<?php

namespace App\Services;

use App\Events\TaskUpdated;
use App\Models\AuditLog;
use App\Models\BoardColumn;
use App\Models\Sprint;
use App\Models\Task;
use App\Repositories\TaskRepository;

class TaskService
{
    public function __construct(
        private TaskRepository $repository,
        private TaskActivityService $activityService,
    ) {}

    public function update(Task $task, array $data, int $userId): Task
    {
        // A sprint that's locked is sealed — block any payload that would route
        // a task into it. Removing a task FROM a locked sprint stays allowed so
        // mistakes can be undone.
        if (array_key_exists('sprint_id', $data) && $data['sprint_id'] !== null
            && (int) $data['sprint_id'] !== (int) $task->sprint_id) {
            $targetSprint = Sprint::find($data['sprint_id']);
            abort_if($targetSprint?->locked, 422, 'Sprint is locked. No new tasks can be added.');
        }

        // Extract assignee_ids before passing the rest to the column update — the
        // pivot is synced separately.
        $assigneeIds = null;
        if (array_key_exists('assignee_ids', $data)) {
            $assigneeIds = $data['assignee_ids'] ?? [];
            unset($data['assignee_ids']);
        }

        // Cross-project move: drop the task in the new project's first column
        // and move it to backlog (sprint_id = null). The old column/sprint belong
        // to the old project, so retaining them would leave the task pointing at
        // a board/sprint it no longer lives in.
        $previousProjectId = $task->project_id;
        $isBacklogMove = $this->isExplicitBacklogMove($task, $data);
        if (isset($data['project_id']) && (int) $data['project_id'] !== (int) $task->project_id) {
            $data['board_column_id'] = $this->resolveFirstColumnId((int) $data['project_id']);
            if (!array_key_exists('sprint_id', $data)) {
                $data['sprint_id'] = null;
            }
        }

        $action = $this->resolveAuditAction($task, $data, $assigneeIds, $isBacklogMove);
        $meta   = $this->resolveAuditMeta($task, $data, $assigneeIds, $previousProjectId, $isBacklogMove);

        $before = $this->activityService->snapshot($task);

        if (!empty($data)) {
            $this->repository->update($task, $data);
        }

        if ($assigneeIds !== null) {
            $this->repository->syncAssignees($task, $assigneeIds);
            $task->refresh();
        }

        $task->refresh()->load(['project:id,name', 'sprint:id,name']);
        $after = $this->activityService->snapshot($task, $assigneeIds);

        $this->activityService->recordChanges($task, null, $before, $after, $userId);

        AuditLog::create([
            'user_id'    => $userId,
            'project_id' => $task->project_id,
            'task_id'    => $task->id,
            'action'     => $action,
            'meta'       => $meta,
        ]);

        $task->refresh()->load(['assignee', 'assignees']);
        broadcast(new TaskUpdated($task))->toOthers();

        return $task;
    }

    /**
     * Replace assignees on a task (idempotent).
     *
     * @param  array<int>  $userIds
     */
    public function setAssignees(Task $task, array $userIds, int $actingUserId): Task
    {
        $before = $this->activityService->snapshot($task);
        $this->repository->syncAssignees($task, $userIds);
        $task->refresh();
        $after = $this->activityService->snapshot($task, $userIds);

        AuditLog::create([
            'user_id'    => $actingUserId,
            'project_id' => $task->project_id,
            'task_id'    => $task->id,
            'action'     => 'task.assigned',
            'meta'       => [
                'assignee_ids'          => $after['assignee_ids'],
                'previous_assignee_ids' => $before['assignee_ids'],
            ],
        ]);

        $this->activityService->recordChanges($task, null, $before, $after, $actingUserId);

        return $task->refresh();
    }

    public function createSubtask(Task $parent, string $title, ?string $priority, int $userId): Task
    {
        $subtask = $this->repository->createSubtask($parent, [
            'title'      => $title,
            'priority'   => $priority ?? 'med',
            'created_by' => $userId,
        ]);

        AuditLog::create([
            'user_id'    => $userId,
            'project_id' => $parent->project_id,
            'task_id'    => $parent->id,
            'action'     => 'task.subtask_added',
            'meta'       => ['subtask_key' => $subtask->key, 'title' => $subtask->title],
        ]);

        $parent->refresh()->load([
            'assignee',
            'assignees',
            'subtasks.assignee',
            'subtasks.assignees',
            'subtasks.boardColumn',
        ]);
        broadcast(new TaskUpdated($parent))->toOthers();

        return $subtask;
    }

