<?php

namespace Tests\Feature;

use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Covers the per-project settings page reached from the sidebar gear:
 *   • viewing the settings page (members only)
 *   • renaming / recoloring the project   (owner or workspace admin)
 *   • adding / removing members            (owner only)
 *   • deleting the project + last-project guard
 */
class ProjectSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Workspace $workspace;
    private Project $project;
    private Project $secondProject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->workspace = Workspace::create([
            'name'     => 'Acme',
            'owner_id' => $this->owner->id,
            'color'    => '#4f46e5',
        ]);
        $this->workspace->users()->attach($this->owner->id, ['role' => 'owner']);
        $this->owner->update(['current_workspace_id' => $this->workspace->id]);

        $this->project = $this->makeProject('Mobile App', 'MOB');
        $this->project->members()->attach($this->owner->id, ['role' => 'owner']);

        // A second project keeps the "last project" delete guard out of the way
        // for the common-case tests.
        $this->secondProject = $this->makeProject('Web App', 'WEB');
    }

    private function makeProject(string $name, string $key): Project
    {
        return Project::create([
            'name'         => $name,
            'key'          => $key,
            'color'        => '#4f46e5',
            'owner_id'     => $this->owner->id,
            'workspace_id' => $this->workspace->id,
        ]);
    }

    /** A workspace user with the given role, not (yet) attached to the project. */
    private function makeWorkspaceUser(string $role = 'member'): User
    {
        $user = User::factory()->create();
        $this->workspace->users()->attach($user->id, ['role' => $role]);
        $user->update(['current_workspace_id' => $this->workspace->id]);

        return $user;
    }

    // ── Viewing ────────────────────────────────────────────────────────────────

    public function test_owner_can_view_the_settings_page(): void
    {
        $this->actingAs($this->owner)
            ->get(route('projects.settings', $this->project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Settings')
                ->where('project.name', 'Mobile App')
                ->where('isOwner', true)
                ->where('canManage', true)
                ->where('projectCount', 2)
                ->has('members', 1)
                ->has('available'));
    }

    public function test_non_member_cannot_view_the_settings_page(): void
    {
        $outsider = User::factory()->create();
        $other = Workspace::create(['name' => 'Other', 'owner_id' => $outsider->id, 'color' => '#000000']);
        $other->users()->attach($outsider->id, ['role' => 'owner']);
        $outsider->update(['current_workspace_id' => $other->id]);

        $this->actingAs($outsider)
            ->get(route('projects.settings', $this->project))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('projects.settings', $this->project))
            ->assertRedirect(route('login'));
    }

    // ── Rename / recolor ────────────────────────────────────────────────────────

    public function test_owner_can_rename_the_project(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('projects.update', $this->project), ['name' => 'Mobile App v2'])
            ->assertRedirect();

        $this->assertSame('Mobile App v2', $this->project->fresh()->name);
        $this->assertDatabaseHas('audit_logs', [
            'project_id' => $this->project->id,
            'action'     => 'project.renamed',
        ]);
    }

    public function test_owner_can_recolor_the_project(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('projects.update', $this->project), ['color' => '#16a34a'])
            ->assertRedirect();

        $this->assertSame('#16a34a', $this->project->fresh()->color);
    }

    public function test_rename_rejects_a_duplicate_name_in_the_workspace(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('projects.update', $this->project), ['name' => 'Web App'])
            ->assertSessionHasErrors('name');

        $this->assertSame('Mobile App', $this->project->fresh()->name);
    }

    public function test_keeping_the_same_name_is_allowed(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('projects.update', $this->project), ['name' => 'Mobile App'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Mobile App', $this->project->fresh()->name);
    }

    public function test_workspace_admin_can_update_a_project_they_do_not_own(): void
    {
        $admin = $this->makeWorkspaceUser('admin');

        $this->actingAs($admin)
            ->patch(route('projects.update', $this->project), ['name' => 'Renamed by admin'])
            ->assertRedirect();

        $this->assertSame('Renamed by admin', $this->project->fresh()->name);
    }

    public function test_plain_member_cannot_update_a_project(): void
    {
        $member = $this->makeWorkspaceUser('member');
        $this->project->members()->attach($member->id, ['role' => 'member']);

        $this->actingAs($member)
            ->patch(route('projects.update', $this->project), ['name' => 'Hacked'])
            ->assertForbidden();

        $this->assertSame('Mobile App', $this->project->fresh()->name);
    }

    public function test_guest_cannot_update_a_project(): void
    {
        $this->patch(route('projects.update', $this->project), ['name' => 'Nope'])
            ->assertRedirect(route('login'));

        $this->assertSame('Mobile App', $this->project->fresh()->name);
    }

    // ── Members ─────────────────────────────────────────────────────────────────

    public function test_owner_can_add_a_workspace_user_to_the_project(): void
    {
        $bob = $this->makeWorkspaceUser('member');

        $this->actingAs($this->owner)
            ->post(route('projects.members.invite', $this->project), [
                'email' => $bob->email,
                'role'  => 'member',
            ])
            ->assertRedirect();

        $this->assertTrue($this->project->members()->where('users.id', $bob->id)->exists());
    }

    public function test_owner_can_remove_a_member(): void
    {
        $bob = $this->makeWorkspaceUser('member');
        $this->project->members()->attach($bob->id, ['role' => 'member']);

        $this->actingAs($this->owner)
            ->delete(route('projects.members.remove', [$this->project, $bob]))
            ->assertRedirect();

        $this->assertFalse($this->project->members()->where('users.id', $bob->id)->exists());
    }

    public function test_settings_page_lists_available_workspace_users_to_add(): void
    {
        $bob = $this->makeWorkspaceUser('member');

        $this->actingAs($this->owner)
            ->get(route('projects.settings', $this->project))
            ->assertInertia(fn (Assert $page) => $page
                ->where('available', fn ($available) =>
                    collect($available)->contains(fn ($u) => $u['id'] === $bob->id)));
    }

    // ── Delete ──────────────────────────────────────────────────────────────────

    public function test_owner_can_delete_a_project_and_it_cascades(): void
    {
        $column = BoardColumn::create([
            'project_id' => $this->project->id, 'name' => 'Todo', 'color' => '#94948c', 'position' => 0,
        ]);

        $this->actingAs($this->owner)
            ->delete(route('projects.destroy', $this->project))
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseMissing('projects', ['id' => $this->project->id]);
        $this->assertDatabaseMissing('board_columns', ['id' => $column->id]);
        $this->assertDatabaseMissing('workspace_members', ['project_id' => $this->project->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'project.deleted']);
    }

    public function test_cannot_delete_the_last_project_in_a_workspace(): void
    {
        // Drop the spare project so only one remains.
        $this->secondProject->delete();

        $this->actingAs($this->owner)
            ->delete(route('projects.destroy', $this->project))
            ->assertSessionHasErrors('project');

        $this->assertDatabaseHas('projects', ['id' => $this->project->id]);
    }

    public function test_workspace_admin_can_delete_a_project(): void
    {
        $admin = $this->makeWorkspaceUser('admin');

        $this->actingAs($admin)
            ->delete(route('projects.destroy', $this->project))
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseMissing('projects', ['id' => $this->project->id]);
    }

    public function test_plain_member_cannot_delete_a_project(): void
    {
        $member = $this->makeWorkspaceUser('member');
        $this->project->members()->attach($member->id, ['role' => 'member']);

        $this->actingAs($member)
            ->delete(route('projects.destroy', $this->project))
            ->assertForbidden();

        $this->assertDatabaseHas('projects', ['id' => $this->project->id]);
    }

    public function test_guest_cannot_delete_a_project(): void
    {
        $this->delete(route('projects.destroy', $this->project))
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('projects', ['id' => $this->project->id]);
    }
}
