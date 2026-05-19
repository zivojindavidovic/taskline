<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProjectFilter extends Model
{
    protected $fillable = [
        'user_id',
        'project_id',
        'sprint_ids',
        'assignee_ids',
        'priorities',
        'status_ids',
        'statuses',
        'hide_completed',
        'unassigned',
        'view_mode',
        'view_sprint_id',
    ];

    protected $casts = [
        'sprint_ids'     => 'array',
        'assignee_ids'   => 'array',
        'priorities'     => 'array',
        'status_ids'     => 'array',
        'statuses'       => 'array',
        'hide_completed' => 'boolean',
        'unassigned'     => 'boolean',
    ];

    public const VIEW_MODES = ['active', 'backlog', 'all'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
