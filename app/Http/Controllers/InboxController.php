<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskComment;
use Inertia\Inertia;
use Inertia\Response;

class InboxController extends Controller
{
    public function index(): Response
    {
        $userId = auth()->id();

        // Comments on tasks assigned to the user (excluding their own comments)
        $assignedComments = TaskComment::whereHas('task', fn ($q) => $q->where('assignee_id', $userId))
            ->where('user_id', '!=', $userId)
            ->with(['task:id,key,title,project_id', 'task.project:id,name,key,color', 'user:id,name'])
            ->latest()
            ->take(30)
            ->get()
            ->map(fn ($c) => [
                'id'          => 'c-'.$c->id,
                'actor'       => $c->user->name,
                'verb'        => 'commented on',
                'target'      => $c->task->key,
                'excerpt'     => $c->body,
                'time'        => $c->created_at->diffForHumans(),
                'task_id'     => $c->task_id,
                'project_id'  => $c->task->project_id,
                'project_key' => $c->task->project?->key,
                'created_at'  => $c->created_at,
            ]);

        // Tasks assigned to user (by someone else)
        $assignedTasks = Task::where('assignee_id', $userId)
            ->where('created_by', '!=', $userId)
            ->with(['creator:id,name', 'project:id,name,key,color'])
            ->latest()
            ->take(10)
            ->get()
            ->map(fn ($t) => [
                'id'          => 'a-'.$t->id,
                'actor'       => $t->creator?->name ?? 'Someone',
                'verb'        => 'assigned you to',
                'target'      => $t->key,
                'excerpt'     => $t->title,
                'time'        => $t->created_at->diffForHumans(),
                'task_id'     => $t->id,
                'project_id'  => $t->project_id,
                'project_key' => $t->project?->key,
                'created_at'  => $t->created_at,
            ]);

        $notifications = $assignedComments->concat($assignedTasks)
            ->sortByDesc('created_at')
            ->values();

        return Inertia::render('Inbox', ['notifications' => $notifications]);
    }
}
