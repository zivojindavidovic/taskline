<?php

namespace App\Models;

use App\Events\AuditLogRecorded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = ['user_id', 'project_id', 'task_id', 'action', 'meta'];

    protected $casts = [
        'meta' => 'array',
    ];

    /**
     * Every audit entry written anywhere in the app broadcasts itself on its
     * project channel, so the Audit log view stays live without each call site
     * having to remember to broadcast. One hook, total coverage.
     */
    protected static function booted(): void
    {
        static::created(function (AuditLog $log) {
            if ($log->project_id) {
                broadcast(new AuditLogRecorded($log))->toOthers();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
