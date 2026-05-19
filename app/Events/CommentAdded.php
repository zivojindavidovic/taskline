<?php

namespace App\Events;

use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommentAdded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Task $task,
        public TaskComment $comment,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('project.' . $this->task->project_id),
        ];
    }

    public function broadcastWith(): array
    {
        $this->comment->loadMissing([
            'user:id,name,email,avatar_color',
            'mentionedUsers:id,name,email,avatar_color',
            'replies' => fn ($q) => $q->oldest(),
            'replies.user:id,name,email,avatar_color',
            'replies.mentionedUsers:id,name,email,avatar_color',
        ]);

        return [
            'task_id' => $this->task->id,
            'comment' => $this->comment->toArray(),
        ];
    }
}
