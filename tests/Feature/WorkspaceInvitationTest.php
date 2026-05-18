<?php

namespace Tests\Feature;

use App\Mail\WorkspaceInvitationMail;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WorkspaceInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function makeOwnerWithWorkspace(): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::create([
            'name'     => 'Acme',
            'owner_id' => $owner->id,
            'color'    => '#4f46e5',
        ]);
        $owner->update(['current_workspace_id' => $workspace->id]);
        return [$owner->fresh(), $workspace];
    }

    public function test_owner_can_invite_a_new_email(): void
    {
        [$owner, $workspace] = $this->makeOwnerWithWorkspace();

        $response = $this->actingAs($owner)->post('/settings/members/invite', [
            'email' => 'newbie@example.com',
            'role'  => 'member',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('workspace_invitations', [
            'workspace_id' => $workspace->id,
            'email'        => 'newbie@example.com',
            'role'         => 'member',
            'invited_by'   => $owner->id,
        ]);
    }

    public function test_invite_rejects_duplicate_pending_email(): void
    {
        [$owner, $workspace] = $this->makeOwnerWithWorkspace();

        WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email'        => 'dup@example.com',
            'role'         => 'member',
            'invited_by'   => $owner->id,
        ]);

        $response = $this->actingAs($owner)->post('/settings/members/invite', [
            'email' => 'dup@example.com',
            'role'  => 'admin',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame(1, WorkspaceInvitation::where('email', 'dup@example.com')->count());
    }

    public function test_invite_rejects_existing_workspace_member(): void
    {
        [$owner, $workspace] = $this->makeOwnerWithWorkspace();

        $member = User::factory()->create(['email' => 'member@example.com']);
        $workspace->users()->attach($member->id, ['role' => 'member']);

        $response = $this->actingAs($owner)->post('/settings/members/invite', [
            'email' => 'member@example.com',
            'role'  => 'member',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('workspace_invitations', [
            'workspace_id' => $workspace->id,
            'email'        => 'member@example.com',
        ]);
    }

    public function test_invite_validates_email_and_role(): void
    {
        [$owner] = $this->makeOwnerWithWorkspace();

        $this->actingAs($owner)
            ->post('/settings/members/invite', ['email' => 'not-an-email', 'role' => 'member'])
            ->assertSessionHasErrors('email');

        $this->actingAs($owner)
            ->post('/settings/members/invite', ['email' => 'ok@example.com', 'role' => 'pirate'])
            ->assertSessionHasErrors('role');
    }

    public function test_non_owner_cannot_invite(): void
    {
        [$owner, $workspace] = $this->makeOwnerWithWorkspace();

        $intruder = User::factory()->create(['current_workspace_id' => $workspace->id]);
        $workspace->users()->attach($intruder->id, ['role' => 'member']);

        $response = $this->actingAs($intruder)->post('/settings/members/invite', [
            'email' => 'newbie@example.com',
            'role'  => 'member',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('workspace_invitations', ['email' => 'newbie@example.com']);
    }

    public function test_guest_cannot_invite(): void
    {
        $this->post('/settings/members/invite', [
            'email' => 'newbie@example.com',
            'role'  => 'member',
        ])->assertRedirect('/login');
    }

    public function test_owner_can_revoke_invitation(): void
    {
        [$owner, $workspace] = $this->makeOwnerWithWorkspace();

        $invitation = WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email'        => 'bye@example.com',
            'role'         => 'member',
            'invited_by'   => $owner->id,
        ]);

        $response = $this->actingAs($owner)
            ->delete('/settings/members/invitations/' . $invitation->id);

        $response->assertRedirect();
        $this->assertDatabaseMissing('workspace_invitations', ['id' => $invitation->id]);
    }

    public function test_owner_cannot_revoke_invitation_from_another_workspace(): void
    {
        [$owner] = $this->makeOwnerWithWorkspace();

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

        $this->actingAs($owner)
            ->delete('/settings/members/invitations/' . $invitation->id)
            ->assertRedirect();

        $this->assertDatabaseHas('workspace_invitations', ['id' => $invitation->id]);
    }

    public function test_invite_dispatches_email_with_token(): void
    {
        [$owner, $workspace] = $this->makeOwnerWithWorkspace();

        $this->actingAs($owner)->post('/settings/members/invite', [
            'email' => 'newbie@example.com',
            'role'  => 'member',
        ])->assertRedirect();

        $invitation = WorkspaceInvitation::where('email', 'newbie@example.com')->first();
        $this->assertNotNull($invitation);
        $this->assertNotNull($invitation->token);
        $this->assertNotNull($invitation->expires_at);
        $this->assertTrue($invitation->expires_at->isFuture());

        Mail::assertSent(WorkspaceInvitationMail::class, function (WorkspaceInvitationMail $mail) use ($invitation) {
            return $mail->hasTo('newbie@example.com')
                && $mail->invitation->id === $invitation->id;
        });
    }

    public function test_accept_returns_404_like_redirect_for_invalid_token(): void
    {
        $this->get('/invitations/this-token-does-not-exist')
            ->assertRedirect(route('login'));
    }

    public function test_accept_rejects_expired_invitation(): void
    {
        [$owner, $workspace] = $this->makeOwnerWithWorkspace();

        $invitation = WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email'        => 'late@example.com',
            'role'         => 'member',
            'invited_by'   => $owner->id,
            'token'        => 'expired-token-xyz',
            'expires_at'   => Carbon::now()->subDay(),
        ]);

        $this->get('/invitations/expired-token-xyz')
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('workspace_invitations', ['id' => $invitation->id]);
    }

    public function test_accept_logs_in_authenticated_user_with_matching_email(): void
    {
        [$owner, $workspace] = $this->makeOwnerWithWorkspace();

        $invitee = User::factory()->create(['email' => 'joiner@example.com']);
        $invitation = WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email'        => 'joiner@example.com',
            'role'         => 'admin',
            'invited_by'   => $owner->id,
            'token'        => 'happy-token',
            'expires_at'   => Carbon::now()->addDay(),
        ]);

        $this->actingAs($invitee)
            ->get('/invitations/happy-token')
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseMissing('workspace_invitations', ['id' => $invitation->id]);
        $this->assertTrue($workspace->fresh()->users()->where('users.id', $invitee->id)->exists());
        $this->assertSame($workspace->id, $invitee->fresh()->current_workspace_id);

        $pivotRole = $workspace->users()->where('users.id', $invitee->id)->first()->pivot->role;
        $this->assertSame('admin', $pivotRole);
    }

    public function test_accept_refuses_authenticated_user_whose_email_does_not_match(): void
    {
        [$owner, $workspace] = $this->makeOwnerWithWorkspace();

        $other = User::factory()->create(['email' => 'someone-else@example.com']);
        $invitation = WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email'        => 'intended@example.com',
            'role'         => 'member',
            'invited_by'   => $owner->id,
            'token'        => 'mismatch-token',
            'expires_at'   => Carbon::now()->addDay(),
        ]);

        $this->actingAs($other)
            ->get('/invitations/mismatch-token')
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('workspace_invitations', ['id' => $invitation->id]);
        $this->assertFalse($workspace->fresh()->users()->where('users.id', $other->id)->exists());
    }

    public function test_accept_as_guest_with_existing_account_redirects_to_login_and_stores_token(): void
    {
        [$owner, $workspace] = $this->makeOwnerWithWorkspace();

        User::factory()->create(['email' => 'has-account@example.com']);
        WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email'        => 'has-account@example.com',
            'role'         => 'member',
            'invited_by'   => $owner->id,
            'token'        => 'login-flow-token',
            'expires_at'   => Carbon::now()->addDay(),
        ]);

        $this->get('/invitations/login-flow-token')
            ->assertRedirect(route('login'))
            ->assertSessionHas('pending_invitation_token', 'login-flow-token');
    }

    public function test_accept_as_guest_without_account_redirects_to_register_prefilled(): void
    {
        [$owner, $workspace] = $this->makeOwnerWithWorkspace();

        WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email'        => 'new-person@example.com',
            'role'         => 'member',
            'invited_by'   => $owner->id,
            'token'        => 'register-flow-token',
            'expires_at'   => Carbon::now()->addDay(),
        ]);

        $this->get('/invitations/register-flow-token')
            ->assertRedirect(route('register', ['email' => 'new-person@example.com']))
            ->assertSessionHas('pending_invitation_token', 'register-flow-token');
    }

    public function test_login_with_pending_token_finalises_invitation(): void
    {
        [$owner, $workspace] = $this->makeOwnerWithWorkspace();

        $invitee = User::factory()->create([
            'email'    => 'login-and-join@example.com',
            'password' => bcrypt('secret-pass-123'),
        ]);
        WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email'        => 'login-and-join@example.com',
            'role'         => 'member',
            'invited_by'   => $owner->id,
            'token'        => 'login-finalise-token',
            'expires_at'   => Carbon::now()->addDay(),
        ]);

        // Step 1: hit the accept link as a guest (stashes the token in session).
        $this->get('/invitations/login-finalise-token')->assertRedirect(route('login'));

        // Step 2: log in — should round-trip through invitations.accept and land on dashboard.
        $this->post('/login', [
            'email'    => 'login-and-join@example.com',
            'password' => 'secret-pass-123',
        ])
            ->assertRedirect(route('invitations.accept', ['token' => 'login-finalise-token']));

        // Step 3: follow the accept redirect now that we're authenticated.
        $this->actingAs($invitee->fresh())
            ->get('/invitations/login-finalise-token')
            ->assertRedirect(route('dashboard'));

        $this->assertTrue($workspace->fresh()->users()->where('users.id', $invitee->id)->exists());
        $this->assertDatabaseMissing('workspace_invitations', ['token' => 'login-finalise-token']);
    }

    public function test_register_with_pending_token_creates_account_and_finalises_invitation(): void
    {
        [$owner, $workspace] = $this->makeOwnerWithWorkspace();

        WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email'        => 'fresh-signup@example.com',
            'role'         => 'admin',
            'invited_by'   => $owner->id,
            'token'        => 'register-finalise-token',
            'expires_at'   => Carbon::now()->addDay(),
        ]);

        // Step 1: visit accept link, stash token.
        $this->get('/invitations/register-finalise-token');

        // Step 2: register. Without followingRedirects we should get an invitations.accept redirect.
        $this->post('/register', [
            'name'                  => 'Fresh Signup',
            'email'                 => 'fresh-signup@example.com',
            'password'              => 'TestPass!2026',
            'password_confirmation' => 'TestPass!2026',
        ])->assertRedirect(route('invitations.accept', ['token' => 'register-finalise-token']));

        $newUser = User::where('email', 'fresh-signup@example.com')->first();
        $this->assertNotNull($newUser);

        // Step 3: visit the accept link as the newly-authenticated user — finalises join.
        $this->actingAs($newUser)
            ->get('/invitations/register-finalise-token')
            ->assertRedirect(route('dashboard'));

        $this->assertTrue($workspace->fresh()->users()->where('users.id', $newUser->id)->exists());
        $this->assertDatabaseMissing('workspace_invitations', ['token' => 'register-finalise-token']);
    }

    public function test_invitations_are_listed_on_members_page(): void
    {
        [$owner, $workspace] = $this->makeOwnerWithWorkspace();

        WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email'        => 'pending@example.com',
            'role'         => 'admin',
            'invited_by'   => $owner->id,
        ]);

        $response = $this->actingAs($owner)->get('/members');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('WorkspaceMembers')
            ->where('pending.0.email', 'pending@example.com')
            ->where('pending.0.role', 'Admin')
        );
    }

    public function test_accept_adds_user_to_every_existing_project_in_workspace(): void
    {
        [$owner, $workspace] = $this->makeOwnerWithWorkspace();

        $projectA = \App\Models\Project::create([
            'name' => 'Alpha', 'key' => 'ALP', 'color' => '#4f46e5',
            'owner_id' => $owner->id, 'workspace_id' => $workspace->id,
        ]);
        $projectB = \App\Models\Project::create([
            'name' => 'Bravo', 'key' => 'BRV', 'color' => '#16a34a',
            'owner_id' => $owner->id, 'workspace_id' => $workspace->id,
        ]);

        $invitee = User::factory()->create(['email' => 'joiner@example.com']);
        WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email'        => 'joiner@example.com',
            'role'         => 'member',
            'invited_by'   => $owner->id,
            'token'        => 'membership-token',
            'expires_at'   => Carbon::now()->addDay(),
        ]);

        $this->actingAs($invitee)->get('/invitations/membership-token');

        $this->assertDatabaseHas('workspace_members', [
            'project_id' => $projectA->id,
            'user_id'    => $invitee->id,
            'role'       => 'member',
        ]);
        $this->assertDatabaseHas('workspace_members', [
            'project_id' => $projectB->id,
            'user_id'    => $invitee->id,
            'role'       => 'member',
        ]);

        $this->assertTrue($projectA->fresh()->members()->where('users.id', $invitee->id)->exists());
        $this->assertTrue($projectB->fresh()->members()->where('users.id', $invitee->id)->exists());
    }

    public function test_accept_with_admin_role_attaches_as_admin_on_projects(): void
    {
        [$owner, $workspace] = $this->makeOwnerWithWorkspace();

        $project = \App\Models\Project::create([
            'name' => 'Alpha', 'key' => 'ALP', 'color' => '#4f46e5',
            'owner_id' => $owner->id, 'workspace_id' => $workspace->id,
        ]);

        $invitee = User::factory()->create(['email' => 'admin-joiner@example.com']);
        WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email'        => 'admin-joiner@example.com',
            'role'         => 'admin',
            'invited_by'   => $owner->id,
            'token'        => 'admin-membership-token',
            'expires_at'   => Carbon::now()->addDay(),
        ]);

        $this->actingAs($invitee)->get('/invitations/admin-membership-token');

        $this->assertDatabaseHas('workspace_members', [
            'project_id' => $project->id,
            'user_id'    => $invitee->id,
            'role'       => 'admin',
        ]);
    }
}
