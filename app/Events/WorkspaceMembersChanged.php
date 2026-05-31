<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Workspace membership / invitation roster changed.
 *
 * $event: 'member_added' | 'member_invited' | 'role_updated'
 *       | 'member_removed' | 'invitation_revoked'
 *
 * Broadcast on the workspace channel so the Members page roster (and any
 * sidebar counts) refresh live for every admin viewing it.
 */
class WorkspaceMembersChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $workspaceId,
        public string $event,
        public ?int $memberId = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('workspace.' . $this->workspaceId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'event'     => $this->event,
            'member_id' => $this->memberId,
        ];
    }
}
