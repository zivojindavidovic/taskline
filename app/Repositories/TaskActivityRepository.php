<?php

namespace App\Repositories;

use App\Models\Task;
use App\Models\TaskActivity;
use Illuminate\Support\Collection;

class TaskActivityRepository
{
    public function create(array $data): TaskActivity
    {
        return TaskActivity::create($data);
    }

    /**
     * All activity rows attached to a task, oldest first, including subtask
     * changes (which carry the parent task_id with subtask_id set).
     *
     * @return Collection<int, TaskActivity>
     */
    public function forTask(Task $task): Collection
    {
        return TaskActivity::with(['user:id,name,email,avatar_color', 'subtask:id,key,title'])
            ->where('task_id', $task->id)
            ->oldest()
            ->get();
    }
}
