<?php

namespace App\Http\Controllers\Api;

use App\Events\CommentAdded;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskCommentController extends Controller
{
    public function store(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->authorizeMember($project);
        abort_if($task->project_id !== $project->id, 404);

        $validated = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $comment = $task->comments()->create([
            'user_id' => $request->user()->id,
            'body'    => $validated['body'],
        ]);

        $comment->load('user:id,name,email', 'replies.user:id,name,email');

        broadcast(new CommentAdded($task, $comment))->toOthers();

        return response()->json($comment, 201);
    }

    public function reply(Request $request, Project $project, Task $task, TaskComment $comment): JsonResponse
    {
        $this->authorizeMember($project);
        abort_if($task->project_id !== $project->id, 404);
        abort_if($comment->task_id !== $task->id, 404);

        $validated = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $reply = $comment->replies()->create([
            'user_id' => $request->user()->id,
            'body'    => $validated['body'],
        ]);

        $reply->load('user:id,name,email');

        return response()->json($reply, 201);
    }

    public function destroy(Project $project, Task $task, TaskComment $comment): JsonResponse
    {
        $this->authorizeMember($project);
        abort_if($comment->task_id !== $task->id, 404);
        abort_if($comment->user_id !== auth()->id(), 403);

        $comment->delete();

        return response()->json(null, 204);
    }

    private function authorizeMember(Project $project): void
    {
        $user = auth()->user();
        $ok = $project->owner_id === $user->id
            || $project->members()->where('user_id', $user->id)->exists();
        abort_unless($ok, 403);
    }
}
