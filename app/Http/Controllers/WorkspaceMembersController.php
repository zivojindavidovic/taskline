<?php

namespace App\Http\Controllers;

use App\Events\InvitationProjectAccessUpdated;
use App\Events\MemberProjectAccessUpdated;
use App\Events\WorkspaceMembersChanged;
use App\Http\Requests\InviteMemberRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Services\InvitationService;
use App\Support\Deployment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceMembersController extends Controller
{
    private const COLORS = ['#4f46e5', '#0891b2', '#16a34a', '#7c3aed', '#d97706', '#dc2626', '#0d9488'];

    public function __construct(private InvitationService $invitations) {}

    public function index(Request $request): Response
    {
        $user      = $request->user();
        $workspace = $user->currentWorkspace;

        abort_unless($workspace, 404);

        // Owners/admins manage the whole access matrix, so they see every
        // project in the workspace. A plain member only sees the projects they
        // actually belong to — same scoping the sidebar and My tasks use — so
        // the Members tab can't leak the existence of projects they can't open.
        $wsRole = $workspace->users()
            ->where('users.id', $user->id)
            ->first()?->pivot->role;
        $managesAll = $workspace->owner_id === $user->id
            || in_array($wsRole, ['owner', 'admin'], true);

        $projects = $workspace->projects()
            ->when(! $managesAll, fn ($q) => $q->where(function ($q2) use ($user) {
                $q2->where('owner_id', $user->id)
                   ->orWhereHas('members', fn ($q3) => $q3->where('users.id', $user->id));
            }))
            ->orderBy('name')
            ->get(['id', 'uuid', 'key', 'name', 'color']);

        $projectIds = $projects->pluck('id');

        // user_id => [project_id, ...] for projects in THIS workspace.
        $accessByUser = DB::table('workspace_members')
            ->whereIn('project_id', $projectIds)
            ->select('user_id', 'project_id')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->pluck('project_id')->all());

        $members = $workspace->users()
            ->get()
            ->map(fn ($m) => [
                'id'            => $m->id,
                'name'          => $m->name,
                'email'         => $m->email,
                'role'          => $m->pivot->role,
                'joined'        => $m->pivot->created_at?->format('M j, Y') ?? '—',
                'projectAccess' => $accessByUser->get($m->id, []),
            ]);

        $owner     = $workspace->owner;
        $memberIds = $members->pluck('id')->all();

        if (!in_array($owner->id, $memberIds)) {
            $members = collect([[
                'id'            => $owner->id,
                'name'          => $owner->name,
                'email'         => $owner->email,
                'role'          => 'owner',
                'joined'        => $workspace->created_at->format('M j, Y'),
                'projectAccess' => $projectIds->all(),
            ]])->concat($members);
        } else {
            $members = $members->map(fn ($m) => $m['id'] === $owner->id
                ? array_merge($m, ['role' => 'owner', 'projectAccess' => $projectIds->all()])
                : $m
            );
        }

        $pending = WorkspaceInvitation::where('workspace_id', $workspace->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($i) => [
                'id'            => $i->id,
                'email'         => $i->email,
                'role'          => ucfirst($i->role),
                'sent'          => $i->created_at->format('M j'),
                // null → "all projects" (legacy invites); empty array → "no access".
                'projectAccess' => $i->projects ?? $projectIds->all(),
            ]);

        return Inertia::render('WorkspaceMembers', [
            'members'    => $members->values(),
            'pending'    => $pending,
            'projects'   => $projects,
            'isOwner'    => $workspace->owner_id === $user->id,
            'authUserId' => $user->id,
        ]);
    }

    public function invite(InviteMemberRequest $request): RedirectResponse
    {
        // Email invitations are a Cloud-only flow. Self-hosted instances create
        // accounts directly (see addMember) — there is no mail/invite pipeline.
        abort_if(Deployment::isSelfHosted($request), 404);

        $user      = $request->user();
        $workspace = $user->currentWorkspace;
        $data      = $request->validated();

        try {
            $this->invitations->invite(
                $workspace,
                $data['email'],
                $data['role'],
                $user->id,
                $this->sanitizeProjectIds($workspace, $data['projects'] ?? null),
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        broadcast(new WorkspaceMembersChanged($workspace->id, 'member_invited'))->toOthers();

        return back()->with('success', "Invitation sent to {$data['email']}.");
    }

    /**
     * Self-hosted only: create a member account directly, with a generated
     * password, active immediately — no email verification, no pending invite.
     * Mirrors the self-hosted team-account step of onboarding.
     */
    public function addMember(Request $request): RedirectResponse
    {
        abort_unless(Deployment::isSelfHosted($request), 404);

        $user      = $request->user();
        $workspace = $user->currentWorkspace;

        abort_unless($workspace && $workspace->owner_id === $user->id, 403);

        $data = $request->validate([
            'name'       => ['nullable', 'string', 'max:255'],
            'email'      => ['required', 'email', 'max:255'],
            'role'       => ['required', 'in:admin,member,viewer'],
            'projects'   => ['sometimes', 'array'],
            'projects.*' => ['integer'],
        ]);

        $email = strtolower(trim($data['email']));

        if (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Someone with this email already has an account.',
            ]);
        }

        $password    = $this->generatePassword();
        $projectIds  = $this->sanitizeProjectIds($workspace, $data['projects'] ?? []) ?? [];
        $created     = null;

        DB::transaction(function () use ($data, $email, $password, $workspace, $projectIds, &$created) {
            $member = User::create([
                'name'         => $data['name'] ?: explode('@', $email)[0],
                'email'        => $email,
                'password'     => Hash::make($password),
                'avatar_color' => self::COLORS[array_rand(self::COLORS)],
            ]);
            // No email verification on self-hosted instances.
            $member->forceFill(['email_verified_at' => now()])->save();

            $workspace->users()->syncWithoutDetaching([
                $member->id => ['role' => $data['role']],
            ]);

            // Grant the selected per-project access immediately.
            if ($projectIds) {
                $projectRole = $data['role'] === 'admin' ? 'admin' : 'member';
                $rows = array_map(fn ($pid) => [
                    'project_id' => $pid,
                    'user_id'    => $member->id,
                    'role'       => $projectRole,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $projectIds);
                DB::table('workspace_members')->insert($rows);
            }

            $created = [
                'name'     => $member->name,
                'email'    => $member->email,
                'password' => $password,
            ];
        });

        broadcast(new WorkspaceMembersChanged($workspace->id, 'member_added'))->toOthers();

        return back()
            ->with('success', "{$created['name']} added.")
            ->with('createdCred', $created);
    }

    public function revoke(Request $request, int $invitation): RedirectResponse
    {
        $user      = $request->user();
        $workspace = $user->currentWorkspace;

        abort_unless($workspace && $workspace->owner_id === $user->id, 403);

        $this->invitations->revoke($workspace, $invitation, $user->id);

        broadcast(new WorkspaceMembersChanged($workspace->id, 'invitation_revoked'))->toOthers();

        return back()->with('success', 'Invitation revoked.');
    }

    public function updateRole(Request $request, int $member): RedirectResponse
    {
        $user      = $request->user();
        $workspace = $user->currentWorkspace;

        abort_unless($workspace && $workspace->owner_id === $user->id, 403);

        $data = $request->validate(['role' => 'required|in:admin,member,viewer']);

        $workspace->users()->updateExistingPivot($member, ['role' => $data['role']]);

        broadcast(new WorkspaceMembersChanged($workspace->id, 'role_updated', $member))->toOthers();

        return back()->with('success', 'Role updated.');
    }

    public function remove(Request $request, int $member): RedirectResponse
    {
        $user      = $request->user();
        $workspace = $user->currentWorkspace;

        abort_unless($workspace && $workspace->owner_id === $user->id, 403);
        abort_if($member === $user->id, 403, 'You cannot remove yourself.');

        $workspace->users()->detach($member);

        // Strip them off every project in this workspace too, so they don't keep
        // ghost access via the per-project pivot.
        DB::table('workspace_members')
            ->whereIn('project_id', $workspace->projects()->pluck('id'))
            ->where('user_id', $member)
            ->delete();

        User::where('id', $member)
            ->where('current_workspace_id', $workspace->id)
            ->update(['current_workspace_id' => null]);

        broadcast(new WorkspaceMembersChanged($workspace->id, 'member_removed', $member))->toOthers();

        return back()->with('success', 'Member removed from workspace.');
    }

    public function updateProjectAccess(Request $request, int $member): RedirectResponse
    {
        $user      = $request->user();
        $workspace = $user->currentWorkspace;

        abort_unless($workspace && $workspace->owner_id === $user->id, 403);
        abort_if($workspace->owner_id === $member, 422, "The workspace owner has access to every project.");

        $data = $request->validate([
            'projects'   => 'present|array',
            'projects.*' => 'integer',
        ]);

        $isMember = $workspace->users()->where('users.id', $member)->exists();
        abort_unless($isMember, 404);

        $wantedIds = $this->sanitizeProjectIds($workspace, $data['projects']);
        $allIds    = $workspace->projects()->pluck('id');

        $existing = DB::table('workspace_members')
            ->whereIn('project_id', $allIds)
            ->where('user_id', $member)
            ->pluck('project_id')
            ->all();

        $toAdd    = array_diff($wantedIds, $existing);
        $toRemove = array_diff($existing, $wantedIds);

        if ($toRemove) {
            DB::table('workspace_members')
                ->whereIn('project_id', $toRemove)
                ->where('user_id', $member)
                ->delete();
        }

        if ($toAdd) {
            $memberRow = $workspace->users()->where('users.id', $member)->first();
            $projectRole = ($memberRow?->pivot->role === 'admin') ? 'admin' : 'member';

            $rows = array_map(fn ($pid) => [
                'project_id' => $pid,
                'user_id'    => $member,
                'role'       => $projectRole,
                'created_at' => now(),
                'updated_at' => now(),
            ], array_values($toAdd));

            DB::table('workspace_members')->insert($rows);
        }

        broadcast(new MemberProjectAccessUpdated(
            $workspace->id,
            $member,
            $wantedIds,
        ))->toOthers();

        return back()->with('success', 'Project access updated.');
    }

    public function updateInvitationProjects(Request $request, int $invitation): RedirectResponse
    {
        $user      = $request->user();
        $workspace = $user->currentWorkspace;

        abort_unless($workspace && $workspace->owner_id === $user->id, 403);

        $data = $request->validate([
            'projects'   => 'present|array',
            'projects.*' => 'integer',
        ]);

        $invite = WorkspaceInvitation::where('id', $invitation)
            ->where('workspace_id', $workspace->id)
            ->firstOrFail();

        $invite->projects = $this->sanitizeProjectIds($workspace, $data['projects']);
        $invite->save();

        broadcast(new InvitationProjectAccessUpdated(
            $workspace->id,
            $invite->id,
            $invite->projects ?? [],
        ))->toOthers();

        return back()->with('success', 'Invitation updated.');
    }

    /**
     * Readable, unambiguous password (no 0/O/1/l/I) — mirrors the self-hosted
     * account generation in the prototype (genMemberPassword).
     */
    private function generatePassword(int $len = 14): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $max   = strlen($chars) - 1;
        $out   = '';
        for ($i = 0; $i < $len; $i++) {
            $out .= $chars[random_int(0, $max)];
        }

        return $out;
    }

    /**
     * Restrict the incoming list to project IDs that actually belong to this workspace,
     * and deduplicate. Null/empty stays the way it came in.
     */
    private function sanitizeProjectIds(Workspace $workspace, ?array $ids): ?array
    {
        if ($ids === null) {
            return null;
        }

        $valid = $workspace->projects()->pluck('id')->all();

        return array_values(array_unique(array_intersect(
            array_map('intval', $ids),
            $valid,
        )));
    }
}
