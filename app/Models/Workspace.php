<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    protected $fillable = ['name', 'owner_id', 'color'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_users')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    /**
     * The user's role in this workspace: 'owner' for the workspace owner,
     * otherwise the workspace_users pivot role ('admin' / 'member'), or null
     * when the user does not belong to this workspace at all.
     */
    public function roleOf(User $user): ?string
    {
        if ((int) $this->owner_id === (int) $user->id) {
            return 'owner';
        }

        return $this->users()->where('users.id', $user->id)->value('role');
    }

    /**
     * Owners and admins manage the workspace: create projects, create and
     * run sprints (lock/unlock/complete/reopen). Members and viewers cannot.
     */
    public function canManage(User $user): bool
    {
        return in_array($this->roleOf($user), ['owner', 'admin'], true);
    }
}
