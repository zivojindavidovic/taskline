<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // For every user that doesn't have a workspace yet, create one
        // and assign all their projects to it.
        User::with('ownedProjects')->get()->each(function (User $user) {
            if ($user->current_workspace_id) {
                return; // already has a workspace, skip
            }

            $workspace = Workspace::create([
                'name'     => $user->name . "'s Workspace",
                'color'    => '#4f46e5',
                'owner_id' => $user->id,
            ]);

            // Add user as owner member
            $workspace->users()->attach($user->id, ['role' => 'owner']);

            // Assign all projects owned by this user to the workspace
            $user->ownedProjects()->update(['workspace_id' => $workspace->id]);

            // Set as current workspace
            $user->updateQuietly(['current_workspace_id' => $workspace->id]);
        });
    }

    public function down(): void
    {
        // Not reversible without data loss risk — intentionally left empty
    }
};
