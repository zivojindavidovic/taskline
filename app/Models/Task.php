<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    protected $fillable = [
        'key', 'title', 'description',
        'project_id', 'sprint_id', 'board_column_id',
        'parent_task_id',
        'assignee_id', 'created_by',
        'priority', 'tags',
        'completed', 'completed_at', 'completed_by',
        'start_date', 'due_date',
    ];

    protected $casts = [
        'tags'         => 'array',
        'completed'    => 'boolean',
        'completed_at' => 'datetime',
        'start_date'   => 'date',
        'due_date'     => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    public function boardColumn(): BelongsTo
    {
        return $this->belongsTo(BoardColumn::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_assignees')->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->latest();
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class)->oldest();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_task_id')->orderBy('created_at');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class)->latest();
    }

    public function accessRequests(): HasMany
    {
        return $this->hasMany(TaskAccessRequest::class)->latest();
    }

    /**
     * Single source of truth for "can this user see this task".
     *
     * Access is task-level: a user qualifies if they own or are a member of the
     * project, OR they hold an approved access request for *this specific task*.
     * An approved request is the grant itself — we never widen it to project
     * membership, so a grantee gets exactly one task and nothing else.
     */
    public function isAccessibleBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $project = $this->project;

        return $project->owner_id === $user->id
            || $project->members()->where('users.id', $user->id)->exists()
            || $this->hasApprovedAccessFor($user->id);
    }

    /** Whether the given user holds an approved task-level grant for this task. */
    public function hasApprovedAccessFor(int $userId): bool
    {
        return $this->accessRequests()
            ->where('user_id', $userId)
            ->where('status', TaskAccessRequest::STATUS_APPROVED)
            ->exists();
    }
}