    public function updateSubtask(Task $parent, Task $subtask, array $data, int $userId): Task
    {
        $assigneeIds = null;
        if (array_key_exists('assignee_ids', $data)) {
            $assigneeIds = $data['assignee_ids'] ?? [];
            unset($data['assignee_ids']);
        }

        $before = $this->activityService->snapshot($subtask);

        if (!empty($data)) {
            $this->repository->updateSubtask($subtask, $data);
        }

        if ($assigneeIds !== null) {
            $this->repository->syncAssignees($subtask, $assigneeIds);
            $subtask->refresh();
        }

        $subtask->refresh()->load(['project:id,name', 'sprint:id,name']);
        $after = $this->activityService->snapshot($subtask, $assigneeIds);

        $this->activityService->recordChanges($parent, $subtask, $before, $after, $userId);

        $changes = array_keys($data);
        if ($assigneeIds !== null) $changes[] = 'assignee_ids';

        AuditLog::create([
            'user_id'    => $userId,
            'project_id' => $parent->project_id,
            'task_id'    => $parent->id,
            'action'     => 'task.subtask_updated',
            'meta'       => ['subtask_key' => $subtask->key, 'changes' => $changes],
        ]);

        $parent->refresh()->load([
            'assignee',
            'assignees',
            'subtasks.assignee',
            'subtasks.assignees',
            'subtasks.boardColumn',
        ]);
        broadcast(new TaskUpdated($parent))->toOthers();

        return $subtask;
    }

    public function delete(Task $task): void
    {
        $this->repository->delete($task);
    }

    private function resolveAuditAction(Task $task, array $data, ?array $assigneeIds = null, bool $isBacklogMove = false): string
    {
        // A project change takes priority — moving across projects also resets
        // board_column_id and sprint_id, but the *meaning* of the action is the
        // project move, not an in-project column shuffle.
        if (isset($data['project_id']) && (int) $data['project_id'] !== (int) $task->project_id) {
            return 'task.project_changed';
        }
        // Sprint cleared (sprint_id transitioning from a value to null) — within
        // the same project this is the canonical "move to backlog" action.
        if ($isBacklogMove) {
            return 'task.moved_to_backlog';
        }
        if (array_key_exists('board_column_id', $data) && $data['board_column_id'] != $task->board_column_id) {
            return 'task.moved';
        }
        if (array_key_exists('assignee_id', $data) || $assigneeIds !== null) return 'task.assigned';
        if (isset($data['priority']))                return 'task.priority_changed';
        if (isset($data['title']))                   return 'task.renamed';
        if (isset($data['tags']))                    return 'task.tags_updated';
        if (array_key_exists('sprint_id', $data))    return 'task.sprint_changed';
        return 'task.updated';
    }

    private function resolveAuditMeta(Task $task, array $data, ?array $assigneeIds = null, ?int $previousProjectId = null, bool $isBacklogMove = false): array
    {
        if (isset($data['project_id']) && (int) $data['project_id'] !== (int) ($previousProjectId ?? $task->project_id)) {
            return [
                'from_project_id' => $previousProjectId ?? $task->project_id,
                'to_project_id'   => (int) $data['project_id'],
            ];
        }
        if ($isBacklogMove) {
            return [
                'from_sprint_id' => $task->sprint_id,
            ];
        }
        if (array_key_exists('board_column_id', $data) && $data['board_column_id'] != $task->board_column_id) {
            $col = BoardColumn::find($data['board_column_id']);
            return ['column' => $col?->name];
        }
        if ($assigneeIds !== null) return ['assignee_ids' => array_values($assigneeIds)];
        if (isset($data['priority'])) return ['priority' => $data['priority']];
        if (isset($data['title']))    return ['title' => $data['title']];
        if (isset($data['tags']))     return ['tags' => $data['tags']];
        return [];
    }

    private function isExplicitBacklogMove(Task $task, array $data): bool
    {
        $projectStays = !isset($data['project_id'])
            || (int) $data['project_id'] === (int) $task->project_id;

        return $projectStays
            && array_key_exists('sprint_id', $data) && $data['sprint_id'] === null
            && $task->sprint_id !== null;
    }

    private function resolveFirstColumnId(int $projectId): ?int
    {
        return BoardColumn::where('project_id', $projectId)
            ->orderBy('position')
            ->orderBy('id')
            ->value('id');
    }
}
