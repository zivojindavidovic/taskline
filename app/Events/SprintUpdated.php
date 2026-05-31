<?php

namespace App\Events;

use App\Models\Sprint;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * One sprint lifecycle change on the board's project channel.
 *
 * $event is the discriminator the frontend switches on:
 *   'created' | 'locked' | 'unlocked' | 'completed' | 'reopened'
 *
 * Mirrors BoardColumnUpdated's single-event-with-discriminator shape so the
 * web SprintController has exactly one broadcast call per action instead of a
 * separate event class per verb.
 */
class SprintUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Sprint $sprint,
        public string $event,
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
            'event'  => $this->event,
            'sprint' => $this->sprint->toArray(),
        ];
    }
}
