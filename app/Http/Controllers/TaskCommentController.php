<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskCommentController extends Controller
{
    public function store(Request $request, Task $task): RedirectResponse
    {
        $request->validate(['body' => 'required|string|max:5000']);

        $task->comments()->create([
            'user_id' => auth()->id(),
            'body'    => $request->body,
        ]);

        return back();
    }

    public function reply(Request $request, Task $task, TaskComment $comment): RedirectResponse
    {
        $request->validate(['body' => 'required|string|max:5000']);

        $comment->replies()->create([
            'user_id' => auth()->id(),
            'body'    => $request->body,
        ]);

        return back();
    }
}
