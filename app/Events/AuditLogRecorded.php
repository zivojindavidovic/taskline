<?php

namespace App\Events;

use App\Models\AuditLog;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A new audit-log entry was written. Dispatched automatically for EVERY
 * AuditLog::create via the model's booted() created hook — one wiring covers
 * column/sprint/project/member/task actions across the whole app, so the
 * project's Audit log view updates live without instrumenting each call site.
 *
 * Broadcast on the project channel (audit entries are always project-scoped).
 */
class AuditLogRecorded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public AuditLog $log) {}

    public function broadcastOn(): array
    {
        // Defensive: a project-less entry has no channel to land on.
        if (! $this->log->project_id) {
            return [];
        }

        return [
            new PrivateChannel('project.' . $this->log->project_id),
        ];
    }

    public function broadcastWith(): array
    {
        $this->log->loadMissing('user:id,name,avatar_color');

        return [
            'log' => [
                'id'         => $this->log->id,
                'action'     => $this->log->action,
                'meta'       => $this->log->meta,
                'task_id'    => $this->log->task_id,
                'project_id' => $this->log->project_id,
                'created_at' => $this->log->created_at,
                'user'       => $this->log->user ? [
                    'id'           => $this->log->user->id,
                    'name'         => $this->log->user->name,
                    'avatar_color' => $this->log->user->avatar_color,
                ] : null,
            ],
        ];
    }
}
