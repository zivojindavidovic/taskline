<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceMembersController extends Controller
{
    public function index(Request $request): Response
    {
        $user      = $request->user();
        $workspace = $user->currentWorkspace;

        abort_unless($workspace, 404);

        $members = $workspace->users()
            ->get()
            ->map(fn ($m) => [
                'id'    => $m->id,
                'name'  => $m->name,
                'email' => $m->email,
                'role'  => $m->pivot->role,
            ]);

        $owner = $workspace->owner;
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

        return Inertia::render('WorkspaceMembers', [
            'members' => $members->values(),
            'isOwner' => $workspace->owner_id === $user->id,
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $user      = $request->user();
        $workspace = $user->currentWorkspace;

        abort_unless($workspace && $workspace->owner_id === $user->id, 403);

        $data = $request->validate([
            'email' => 'required|email|exists:users,email',
            'role'  => 'required|in:admin,member',
        ]);

        $invitee = User::where('email', $data['email'])->first();

        if ($workspace->users()->where('users.id', $invitee->id)->exists()) {
            return back()->withErrors(['email' => 'This user is already a member of the workspace.']);
        }

        $workspace->users()->attach($invitee->id, ['role' => $data['role']]);

        return back()->with('success', "{$invitee->name} added to workspace.");
    }

    public function updateRole(Request $request, int $member): RedirectResponse
    {
        $user      = $request->user();
        $workspace = $user->currentWorkspace;

        abort_unless($workspace && $workspace->owner_id === $user->id, 403);

        $data = $request->validate(['role' => 'required|in:admin,member']);

        $workspace->users()->updateExistingPivot($member, ['role' => $data['role']]);

        return back()->with('success', 'Role updated.');
    }

    public function remove(Request $request, int $member): RedirectResponse
    {
        $user      = $request->user();
        $workspace = $user->currentWorkspace;

        abort_unless($workspace && $workspace->owner_id === $user->id, 403);
        abort_if($member === $user->id, 403, 'You cannot remove yourself.');

        $workspace->users()->detach($member);

        User::where('id', $member)
            ->where('current_workspace_id', $workspace->id)
            ->update(['current_workspace_id' => null]);

        return back()->with('success', 'Member removed from workspace.');
    }
}
