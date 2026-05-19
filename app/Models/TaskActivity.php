<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskActivity extends Model
{
    public const FIELD_TITLE       = 'title';
    public const FIELD_DESCRIPTION = 'description';
    public const FIELD_PRIORITY    = 'priority';
    public const FIELD_STATUS      = 'status';
    public const FIELD_ASSIGNEES   = 'assignees';
    public const FIELD_PROJECT     = 'project';
    public const FIELD_SPRINT      = 'sprint';
    public const FIELD_START_DATE  = 'start_date';
    public const FIELD_DUE_DATE    = 'due_date';
    public const FIELD_TAGS        = 'tags';

    protected $fillable = [
        'task_id',
        'subtask_id',
        'user_id',
        'field',
        'from_value',
        'to_value',
    ];

    protected $casts = [
        'from_value' => 'array',
        'to_value'   => 'array',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function subtask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'subtask_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
