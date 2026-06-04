<?php

namespace App\Repositories;

use App\Models\Task;

class TaskRepository
{
    public function update(Task $task, array $data): Task
    {
        $task->update($data);
        return $task;
    }

    /**
     * Replace the task's assignees with the given user IDs.
     *
     * Also mirrors the chosen "primary" assignee into the legacy
     * `tasks.assignee_id` column so existing views and notifications keep
     * working. The primary is the first id in the array (callers can sort).
     *
     * Returns the canonical list of user IDs now on the task.
     *
     * @param  array<int>  $userIds
     * @return array<int>
     */
    public function syncAssignees(Task $task, array $userIds): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));

        $task->assignees()->sync($userIds);

        $primary = $userIds[0] ?? null;
        if ($task->assignee_id !== $primary) {
            $task->update(['assignee_id' => $primary]);
        }

        return $userIds;
    }

    public function createSubtask(Task $parent, array $data): Task
    {
        $project = $parent->project;
        $taskNum = $project->tasks()->count() + 1;

        return Task::create([
            'key'             => $project->key . '-' . $taskNum,
            'title'           => $data['title'],
            'priority'        => $data['priority'],
            'project_id'      => $parent->project_id,
            'sprint_id'       => $parent->sprint_id,
            'board_column_id' => $parent->board_column_id,
            'parent_task_id'  => $parent->id,
            'created_by'      => $data['created_by'],
        ]);
    }

    public function updateSubtask(Task $subtask, array $data): Task
    {
        $subtask->update($data);
        return $subtask;
    }

    public function delete(Task $task): void
    {
        // Remove the whole subtree, not just this row. The parent_task_id FK is
        // null-on-delete, so deleting a task with subtasks would otherwise orphan
        // them to the top level — promoting hidden subtasks into stray board
        // cards. Deleting each descendant model fires its own per-row FK cascades
        // (comments, attachments, assignee pivot).
        $descendantIds = $task->descendantIds();
        if (!empty($descendantIds)) {
            Task::whereIn('id', $descendantIds)->get()->each->delete();
        }

        $task->delete();
    }
}
