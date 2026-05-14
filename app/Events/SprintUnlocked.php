<?php

namespace App\Events;

use App\Models\Sprint;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SprintUnlocked implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Sprint $sprint,
        public User $unlockedBy,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('project.' . $this->sprint->project_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'sprint'      => $this->sprint->toArray(),
            'unlocked_by' => $this->unlockedBy->only('id', 'name'),
        ];
    }
}
