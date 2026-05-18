<?php

namespace App\Services;

use App\Mail\WorkspaceInvitationMail;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Repositories\InvitationRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvitationService
{
    /** Invitation lifetime in days. */
    public const EXPIRES_IN_DAYS = 7;

    public function __construct(private InvitationRepository $repository) {}

    /**
     * Invite a user to a workspace by email. Generates a unique accept token,
     * persists the invitation, and dispatches the invitation email.
     *
     * @throws ValidationException when the email already belongs to a member or has a pending invite.
     */
    public function invite(Workspace $workspace, string $email, string $role, int $inviterId): WorkspaceInvitation
    {
        $email = strtolower(trim($email));

        $existingUser = User::where('email', $email)->first();
        if ($existingUser && $workspace->users()->where('users.id', $existingUser->id)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This user is already a member of the workspace.',
            ]);
        }

        if ($existingUser && $workspace->owner_id === $existingUser->id) {
            throw ValidationException::withMessages([
                'email' => 'This user is already a member of the workspace.',
            ]);
        }

        if ($this->repository->findByWorkspaceAndEmail($workspace->id, $email)) {
            throw ValidationException::withMessages([
                'email' => 'An invitation has already been sent to this email.',
            ]);
        }

        $invitation = $this->repository->create([
            'workspace_id' => $workspace->id,
            'email'        => $email,
            'role'         => $role,
            'invited_by'   => $inviterId,
            'token'        => $this->generateUniqueToken(),
            'expires_at'   => Carbon::now()->addDays(self::EXPIRES_IN_DAYS),
        ]);

        $inviter = User::find($inviterId);
        Mail::to($email)->send(new WorkspaceInvitationMail($invitation, $workspace, $inviter));

        AuditLog::create([
            'user_id' => $inviterId,
            'action'  => 'workspace.invitation_sent',
            'meta'    => [
                'workspace_id' => $workspace->id,
                'email'        => $email,
                'role'         => $role,
            ],
        ]);

        return $invitation;
    }

    public function revoke(Workspace $workspace, int $invitationId, int $actorId): bool
    {
        $invitation = $this->repository->find($invitationId);

        if (!$invitation || $invitation->workspace_id !== $workspace->id) {
            return false;
        }

        $this->repository->delete($invitation);

        AuditLog::create([
            'user_id' => $actorId,
            'action'  => 'workspace.invitation_revoked',
            'meta'    => [
                'workspace_id' => $workspace->id,
                'email'        => $invitation->email,
            ],
        ]);

        return true;
    }

    /**
     * Accept an invitation on behalf of the given user. Attaches them to the workspace
     * (idempotent if already a member), switches their current workspace, deletes the
     * invitation, and writes an audit log.
     */
    public function accept(WorkspaceInvitation $invitation, User $user): Workspace
    {
        $workspace = $invitation->workspace;

        if (!$workspace->users()->where('users.id', $user->id)->exists() && $workspace->owner_id !== $user->id) {
            $workspace->users()->attach($user->id, ['role' => $invitation->role]);
        }

        // `workspace_members` is actually the project↔user pivot (despite the name).
        // Without these rows, Project::members() doesn't see the invitee and they
        // can't open any project in the workspace they just joined.
        $projectRole = $invitation->role === 'admin' ? 'admin' : 'member';
        $projectIds = $workspace->projects()->pluck('id');
        foreach ($projectIds as $projectId) {
            DB::table('workspace_members')->updateOrInsert(
                ['project_id' => $projectId, 'user_id' => $user->id],
                ['role' => $projectRole, 'updated_at' => now(), 'created_at' => now()],
            );
        }

        $user->update(['current_workspace_id' => $workspace->id]);

        $this->repository->delete($invitation);

        AuditLog::create([
            'user_id' => $user->id,
            'action'  => 'workspace.invitation_accepted',
            'meta'    => [
                'workspace_id' => $workspace->id,
                'email'        => $invitation->email,
            ],
        ]);

        return $workspace;
    }

    private function generateUniqueToken(): string
    {
        do {
            $token = Str::random(48);
        } while ($this->repository->findByToken($token) !== null);

        return $token;
    }
}
