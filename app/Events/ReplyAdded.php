<?php

namespace App\Events;

use App\Models\CommentReply;
use App\Models\Task;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReplyAdded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Task $task,
        public CommentReply $reply,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('project.' . $this->task->project_id),
        ];
    }

    public function broadcastWith(): array
    {
        $this->reply->loadMissing([
            'user:id,name,email,avatar_color',
            'mentionedUsers:id,name,email,avatar_color',
        ]);

        return [
            'task_id'    => $this->task->id,
            'comment_id' => $this->reply->task_comment_id,
            'reply'      => $this->reply->toArray(),
        ];
    }
}
