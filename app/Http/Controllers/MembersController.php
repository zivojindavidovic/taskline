<?php

namespace App\Http\Controllers;

use App\Events\MemberProjectAccessUpdated;
use App\Events\ProjectMembersChanged;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MembersController extends Controller
{
    public function index(Project $project): Response
    {
        $user = auth()->user();
        abort_unless(
            $project->owner_id === $user->id ||
            $project->members()->where('users.id', $user->id)->exists(),
            403
        );

        $members = $project->members()
            ->get()
            ->map(fn ($m) => [
                'id'    => $m->id,
                'name'  => $m->name,
                'email' => $m->email,
                'role'  => $m->pivot->role,
            ]);

        // Add the owner as well (if not already in members)
        $owner = $project->owner;
        $memberIds = $members->pluck('id')->all();
        if (!in_array($owner->id, $memberIds)) {
            $members = collect([[
                'id'    => $owner->id,
                'name'  => $owner->name,
                'email' => $owner->email,
                'role'  => 'owner',
            ]])->concat($members);
        } else {
            $members = $members->map(fn ($m) => $m['id'] === $owner->id
                ? array_merge($m, ['role' => 'owner'])
                : $m
            );
        }

        return Inertia::render('Members', [
            'project' => $project->only(['id', 'uuid', 'name', 'key', 'color']),
            'members' => $members->values(),
            'isOwner' => $project->owner_id === $user->id,
        ]);
    }

    public function invite(Request $request, Project $project): RedirectResponse
    {
        abort_unless($project->owner_id === auth()->id(), 403);

        $data = $request->validate([
            'email' => 'required|email|exists:users,email',
            'role'  => 'required|in:admin,member',
        ]);

        $invitee = User::where('email', $data['email'])->firstOrFail();

        if ($invitee->id === $project->owner_id) {
            return back()->with('error', 'This user is already the project owner.');
        }

        $project->members()->syncWithoutDetaching([
            $invitee->id => ['role' => $data['role']],
        ]);

        \App\Models\AuditLog::create([
            'user_id'    => auth()->id(),
            'project_id' => $project->id,
            'action'     => 'member.invited',
            'meta'       => ['email' => $invitee->email, 'role' => $data['role']],
        ]);

        broadcast(new ProjectMembersChanged($project->id, 'member_invited', $invitee->id))->toOthers();

        // Push the new access to the invitee's own channel so their sidebar
        // picks up the project live (AppLayout reloads on this event).
        broadcast(new MemberProjectAccessUpdated(
            (int) $project->workspace_id,
            (int) $invitee->id,
            Project::accessibleIdsFor((int) $invitee->id, (int) $project->workspace_id),
        ))->toOthers();

        return back()->with('success', "{$invitee->name} has been added to the project.");
    }

    public function updateRole(Request $request, Project $project, User $member): RedirectResponse
    {
        abort_unless($project->owner_id === auth()->id(), 403);

        $data = $request->validate([
            'role' => 'required|in:admin,member',
        ]);

        $project->members()->updateExistingPivot($member->id, ['role' => $data['role']]);

        broadcast(new ProjectMembersChanged($project->id, 'role_updated', $member->id))->toOthers();

        return back()->with('success', 'Role updated.');
    }

    public function remove(Project $project, User $member): RedirectResponse
    {
        abort_unless($project->owner_id === auth()->id(), 403);

        $project->members()->detach($member->id);

        // Losing project access also strips the member from every task in the
        // project they were assigned to — both the legacy single-assignee column
        // and the multi-assignee pivot — so no stale assignment survives.
        $taskIds = $project->tasks()->pluck('id');
        if ($taskIds->isNotEmpty()) {
            Task::whereIn('id', $taskIds)
                ->where('assignee_id', $member->id)
                ->update(['assignee_id' => null]);
            DB::table('task_assignees')
                ->whereIn('task_id', $taskIds)
                ->where('user_id', $member->id)
                ->delete();
        }

        \App\Models\AuditLog::create([
            'user_id'    => auth()->id(),
            'project_id' => $project->id,
            'action'     => 'member.removed',
            'meta'       => ['email' => $member->email],
        ]);

        broadcast(new ProjectMembersChanged($project->id, 'member_removed', $member->id))->toOthers();

        // Tell the removed user directly so their sidebar drops the project live.
        broadcast(new MemberProjectAccessUpdated(
            (int) $project->workspace_id,
            (int) $member->id,
            Project::accessibleIdsFor((int) $member->id, (int) $project->workspace_id),
        ))->toOthers();

        return back()->with('success', "{$member->name} has been removed from the project.");
    }
}
