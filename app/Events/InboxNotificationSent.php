<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A new inbox-worthy event now concerns a specific user — an @mention or a new
 * assignment. The inbox itself is derived on read (see InboxService), so this
 * event carries no payload beyond the trigger: the user's Inbox page and the
 * sidebar badge re-fetch from the server when it lands.
 *
 * Broadcast on the recipient's private user channel.
 */
class InboxNotificationSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public string $kind, // 'mention' | 'assigned'
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.' . $this->userId),
        ];
    }

    public function broadcastWith(): array
    {
        return ['kind' => $this->kind];
    }
}
