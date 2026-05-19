<?php

namespace App\Services;

use App\Events\TaskActivityRecorded;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use App\Repositories\TaskActivityRepository;
use Illuminate\Support\Collection;

/**
 * Records human-readable activity for *anything that changes on a task or
 * subtask*: title, description, priority, status (completed/reopened),
 * assignees, project, sprint, start_date, due_date, tags.
 *
 * Each tracked change yields ONE TaskActivity row. The from_value / to_value
 * JSON columns store enough denormalized snapshot information (names, ids)
 * that the rendered message stays stable even after the linked sprint /
 * project / user is renamed or deleted.
 */
class TaskActivityService
{
    public function __construct(private TaskActivityRepository $repository) {}

    /**
     * Snapshot the parts of a task that are interesting for activity diffing.
     * Optionally accepts a pre-resolved assignee_ids list (cheaper than
     * re-loading the relation when the caller already has it).
     *
     * Shape:
     *   [
     *     'title' => '...',
     *     'description' => '...',
     *     'priority' => 'med',
     *     'completed' => false,
     *     'project_id' => 1, 'project_name' => 'X',
     *     'sprint_id' => 2 | null, 'sprint_name' => 'Sprint 1' | null,
     *     'start_date' => 'Y-m-d' | null, 'due_date' => 'Y-m-d' | null,
     *     'tags' => [...] | null,
     *     'assignee_ids' => [...], 'assignee_names' => ['Alice', 'Bob'],
     *   ]
     */
    public function snapshot(Task $task, ?array $assigneeIds = null): array
    {
        if ($assigneeIds === null) {
            $assigneeIds = $task->assignees()->pluck('users.id')->all();
        }
        $assigneeIds = array_values(array_map('intval', $assigneeIds));
        $names = $assigneeIds
            ? User::whereIn('id', $assigneeIds)->pluck('name', 'id')->all()
            : [];
        $assigneeNames = array_map(fn ($id) => $names[$id] ?? "User #{$id}", $assigneeIds);

        return [
            'title'          => (string) $task->title,
            'description'    => (string) ($task->description ?? ''),
            'priority'       => (string) $task->priority,
            'completed'      => (bool) $task->completed,
            'project_id'     => (int) $task->project_id,
            'project_name'   => optional($task->project)->name ?? optional(Project::find($task->project_id))->name,
            'sprint_id'      => $task->sprint_id ? (int) $task->sprint_id : null,
            'sprint_name'    => $task->sprint_id ? (optional($task->sprint)->name ?? optional(Sprint::find($task->sprint_id))->name) : null,
            'start_date'     => $this->dateString($task->start_date),
            'due_date'       => $this->dateString($task->due_date),
            'tags'           => $task->tags ? array_values($task->tags) : [],
            'assignee_ids'   => $assigneeIds,
            'assignee_names' => $assigneeNames,
        ];
    }

