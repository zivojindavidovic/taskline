<?php

namespace App\Events;

use App\Models\Project;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A new project was created. Broadcast on the WORKSPACE channel (not the
 * project channel) — the whole point is to tell members who aren't yet on the
 * project that a new one appeared, so their sidebar can refresh.
 */
class ProjectCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Project $project) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('workspace.' . $this->project->workspace_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'project' => $this->project->only(['id', 'name', 'key', 'color', 'workspace_id']),
        ];
    }
}
