<?php

namespace App\Services;

use App\Events\TaskUpdated;
use App\Models\AuditLog;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Repositories\TaskRepository;

class TaskService
{
    public function __construct(private TaskRepository $repository) {}

    public function update(Task $task, array $data, int $userId): Task
    {
        // Extract assignee_ids before passing the rest to the column update — the
        // pivot is synced separately.
        $assigneeIds = null;
        if (array_key_exists('assignee_ids', $data)) {
            $assigneeIds = $data['assignee_ids'] ?? [];
            unset($data['assignee_ids']);
        }

        $action = $this->resolveAuditAction($task, $data, $assigneeIds);
        $meta   = $this->resolveAuditMeta($task, $data, $assigneeIds);

        if (!empty($data)) {
            $this->repository->update($task, $data);
        }

        if ($assigneeIds !== null) {
            $this->repository->syncAssignees($task, $assigneeIds);
            $task->refresh();
        }

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
        $previous = $task->assignees()->pluck('users.id')->all();
        $this->repository->syncAssignees($task, $userIds);

        AuditLog::create([
            'user_id'    => $actingUserId,
            'project_id' => $task->project_id,
            'task_id'    => $task->id,
            'action'     => 'task.assigned',
            'meta'       => [
                'assignee_ids'          => $task->assignees()->pluck('users.id')->all(),
                'previous_assignee_ids' => $previous,
            ],
        ]);

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

        if (!empty($data)) {
            $this->repository->updateSubtask($subtask, $data);
        }

        if ($assigneeIds !== null) {
            $this->repository->syncAssignees($subtask, $assigneeIds);
            $subtask->refresh();
        }

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

    private function resolveAuditAction(Task $task, array $data, ?array $assigneeIds = null): string
    {
        if (isset($data['board_column_id']) && $data['board_column_id'] != $task->board_column_id) {
            return 'task.moved';
        }
        if (array_key_exists('assignee_id', $data) || $assigneeIds !== null) return 'task.assigned';
        if (isset($data['priority']))                return 'task.priority_changed';
        if (isset($data['title']))                   return 'task.renamed';
        if (isset($data['tags']))                    return 'task.tags_updated';
        if (isset($data['project_id']))              return 'task.project_changed';
        if (array_key_exists('sprint_id', $data))   return 'task.sprint_changed';
        return 'task.updated';
    }

    private function resolveAuditMeta(Task $task, array $data, ?array $assigneeIds = null): array
    {
        if (isset($data['board_column_id']) && $data['board_column_id'] != $task->board_column_id) {
            $col = BoardColumn::find($data['board_column_id']);
            return ['column' => $col?->name];
        }
        if ($assigneeIds !== null) return ['assignee_ids' => array_values($assigneeIds)];
        if (isset($data['priority'])) return ['priority' => $data['priority']];
        if (isset($data['title']))    return ['title' => $data['title']];
        if (isset($data['tags']))     return ['tags' => $data['tags']];
        return [];
    }
}
