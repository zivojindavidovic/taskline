<?php

namespace App\Http\Controllers;

use App\Http\Requests\InviteMemberRequest;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceMembersController extends Controller
{
    public function __construct(private InvitationService $invitations) {}

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

    public function invite(InviteMemberRequest $request): RedirectResponse
    {
        $user      = $request->user();
        $workspace = $user->currentWorkspace;
        $data      = $request->validated();

        try {
            $this->invitations->invite($workspace, $data['email'], $data['role'], $user->id);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', "Invitation sent to {$data['email']}.");
    }

    public function revoke(Request $request, int $invitation): RedirectResponse
    {
        $user      = $request->user();
        $workspace = $user->currentWorkspace;

        abort_unless($workspace && $workspace->owner_id === $user->id, 403);

        $this->invitations->revoke($workspace, $invitation, $user->id);

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
