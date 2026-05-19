<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MemberProjectAccessUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $workspaceId,
        public int $memberId,
        public array $projectIds,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('workspace.' . $this->workspaceId),
            new PrivateChannel('App.Models.User.' . $this->memberId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'member_id'      => $this->memberId,
            'project_access' => $this->projectIds,
        ];
    }
}
