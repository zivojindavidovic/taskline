<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\HasUuidv7;

class Task extends Model
{
    use HasUuidv7;

    protected $fillable = [
        'key', 'title', 'description',
        'project_id', 'sprint_id', 'board_column_id',
        'position',
        'parent_task_id',
        'assignee_id', 'created_by',
        'priority', 'tags',
        'completed', 'completed_at', 'completed_by',
        'start_date', 'due_date',
    ];

    protected $casts = [
        'tags'         => 'array',
        'position'     => 'integer',
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

    /**
     * Relations the task panel renders on every node of a subtask tree. Shared
     * by the board, the standalone details endpoint, and the realtime broadcasts
     * so a subtask carries the same shape no matter how it reaches the client.
     *
     * @return array<int|string, mixed>
     */
    public static function subtaskTreeRelations(): array
    {
        return [
            'assignee:id,name,email,avatar_color',
            'assignees:id,name,email,avatar_color',
            'boardColumn:id,name,color',
            'comments' => fn ($q) => $q->oldest(),
            'comments.user:id,name,email,avatar_color',
            'comments.mentionedUsers:id,name,email,avatar_color',
            'comments.replies' => fn ($q) => $q->oldest(),
            'comments.replies.user:id,name,email,avatar_color',
        ];
    }

    /**
     * Eager-load the entire subtask subtree (subtasks of subtasks, to any depth)
     * onto each task's `subtasks` relation, with the panel relations on every
     * node. Walks one depth level at a time across the whole set, so a tree of
     * depth N costs N queries total — not one per node. A node with no children
     * ends up with an empty `subtasks` collection (serializes to `[]`).
     *
     * @param  Collection<int, Task>  $tasks
     */
    public static function loadSubtaskTrees(Collection $tasks): void
    {
        $level = $tasks;
        while ($level->isNotEmpty()) {
            $level->load(['subtasks' => fn ($q) => $q->with(static::subtaskTreeRelations())]);

            $next = [];
            foreach ($level as $node) {
                foreach ($node->subtasks as $child) {
                    $next[] = $child;
                }
            }
            $level = new Collection($next);
        }
    }

    /** Eager-load the full subtask tree onto this single task. */
    public function loadSubtaskTree(): static
    {
        static::loadSubtaskTrees(new Collection([$this]));

        return $this;
    }

    /**
     * IDs of every task beneath this one in the subtask tree (children, their
     * children, and so on). Used to cascade a delete across the whole subtree so
     * deleting a parent never orphans its descendants to the top level.
     *
     * @return array<int, int>
     */
    public function descendantIds(): array
    {
        $ids = [];
        $frontier = [$this->id];

        while (!empty($frontier)) {
            $children = static::whereIn('parent_task_id', $frontier)->pluck('id')->all();
            if (empty($children)) {
                break;
            }
            $ids = array_merge($ids, $children);
            $frontier = $children;
        }

        return $ids;
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
