<?php

namespace Tests\Unit;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Repositories\InvitationRepository;
use App\Services\InvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InvitationServiceTest extends TestCase
{
    use RefreshDatabase;

    private InvitationService $service;
    private User $owner;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->service = new InvitationService(new InvitationRepository());

        $this->owner = User::factory()->create();
        $this->workspace = Workspace::create([
            'name'     => 'Acme',
            'owner_id' => $this->owner->id,
            'color'    => '#4f46e5',
        ]);
    }

    public function test_invite_persists_invitation_and_audit_log(): void
    {
        $invitation = $this->service->invite($this->workspace, 'newbie@example.com', 'member', $this->owner->id);

        $this->assertDatabaseHas('workspace_invitations', [
            'id'           => $invitation->id,
            'workspace_id' => $this->workspace->id,
            'email'        => 'newbie@example.com',
            'role'         => 'member',
            'invited_by'   => $this->owner->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->owner->id,
            'action'  => 'workspace.invitation_sent',
        ]);
    }

    public function test_invite_normalizes_email_to_lowercase_and_trims(): void
    {
        $invitation = $this->service->invite($this->workspace, '  NEWBIE@Example.COM  ', 'member', $this->owner->id);

        $this->assertSame('newbie@example.com', $invitation->email);
    }

    public function test_invite_rejects_existing_workspace_member(): void
    {
        $member = User::factory()->create(['email' => 'member@example.com']);
        $this->workspace->users()->attach($member->id, ['role' => 'member']);

        $this->expectException(ValidationException::class);

        $this->service->invite($this->workspace, 'member@example.com', 'member', $this->owner->id);
    }

    public function test_invite_rejects_workspace_owner_email(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->invite($this->workspace, $this->owner->email, 'member', $this->owner->id);
    }

    public function test_invite_rejects_duplicate_pending_invitation(): void
    {
        $this->service->invite($this->workspace, 'duplicate@example.com', 'member', $this->owner->id);

        $this->expectException(ValidationException::class);

        $this->service->invite($this->workspace, 'duplicate@example.com', 'admin', $this->owner->id);
    }

    public function test_invite_allows_email_that_belongs_to_another_workspace_user(): void
    {
        $outsider = User::factory()->create(['email' => 'outsider@example.com']);

        $invitation = $this->service->invite($this->workspace, $outsider->email, 'member', $this->owner->id);

        $this->assertNotNull($invitation);
    }

    public function test_revoke_deletes_invitation_and_returns_true(): void
    {
        $invitation = $this->service->invite($this->workspace, 'revokeme@example.com', 'member', $this->owner->id);

        $result = $this->service->revoke($this->workspace, $invitation->id, $this->owner->id);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('workspace_invitations', ['id' => $invitation->id]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->owner->id,
            'action'  => 'workspace.invitation_revoked',
        ]);
    }

    public function test_revoke_returns_false_for_unknown_invitation(): void
    {
        $this->assertFalse($this->service->revoke($this->workspace, 99999, $this->owner->id));
    }

    public function test_revoke_returns_false_when_invitation_belongs_to_another_workspace(): void
    {
        $otherOwner = User::factory()->create();
        $otherWorkspace = Workspace::create([
            'name'     => 'Other',
            'owner_id' => $otherOwner->id,
            'color'    => '#10b981',
        ]);

        $invitation = WorkspaceInvitation::create([
            'workspace_id' => $otherWorkspace->id,
            'email'        => 'foreign@example.com',
            'role'         => 'member',
            'invited_by'   => $otherOwner->id,
        ]);

        $this->assertFalse($this->service->revoke($this->workspace, $invitation->id, $this->owner->id));
        $this->assertDatabaseHas('workspace_invitations', ['id' => $invitation->id]);
    }
}
