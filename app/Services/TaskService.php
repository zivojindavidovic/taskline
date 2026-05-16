<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Repositories\TaskRepository;

class TaskService
{
    public function __construct(private TaskRepository $repository) {}

    public function update(Task $task, array $data, int $userId): Task
    {
        $action = $this->resolveAuditAction($task, $data);
        $meta   = $this->resolveAuditMeta($task, $data);

        $this->repository->update($task, $data);

        AuditLog::create([
            'user_id'    => $userId,
            'project_id' => $task->project_id,
            'task_id'    => $task->id,
            'action'     => $action,
            'meta'       => $meta,
        ]);

        return $task;
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

        return $subtask;
    }

    public function updateSubtask(Task $parent, Task $subtask, array $data, int $userId): Task
    {
        $this->repository->updateSubtask($subtask, $data);

        AuditLog::create([
            'user_id'    => $userId,
            'project_id' => $parent->project_id,
            'task_id'    => $parent->id,
            'action'     => 'task.subtask_updated',
            'meta'       => ['subtask_key' => $subtask->key, 'changes' => array_keys($data)],
        ]);

        return $subtask;
    }

    public function delete(Task $task): void
    {
        $this->repository->delete($task);
    }

    private function resolveAuditAction(Task $task, array $data): string
    {
        if (isset($data['board_column_id']) && $data['board_column_id'] != $task->board_column_id) {
            return 'task.moved';
        }
        if (array_key_exists('assignee_id', $data)) return 'task.assigned';
        if (isset($data['priority']))                return 'task.priority_changed';
        if (isset($data['title']))                   return 'task.renamed';
        if (isset($data['tags']))                    return 'task.tags_updated';
        if (isset($data['project_id']))              return 'task.project_changed';
        if (array_key_exists('sprint_id', $data))   return 'task.sprint_changed';
        return 'task.updated';
    }

    private function resolveAuditMeta(Task $task, array $data): array
    {
        if (isset($data['board_column_id']) && $data['board_column_id'] != $task->board_column_id) {
            $col = BoardColumn::find($data['board_column_id']);
            return ['column' => $col?->name];
        }
        if (isset($data['priority'])) return ['priority' => $data['priority']];
        if (isset($data['title']))    return ['title' => $data['title']];
        if (isset($data['tags']))     return ['tags' => $data['tags']];
        return [];
    }
}
