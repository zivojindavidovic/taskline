<?php

namespace App\Events;

use App\Models\TaskActivity;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskActivityRecorded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public TaskActivity $activity, public int $projectId) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('project.' . $this->projectId),
        ];
    }

    public function broadcastWith(): array
    {
        $this->activity->loadMissing([
            'user:id,name,email,avatar_color',
            'subtask:id,key,title',
        ]);

        return ['activity' => $this->activity->toArray()];
    }
}
