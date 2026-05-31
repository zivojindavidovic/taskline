<?php

namespace App\Events;

use App\Models\BoardColumn;
use App\Models\Project;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BoardColumnUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Project $project,
        public BoardColumn $column,
        public string $event, // 'created' | 'updated' | 'deleted'
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('project.' . $this->project->id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'event'  => $this->event,
            'column' => $this->column->toArray(),
        ];
    }
}
