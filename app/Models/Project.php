<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\HasUuidv7;

class Project extends Model
{
    use HasUuidv7;

    protected $fillable = ['name', 'key', 'color', 'owner_id', 'workspace_id'];

    public function workspace(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Workspace::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function sprints(): HasMany
    {
        return $this->hasMany(Sprint::class);
    }

    public function boardColumns(): HasMany
    {
        return $this->hasMany(BoardColumn::class)->orderBy('position');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * IDs of every project in a workspace the given user can still access (owns
     * or is a member of). Drives the realtime sidebar refresh that follows any
     * access change — invite, removal, or project deletion.
     *
     * @return array<int, int>
     */
    public static function accessibleIdsFor(int $userId, int $workspaceId): array
    {
        return static::query()
            ->where('workspace_id', $workspaceId)
            ->where(function ($q) use ($userId) {
                $q->where('owner_id', $userId)
                  ->orWhereHas('members', fn ($q2) => $q2->where('users.id', $userId));
            })
            ->pluck('id')
            ->all();
    }
}