    /**
     * Diff two snapshots and persist one activity row per changed field.
     * If $subtask is provided, the activity row is anchored to the parent
     * task ($task) but tags `subtask_id` for display grouping.
     */
    public function recordChanges(
        Task $task,
        ?Task $subtask,
        array $before,
        array $after,
        int $userId,
    ): void {
        $taskIdForRow    = $task->id;
        $subtaskIdForRow = $subtask?->id;
        $projectId       = (int) $task->project_id;

        if ($before['title'] !== $after['title']) {
            $this->write($projectId, $taskIdForRow, $subtaskIdForRow, $userId, TaskActivity::FIELD_TITLE,
                ['value' => $before['title']],
                ['value' => $after['title']],
            );
        }

        if ($before['description'] !== $after['description']) {
            $this->write($projectId, $taskIdForRow, $subtaskIdForRow, $userId, TaskActivity::FIELD_DESCRIPTION,
                ['value' => $before['description']],
                ['value' => $after['description']],
            );
        }

        if ($before['priority'] !== $after['priority']) {
            $this->write($projectId, $taskIdForRow, $subtaskIdForRow, $userId, TaskActivity::FIELD_PRIORITY,
                ['value' => $before['priority']],
                ['value' => $after['priority']],
            );
        }

        if ($before['completed'] !== $after['completed']) {
            $this->write($projectId, $taskIdForRow, $subtaskIdForRow, $userId, TaskActivity::FIELD_STATUS,
                ['value' => $before['completed']],
                ['value' => $after['completed']],
            );
        }

        if ($before['project_id'] !== $after['project_id']) {
            // Cross-project move: broadcast on the destination project channel
            // (that's where the task lives now and where other viewers expect
            // to see the activity feed update).
            $this->write((int) $after['project_id'], $taskIdForRow, $subtaskIdForRow, $userId, TaskActivity::FIELD_PROJECT,
                ['id' => $before['project_id'], 'name' => $before['project_name']],
                ['id' => $after['project_id'],  'name' => $after['project_name']],
            );
        }

        if ($before['sprint_id'] !== $after['sprint_id']) {
            $this->write($projectId, $taskIdForRow, $subtaskIdForRow, $userId, TaskActivity::FIELD_SPRINT,
                $before['sprint_id'] !== null ? ['id' => $before['sprint_id'], 'name' => $before['sprint_name']] : null,
                $after['sprint_id']  !== null ? ['id' => $after['sprint_id'],  'name' => $after['sprint_name']]  : null,
            );
        }

        if ($before['start_date'] !== $after['start_date']) {
            $this->write($projectId, $taskIdForRow, $subtaskIdForRow, $userId, TaskActivity::FIELD_START_DATE,
                ['value' => $before['start_date']],
                ['value' => $after['start_date']],
            );
        }

        if ($before['due_date'] !== $after['due_date']) {
            $this->write($projectId, $taskIdForRow, $subtaskIdForRow, $userId, TaskActivity::FIELD_DUE_DATE,
                ['value' => $before['due_date']],
                ['value' => $after['due_date']],
            );
        }

        $tagsBefore = $before['tags'] ?? [];
        $tagsAfter  = $after['tags']  ?? [];
        if ($this->listsDiffer($tagsBefore, $tagsAfter)) {
            $this->write($projectId, $taskIdForRow, $subtaskIdForRow, $userId, TaskActivity::FIELD_TAGS,
                ['value' => $tagsBefore],
                ['value' => $tagsAfter],
            );
        }

        if ($this->listsDiffer($before['assignee_ids'], $after['assignee_ids'])) {
            $this->write($projectId, $taskIdForRow, $subtaskIdForRow, $userId, TaskActivity::FIELD_ASSIGNEES,
                ['ids' => $before['assignee_ids'], 'names' => $before['assignee_names']],
                ['ids' => $after['assignee_ids'],  'names' => $after['assignee_names']],
            );
        }
    }

    /**
     * Convenience helper used by the complete/uncomplete endpoints, which
     * don't go through the generic update flow.
     */
    public function recordStatusChange(Task $task, bool $from, bool $to, int $userId): void
    {
        if ($from === $to) {
            return;
        }
        $this->write(
            (int) $task->project_id,
            $task->parent_task_id ?: $task->id,
            $task->parent_task_id ? $task->id : null,
            $userId,
            TaskActivity::FIELD_STATUS,
            ['value' => $from],
            ['value' => $to],
        );
    }

    public function listForTask(Task $task): Collection
    {
        return $this->repository->forTask($task);
    }

    private function write(int $projectId, int $taskId, ?int $subtaskId, int $userId, string $field, ?array $from, ?array $to): void
    {
        $activity = $this->repository->create([
            'task_id'    => $taskId,
            'subtask_id' => $subtaskId,
            'user_id'    => $userId,
            'field'      => $field,
            'from_value' => $from,
            'to_value'   => $to,
        ]);

        broadcast(new TaskActivityRecorded($activity, $projectId))->toOthers();
    }

    private function dateString(mixed $date): ?string
    {
        if ($date === null) {
            return null;
        }
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }
        return substr((string) $date, 0, 10);
    }

    /**
     * Order-insensitive comparison for arrays of scalars (assignee ids, tags).
     */
    private function listsDiffer(array $a, array $b): bool
    {
        $a = array_values(array_unique($a));
        $b = array_values(array_unique($b));
        sort($a);
        sort($b);
        return $a != $b;
    }
}
