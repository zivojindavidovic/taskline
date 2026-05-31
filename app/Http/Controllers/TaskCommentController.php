<?php

namespace App\Http\Controllers;

use App\Events\CommentAdded;
use App\Events\ReplyAdded;
use App\Models\Task;
use App\Models\TaskComment;
use App\Services\CommentMentionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskCommentController extends Controller
{
    public function __construct(private CommentMentionService $mentions) {}

    public function store(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeTaskAccess($task);
        $request->validate(['body' => 'required|string|max:5000']);

        $comment = $task->comments()->create([
            'user_id' => auth()->id(),
            'body'    => $request->body,
        ]);

        $this->mentions->syncForComment($comment, $task->project);

        broadcast(new CommentAdded($task, $comment))->toOthers();

        return back();
    }

    public function reply(Request $request, Task $task, TaskComment $comment): RedirectResponse
    {
        $this->authorizeTaskAccess($task);
        $request->validate(['body' => 'required|string|max:5000']);

        $reply = $comment->replies()->create([
            'user_id' => auth()->id(),
            'body'    => $request->body,
        ]);

        $this->mentions->syncForReply($reply, $task->project);

        broadcast(new ReplyAdded($task, $reply))->toOthers();

        return back();
    }

    /**
     * Autocomplete list for the @-mention picker: workspace/project members
     * minus the current user.
     */
    public function mentionableUsers(Task $task): JsonResponse
    {
        $this->authorizeTaskAccess($task);
        return response()->json(
            $this->mentions->mentionableUsers($task->project, (int) auth()->id())
        );
    }

    private function authorizeTaskAccess(Task $task): void
    {
        // Honors task-level grants too (Task::isAccessibleBy), so a granted
        // non-member can comment on exactly the task they were given.
        abort_unless($task->isAccessibleBy(auth()->user()), 403);
    }
}
