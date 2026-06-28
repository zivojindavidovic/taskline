<?php

namespace Tests\Feature;

use App\Events\SprintUpdated;
use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Covers the workspace-wide Sprints page (sidebar "Sprints" tab): the Inertia
 * index, project scoping, live progress / completed breakdown, plus the edit
 * and delete endpoints it drives.
 */
class SprintsPageTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Workspace $workspace;
    private Project $project;
    private BoardColumn $todo;
    private BoardColumn $done;

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

        $this->project = Project::create([
            'name'         => 'Mobile App v3',
            'key'          => 'MOB',
            'color'        => '#4f46e5',
            'owner_id'     => $this->owner->id,
            'workspace_id' => $this->workspace->id,
        ]);
        $this->project->members()->attach($this->owner->id, ['role' => 'owner']);

        $this->todo = BoardColumn::create(['project_id' => $this->project->id, 'name' => 'Todo', 'color' => '#94948c', 'position' => 0]);
        $this->done = BoardColumn::create(['project_id' => $this->project->id, 'name' => 'Done', 'color' => '#16a34a', 'position' => 1]);
    }

    private function makeTask(Sprint $sprint, BoardColumn $column, string $key, array $attrs = []): Task
    {
        return Task::create(array_merge([
            'key'             => $key,
            'title'           => $key,
            'project_id'      => $this->project->id,
            'sprint_id'       => $sprint->id,
            'board_column_id' => $column->id,
            'created_by'      => $this->owner->id,
            'priority'        => 'med',
        ], $attrs));
    }

    /** A workspace member (role: member) who can see the project but not manage sprints. */
    private function plainMember(): User
    {
        $member = User::factory()->create();
        $this->workspace->users()->attach($member->id, ['role' => 'member']);
        $member->update(['current_workspace_id' => $this->workspace->id]);
        $this->project->members()->attach($member->id, ['role' => 'member']);

        return $member;
    }

    // ── Index ────────────────────────────────────────────────────────────────

    public function test_index_renders_sprints_page_with_props(): void
    {
        Sprint::create(['project_id' => $this->project->id, 'name' => 'Sprint 1', 'status' => 'active']);
        Sprint::create(['project_id' => $this->project->id, 'name' => 'Sprint 2', 'status' => 'planned']);

        $this->actingAs($this->owner)
            ->get(route('sprints.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Sprints')
                ->has('sprints', 2)
                ->has('projects', 1)
                ->where('canManage', true)
            );
    }

    public function test_guest_cannot_view_sprints(): void
    {
        $this->get(route('sprints.index'))->assertRedirect(route('login'));
    }

    public function test_index_excludes_projects_from_other_workspaces(): void
    {
        Sprint::create(['project_id' => $this->project->id, 'name' => 'Mine', 'status' => 'active']);

        // A sprint in a workspace the owner is not part of must not appear.
        $stranger = User::factory()->create();
        $otherWs  = Workspace::create(['name' => 'Other', 'owner_id' => $stranger->id, 'color' => '#000000']);
        $otherProject = Project::create([
            'name' => 'Secret', 'key' => 'SEC', 'color' => '#000000',
            'owner_id' => $stranger->id, 'workspace_id' => $otherWs->id,
        ]);
        Sprint::create(['project_id' => $otherProject->id, 'name' => 'Hidden', 'status' => 'active']);

        $this->actingAs($this->owner)
            ->get(route('sprints.index'))
            ->assertInertia(fn ($page) => $page
                ->has('sprints', 1)
                ->where('sprints.0.name', 'Mine')
                ->has('projects', 1)
            );
    }

    public function test_member_sees_sprints_but_cannot_manage(): void
    {
        Sprint::create(['project_id' => $this->project->id, 'name' => 'Sprint 1', 'status' => 'active']);

        $this->actingAs($this->plainMember())
            ->get(route('sprints.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Sprints')
                ->has('sprints', 1)
                ->where('canManage', false)
            );
    }

    // ── Progress + completed breakdown ─────────────────────────────────────────

    public function test_open_sprint_carries_live_progress(): void
    {
        $sprint = Sprint::create(['project_id' => $this->project->id, 'name' => 'Sprint 1', 'status' => 'active']);
        $this->makeTask($sprint, $this->done, 'MOB-1');                 // done (Done column)
        $this->makeTask($sprint, $this->todo, 'MOB-2', ['completed' => true]); // done (completed flag)
        $this->makeTask($sprint, $this->todo, 'MOB-3');                 // not done

        $this->actingAs($this->owner)
            ->get(route('sprints.index'))
            ->assertInertia(fn ($page) => $page
                ->where('sprints.0.progress.done', 2)
                ->where('sprints.0.progress.total', 3)
                ->where('sprints.0.progress.pct', 67)
                ->where('sprints.0.summary', null)
            );
    }

    public function test_completing_a_sprint_moves_unfinished_tasks_to_backlog_and_snapshots(): void
    {
        $sprint = Sprint::create(['project_id' => $this->project->id, 'name' => 'Sprint 1', 'status' => 'active']);
        $shipped    = $this->makeTask($sprint, $this->done, 'MOB-1');            // in Done column → done
        $incomplete = $this->makeTask($sprint, $this->todo, 'MOB-2');            // not done → rolls over
        $incomplete2 = $this->makeTask($sprint, $this->todo, 'MOB-3');           // not done → rolls over

        $this->actingAs($this->owner)
            ->post(route('sprints.complete', $sprint))
            ->assertRedirect();

        // Unfinished tasks fall back to the backlog; the shipped one stays.
        $this->assertNull($incomplete->fresh()->sprint_id);
        $this->assertNull($incomplete2->fresh()->sprint_id);
        $this->assertSame($sprint->id, $shipped->fresh()->sprint_id);

        // The breakdown is snapshotted so it survives the rollover.
        $summary = $sprint->fresh()->summary;
        $this->assertCount(1, $summary['completed']);
        $this->assertCount(2, $summary['incomplete']);
        $this->assertSame(33, $summary['completion_rate']);
    }

    public function test_completed_sprint_breakdown_appears_on_the_page(): void
    {
        $sprint = Sprint::create(['project_id' => $this->project->id, 'name' => 'Sprint 1', 'status' => 'active']);
        $this->makeTask($sprint, $this->done, 'MOB-1'); // completed
        $this->makeTask($sprint, $this->todo, 'MOB-2'); // rolls over
        $this->makeTask($sprint, $this->todo, 'MOB-3'); // rolls over

        $this->actingAs($this->owner)->post(route('sprints.complete', $sprint));

        $this->actingAs($this->owner)
            ->get(route('sprints.index'))
            ->assertInertia(fn ($page) => $page
                ->has('sprints.0.summary.completed', 1)
                ->has('sprints.0.summary.incomplete', 2)
                ->where('sprints.0.summary.completion_rate', 33)
            );
    }

    public function test_reopening_a_sprint_clears_its_summary(): void
    {
        $sprint = Sprint::create(['project_id' => $this->project->id, 'name' => 'Sprint 1', 'status' => 'active']);
        $this->makeTask($sprint, $this->todo, 'MOB-1');

        $this->actingAs($this->owner)->post(route('sprints.complete', $sprint));
        $this->assertNotNull($sprint->fresh()->summary);

        $this->actingAs($this->owner)
            ->post(route('sprints.reopen', $sprint))
            ->assertRedirect();

        $this->assertNull($sprint->fresh()->summary);
        $this->assertSame('active', $sprint->fresh()->status);
    }

    // ── Store (goal) ───────────────────────────────────────────────────────────

    public function test_store_persists_goal(): void
    {
        $this->actingAs($this->owner)
            ->post(route('sprints.store', $this->project), [
                'name'       => 'Sprint 9',
                'start_date' => '2026-06-01',
                'end_date'   => '2026-06-15',
                'goal'       => 'Ship onboarding.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('sprints', [
            'project_id' => $this->project->id,
            'name'       => 'Sprint 9',
            'goal'       => 'Ship onboarding.',
        ]);
    }

    // ── Update ──────────────────────────────────────────────────────────────────

    public function test_manager_can_update_sprint(): void
    {
        $sprint = Sprint::create([
            'project_id' => $this->project->id, 'name' => 'Sprint 1',
            'start_date' => '2026-05-01', 'end_date' => '2026-05-14', 'status' => 'planned',
        ]);

        $this->actingAs($this->owner)
            ->patch(route('sprints.update', $sprint), [
                'name'       => 'Sprint 1 — renamed',
                'start_date' => '2026-05-05',
                'end_date'   => '2026-05-19',
                'goal'       => 'New goal',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('sprints', [
            'id'   => $sprint->id,
            'name' => 'Sprint 1 — renamed',
            'goal' => 'New goal',
        ]);
        $this->assertSame('2026-05-19', $sprint->fresh()->end_date->toDateString());
    }

    public function test_update_rejects_end_date_before_start_date(): void
    {
        $sprint = Sprint::create(['project_id' => $this->project->id, 'name' => 'Sprint 1']);

        $this->actingAs($this->owner)
            ->patch(route('sprints.update', $sprint), [
                'name'       => 'Sprint 1',
                'start_date' => '2026-05-19',
                'end_date'   => '2026-05-05',
            ])
            ->assertSessionHasErrors('end_date');
    }

    public function test_member_cannot_update_sprint(): void
    {
        $sprint = Sprint::create(['project_id' => $this->project->id, 'name' => 'Sprint 1']);

        $this->actingAs($this->plainMember())
            ->patch(route('sprints.update', $sprint), ['name' => 'Hacked'])
            ->assertForbidden();

        $this->assertDatabaseHas('sprints', ['id' => $sprint->id, 'name' => 'Sprint 1']);
    }

    // ── Destroy ──────────────────────────────────────────────────────────────────

    public function test_manager_can_delete_sprint_and_tasks_fall_back_to_backlog(): void
    {
        $sprint = Sprint::create(['project_id' => $this->project->id, 'name' => 'Sprint 1', 'status' => 'active']);
        $task = $this->makeTask($sprint, $this->todo, 'MOB-1');

        $this->actingAs($this->owner)
            ->delete(route('sprints.destroy', $sprint))
            ->assertRedirect();

        $this->assertDatabaseMissing('sprints', ['id' => $sprint->id]);
        $this->assertNull($task->fresh()->sprint_id);
        $this->assertDatabaseHas('audit_logs', [
            'project_id' => $this->project->id,
            'action'     => 'sprint.deleted',
        ]);
    }

    public function test_member_cannot_delete_sprint(): void
    {
        $sprint = Sprint::create(['project_id' => $this->project->id, 'name' => 'Sprint 1']);

        $this->actingAs($this->plainMember())
            ->delete(route('sprints.destroy', $sprint))
            ->assertForbidden();

        $this->assertDatabaseHas('sprints', ['id' => $sprint->id]);
    }

    public function test_deleting_with_delete_tasks_flag_permanently_removes_tasks_and_subtasks(): void
    {
        $sprint = Sprint::create(['project_id' => $this->project->id, 'name' => 'Sprint 1', 'status' => 'active']);
        $parent = $this->makeTask($sprint, $this->todo, 'MOB-1');
        $child  = $this->makeTask($sprint, $this->todo, 'MOB-2', ['parent_task_id' => $parent->id]);
        $other  = $this->makeTask($sprint, $this->todo, 'MOB-3');

        $this->actingAs($this->owner)
            ->delete(route('sprints.destroy', $sprint), ['delete_tasks' => true])
            ->assertRedirect();

        $this->assertDatabaseMissing('sprints', ['id' => $sprint->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $parent->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $child->id]);   // subtree cascaded
        $this->assertDatabaseMissing('tasks', ['id' => $other->id]);
    }

    public function test_deleting_a_sprint_broadcasts_a_live_update(): void
    {
        Event::fake([SprintUpdated::class]);
        $sprint = Sprint::create(['project_id' => $this->project->id, 'name' => 'Sprint 1', 'status' => 'active']);

        $this->actingAs($this->owner)->delete(route('sprints.destroy', $sprint));

        Event::assertDispatched(
            SprintUpdated::class,
            fn (SprintUpdated $e) => $e->event === 'deleted' && (int) $e->sprint->id === (int) $sprint->id,
        );
    }

    // ── Derived "in progress" status (from the date window) ─────────────────────

    public function test_started_sprint_shows_as_in_progress(): void
    {
        // Stored as 'planned', but its start date has already passed.
        Sprint::create([
            'project_id' => $this->project->id, 'name' => 'Started', 'status' => 'planned',
            'start_date' => now()->subDay()->toDateString(),
            'end_date'   => now()->addDays(10)->toDateString(),
        ]);

        $this->actingAs($this->owner)
            ->get(route('sprints.index'))
            ->assertInertia(fn ($page) => $page->where('sprints.0.status', 'active'));
    }

    public function test_future_sprint_shows_as_planned(): void
    {
        // Stored as 'active', but it doesn't start until later.
        Sprint::create([
            'project_id' => $this->project->id, 'name' => 'Future', 'status' => 'active',
            'start_date' => now()->addDays(3)->toDateString(),
        ]);

        $this->actingAs($this->owner)
            ->get(route('sprints.index'))
            ->assertInertia(fn ($page) => $page->where('sprints.0.status', 'planned'));
    }

    public function test_completed_status_is_never_overridden_by_dates(): void
    {
        Sprint::create([
            'project_id' => $this->project->id, 'name' => 'Done', 'status' => 'completed',
            'start_date' => now()->subDays(20)->toDateString(),
        ]);

        $this->actingAs($this->owner)
            ->get(route('sprints.index'))
            ->assertInertia(fn ($page) => $page->where('sprints.0.status', 'completed'));
    }
}
