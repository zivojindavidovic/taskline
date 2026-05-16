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
        $task->delete();
    }
}
