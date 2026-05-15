<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WorkspaceInvitation;
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
                'id'     => $m->id,
                'name'   => $m->name,
                'email'  => $m->email,
                'role'   => $m->pivot->role,
                'joined' => $m->pivot->created_at?->format('M j, Y') ?? '—',
            ]);

        $owner     = $workspace->owner;
        $memberIds = $members->pluck('id')->all();

        if (!in_array($owner->id, $memberIds)) {
            $members = collect([[
                'id'     => $owner->id,
                'name'   => $owner->name,
                'email'  => $owner->email,
                'role'   => 'owner',
                'joined' => $workspace->created_at->format('M j, Y'),
            ]])->concat($members);
        } else {
            $members = $members->map(fn ($m) => $m['id'] === $owner->id
                ? array_merge($m, ['role' => 'owner'])
                : $m
            );
        }

        $pending = WorkspaceInvitation::where('workspace_id', $workspace->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($i) => [
                'id'    => $i->id,
                'email' => $i->email,
                'role'  => ucfirst($i->role),
                'sent'  => $i->created_at->format('M j'),
            ]);

        return Inertia::render('WorkspaceMembers', [
            'members'    => $members->values(),
            'pending'    => $pending,
            'isOwner'    => $workspace->owner_id === $user->id,
            'authUserId' => $user->id,
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $user      = $request->user();
        $workspace = $user->currentWorkspace;

        abort_unless($workspace && $workspace->owner_id === $user->id, 403);

        $data = $request->validate([
            'email' => 'required|email',
            'role'  => 'required|in:admin,member,viewer',
        ]);

        $existingUser = User::where('email', $data['email'])->first();
        if ($existingUser && $workspace->users()->where('users.id', $existingUser->id)->exists()) {
            return back()->withErrors(['email' => 'This user is already a member of the workspace.']);
        }

        if (WorkspaceInvitation::where('workspace_id', $workspace->id)
                ->where('email', $data['email'])->exists()) {
            return back()->withErrors(['email' => 'An invitation has already been sent to this email.']);
        }

        WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email'        => $data['email'],
            'role'         => $data['role'],
            'invited_by'   => $user->id,
        ]);

        return back()->with('success', "Invitation sent to {$data['email']}.");
    }

    public function revoke(Request $request, int $invitation): RedirectResponse
    {
        $user      = $request->user();
        $workspace = $user->currentWorkspace;

        abort_unless($workspace && $workspace->owner_id === $user->id, 403);

        WorkspaceInvitation::where('id', $invitation)
            ->where('workspace_id', $workspace->id)
            ->delete();

        return back()->with('success', 'Invitation revoked.');
    }

    public function updateRole(Request $request, int $member): RedirectResponse
    {
        $user      = $request->user();
        $workspace = $user->currentWorkspace;

        abort_unless($workspace && $workspace->owner_id === $user->id, 403);

        $data = $request->validate(['role' => 'required|in:admin,member,viewer']);

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
