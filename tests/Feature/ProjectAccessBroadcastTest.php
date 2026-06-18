<?php

namespace Tests\Feature;

use App\Events\MemberProjectAccessUpdated;
use App\Events\ProjectMembersChanged;
use App\Events\ProjectUpdated;
use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Covers the realtime + data side effects of project access changes:
 *   • revoking access unassigns the member from every task in the project
 *   • the removed/invited user gets a MemberProjectAccessUpdated on their channel
 *     so their sidebar updates live
 *   • deleting a project notifies everyone who could see it
 *   • renaming/recoloring a project broadcasts ProjectUpdated
 */
class ProjectAccessBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $bob;
    private Workspace $workspace;
    private Project $project;
    private Project $secondProject;
    private BoardColumn $column;
    private Task $task;

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

        $this->project       = $this->makeProject('Mobile App', 'MOB');
        $this->secondProject = $this->makeProject('Web App', 'WEB');

        // Bob is a workspace member with project access, assigned to a task.
        $this->bob = $this->makeWorkspaceUser('member');
        $this->project->members()->attach($this->bob->id, ['role' => 'member']);

        $this->column = BoardColumn::create([
            'project_id' => $this->project->id, 'name' => 'Todo', 'color' => '#94948c', 'position' => 0,
        ]);
        $this->task = $this->makeTask($this->project, 'MOB-1');
        $this->task->update(['assignee_id' => $this->bob->id]);
        $this->task->assignees()->attach($this->bob->id);
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

    private function makeWorkspaceUser(string $role = 'member'): User
    {
        $user = User::factory()->create();
        $this->workspace->users()->attach($user->id, ['role' => $role]);
        $user->update(['current_workspace_id' => $this->workspace->id]);

        return $user;
    }

    private function makeTask(Project $project, string $key): Task
    {
        return Task::create([
            'key'        => $key,
            'title'      => 'A task',
            'project_id' => $project->id,
            'created_by' => $this->owner->id,
            'priority'   => 'med',
        ]);
    }

    // ── Removal unassigns ──────────────────────────────────────────────────────

    public function test_removing_a_member_unassigns_them_from_all_project_tasks(): void
    {
        $this->actingAs($this->owner)
            ->delete(route('projects.members.remove', [$this->project, $this->bob]))
            ->assertRedirect();

        $this->assertNull($this->task->fresh()->assignee_id);
        $this->assertDatabaseMissing('task_assignees', [
            'task_id' => $this->task->id, 'user_id' => $this->bob->id,
        ]);
        $this->assertFalse($this->project->members()->where('users.id', $this->bob->id)->exists());
    }

    public function test_removing_a_member_leaves_assignments_in_other_projects_intact(): void
    {
        // Bob is also assigned to a task in a different project.
        $this->secondProject->members()->attach($this->bob->id, ['role' => 'member']);
        $otherTask = $this->makeTask($this->secondProject, 'WEB-1');
        $otherTask->update(['assignee_id' => $this->bob->id]);
        $otherTask->assignees()->attach($this->bob->id);

        $this->actingAs($this->owner)
            ->delete(route('projects.members.remove', [$this->project, $this->bob]));

        // Removed from the first project's task, untouched in the second.
        $this->assertNull($this->task->fresh()->assignee_id);
        $this->assertSame($this->bob->id, $otherTask->fresh()->assignee_id);
        $this->assertDatabaseHas('task_assignees', [
            'task_id' => $otherTask->id, 'user_id' => $this->bob->id,
        ]);
    }

    // ── Broadcasts ──────────────────────────────────────────────────────────────

    public function test_removing_a_member_broadcasts_access_update_to_that_user(): void
    {
        Event::fake([MemberProjectAccessUpdated::class, ProjectMembersChanged::class]);

        $this->actingAs($this->owner)
            ->delete(route('projects.members.remove', [$this->project, $this->bob]));

        Event::assertDispatched(
            MemberProjectAccessUpdated::class,
            fn (MemberProjectAccessUpdated $e) => $e->memberId === $this->bob->id
                && ! in_array($this->project->id, $e->projectIds, true),
        );
        Event::assertDispatched(
            ProjectMembersChanged::class,
            fn (ProjectMembersChanged $e) => $e->event === 'member_removed' && $e->memberId === $this->bob->id,
        );
    }

    public function test_inviting_a_member_broadcasts_access_update_to_the_invitee(): void
    {
        $carol = $this->makeWorkspaceUser('member');

        Event::fake([MemberProjectAccessUpdated::class, ProjectMembersChanged::class]);

        $this->actingAs($this->owner)
            ->post(route('projects.members.invite', $this->project), [
                'email' => $carol->email,
                'role'  => 'member',
            ])
            ->assertRedirect();

        Event::assertDispatched(
            MemberProjectAccessUpdated::class,
            fn (MemberProjectAccessUpdated $e) => $e->memberId === $carol->id
                && in_array($this->project->id, $e->projectIds, true),
        );
    }

    public function test_deleting_a_project_broadcasts_access_update_to_members(): void
    {
        Event::fake([MemberProjectAccessUpdated::class]);

        $this->actingAs($this->owner)
            ->delete(route('projects.destroy', $this->project))
            ->assertRedirect(route('dashboard'));

        // Bob (a member) is told his access set no longer includes the project.
        Event::assertDispatched(
            MemberProjectAccessUpdated::class,
            fn (MemberProjectAccessUpdated $e) => $e->memberId === $this->bob->id
                && ! in_array($this->project->id, $e->projectIds, true),
        );
        // The owner is notified too.
        Event::assertDispatched(
            MemberProjectAccessUpdated::class,
            fn (MemberProjectAccessUpdated $e) => $e->memberId === $this->owner->id,
        );
    }

    public function test_recoloring_a_project_broadcasts_project_updated(): void
    {
        Event::fake([ProjectUpdated::class]);

        $this->actingAs($this->owner)
            ->patch(route('projects.update', $this->project), ['color' => '#16a34a'])
            ->assertRedirect();

        Event::assertDispatched(
            ProjectUpdated::class,
            fn (ProjectUpdated $e) => $e->project->is($this->project),
        );
    }
}
