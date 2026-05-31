<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Lifecycle of a task-level access request.
 *
 * $event: 'requested' | 'approved' | 'declined'
 *
 * Broadcasts on TWO channels:
 *   - project.{projectId} — so owner/admins viewing the board or the task panel
 *     see the request appear / leave the pending list live.
 *   - App.Models.User.{userId} — so the requester's locked "Request access"
 *     panel unlocks the instant it's approved (or re-locks on decline).
 */
class TaskAccessRequestUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $taskId,
        public int $projectId,
        public int $userId,
        public string $event,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('project.' . $this->projectId),
            new PrivateChannel('App.Models.User.' . $this->userId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'event'      => $this->event,
            'task_id'    => $this->taskId,
            'project_id' => $this->projectId,
            'user_id'    => $this->userId,
        ];
    }
}
