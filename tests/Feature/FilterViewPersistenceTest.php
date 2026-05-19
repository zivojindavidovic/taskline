<?php

namespace Tests\Feature;

use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use App\Models\UserProjectFilter;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilterViewPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Workspace $workspace;
    private Project $project;
    private Sprint $sprint;
    private BoardColumn $column;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();

        $this->workspace = Workspace::create([
            'name'     => 'Test Workspace',
            'owner_id' => $this->owner->id,
            'color'    => '#4f46e5',
        ]);
        $this->workspace->users()->attach($this->owner->id, ['role' => 'owner']);
        $this->owner->update(['current_workspace_id' => $this->workspace->id]);

        $this->project = Project::create([
            'name'         => 'Test Project',
            'key'          => 'TST',
            'color'        => '#4f46e5',
            'owner_id'     => $this->owner->id,
            'workspace_id' => $this->workspace->id,
        ]);
        $this->project->members()->attach($this->owner->id, ['role' => 'owner']);

        $this->sprint = Sprint::create([
            'project_id' => $this->project->id,
            'name'       => 'Sprint 1',
            'status'     => 'active',
            'locked'     => false,
        ]);
        $this->column = BoardColumn::create([
            'project_id' => $this->project->id,
            'name'       => 'Todo',
            'color'      => '#94948c',
            'position'   => 0,
        ]);
    }

    private function pageProps($response): array
    {
        return $response->original->getData()['page']['props'];
    }

    // -----------------------------------------------------------------------
    // ProjectController::show — saves the rendered view to user_project_filters
    // -----------------------------------------------------------------------

    public function test_visiting_with_sprint_query_persists_view_for_that_sprint(): void
    {
        $response = $this->actingAs($this->owner)
            ->get("/projects/{$this->project->id}?sprint={$this->sprint->id}");

        $response->assertOk();

        $this->assertDatabaseHas('user_project_filters', [
            'user_id'        => $this->owner->id,
            'project_id'     => $this->project->id,
            'view_mode'      => 'active',
            'view_sprint_id' => $this->sprint->id,
        ]);
    }

    public function test_visiting_with_backlog_query_persists_backlog_view(): void
    {
        $response = $this->actingAs($this->owner)
            ->get("/projects/{$this->project->id}?backlog=1");

        $response->assertOk();

        $this->assertDatabaseHas('user_project_filters', [
            'user_id'        => $this->owner->id,
            'project_id'     => $this->project->id,
            'view_mode'      => 'backlog',
            'view_sprint_id' => null,
        ]);
    }

    public function test_visiting_with_all_query_persists_all_view(): void
    {
        $response = $this->actingAs($this->owner)
            ->get("/projects/{$this->project->id}?all=1");

        $response->assertOk();

        $this->assertDatabaseHas('user_project_filters', [
            'user_id'        => $this->owner->id,
            'project_id'     => $this->project->id,
            'view_mode'      => 'all',
            'view_sprint_id' => null,
        ]);
    }

    // -----------------------------------------------------------------------
    // Restoring saved view on plain /projects/{project}
    // -----------------------------------------------------------------------

    public function test_show_without_query_restores_saved_backlog_view(): void
    {
        UserProjectFilter::create([
            'user_id'    => $this->owner->id,
            'project_id' => $this->project->id,
            'view_mode'  => 'backlog',
        ]);

        $response = $this->actingAs($this->owner)
            ->get("/projects/{$this->project->id}");

        $response->assertOk();
        $props = $this->pageProps($response);
        $this->assertTrue($props['isBacklog']);
        $this->assertFalse($props['isAll']);
        $this->assertNull($props['currentSprint']);
    }

    public function test_show_without_query_restores_saved_all_view(): void
    {
        UserProjectFilter::create([
            'user_id'    => $this->owner->id,
            'project_id' => $this->project->id,
            'view_mode'  => 'all',
        ]);

        $response = $this->actingAs($this->owner)
            ->get("/projects/{$this->project->id}");

        $response->assertOk();
        $props = $this->pageProps($response);
        $this->assertTrue($props['isAll']);
        $this->assertFalse($props['isBacklog']);
        $this->assertNull($props['currentSprint']);
    }

    public function test_show_without_query_restores_saved_sprint_view(): void
    {
        $sprint2 = Sprint::create([
            'project_id' => $this->project->id,
            'name'       => 'Sprint 2',
            'status'     => 'active',
            'locked'     => false,
        ]);

        UserProjectFilter::create([
            'user_id'        => $this->owner->id,
            'project_id'     => $this->project->id,
            'view_mode'      => 'active',
            'view_sprint_id' => $sprint2->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->get("/projects/{$this->project->id}");

        $response->assertOk();
        $props = $this->pageProps($response);
        $this->assertFalse($props['isBacklog']);
        $this->assertFalse($props['isAll']);
        $this->assertEquals($sprint2->id, $props['currentSprint']['id']);
    }

    public function test_show_without_query_or_saved_view_falls_back_to_active_sprint(): void
    {
        // Mark Sprint 1 as completed so the "active" fallback chooses a fresh one.
        $this->sprint->update(['status' => 'completed']);
        $sprintActive = Sprint::create([
            'project_id' => $this->project->id,
            'name'       => 'Sprint Active',
            'status'     => 'active',
            'locked'     => false,
        ]);

        $response = $this->actingAs($this->owner)
            ->get("/projects/{$this->project->id}");

        $response->assertOk();
        $props = $this->pageProps($response);
        $this->assertFalse($props['isBacklog']);
        $this->assertFalse($props['isAll']);
        $this->assertEquals($sprintActive->id, $props['currentSprint']['id']);
    }

    public function test_stored_view_sprint_id_is_ignored_when_sprint_was_deleted(): void
    {
        $doomed = Sprint::create([
            'project_id' => $this->project->id,
            'name'       => 'Doomed',
            'status'     => 'active',
            'locked'     => false,
        ]);

        UserProjectFilter::create([
            'user_id'        => $this->owner->id,
            'project_id'     => $this->project->id,
            'view_mode'      => 'active',
            'view_sprint_id' => $doomed->id,
        ]);

        // Cascade should null the column via the FK constraint when we delete.
        $doomed->delete();

        $response = $this->actingAs($this->owner)
            ->get("/projects/{$this->project->id}");

        $response->assertOk();
        $props = $this->pageProps($response);
        // Falls back to the remaining active sprint
        $this->assertEquals($this->sprint->id, $props['currentSprint']['id']);
    }

    // -----------------------------------------------------------------------
    // Query params win and overwrite previously saved view
    // -----------------------------------------------------------------------

    public function test_query_param_overrides_saved_view_and_updates_persisted_value(): void
    {
        UserProjectFilter::create([
            'user_id'    => $this->owner->id,
            'project_id' => $this->project->id,
            'view_mode'  => 'all',
        ]);

        $this->actingAs($this->owner)
            ->get("/projects/{$this->project->id}?backlog=1")
            ->assertOk();

        $this->assertDatabaseHas('user_project_filters', [
            'user_id'    => $this->owner->id,
            'project_id' => $this->project->id,
            'view_mode'  => 'backlog',
        ]);

        // Now visiting without any query should restore "backlog".
        $response = $this->actingAs($this->owner)
            ->get("/projects/{$this->project->id}");
        $this->assertTrue($this->pageProps($response)['isBacklog']);
    }

    // -----------------------------------------------------------------------
    // Saving the view must not stomp other filter columns
    // -----------------------------------------------------------------------

    public function test_view_persistence_preserves_other_filters(): void
    {
        UserProjectFilter::create([
            'user_id'        => $this->owner->id,
            'project_id'     => $this->project->id,
            'priorities'     => ['high', 'urgent'],
            'assignee_ids'   => [$this->owner->id],
            'hide_completed' => true,
            'unassigned'     => true,
            'view_mode'      => 'active',
            'view_sprint_id' => $this->sprint->id,
        ]);

        $this->actingAs($this->owner)
            ->get("/projects/{$this->project->id}?all=1")
            ->assertOk();

        $filter = UserProjectFilter::where('user_id', $this->owner->id)
            ->where('project_id', $this->project->id)
            ->first();

        $this->assertEquals('all', $filter->view_mode);
        $this->assertNull($filter->view_sprint_id);
        $this->assertEquals(['high', 'urgent'], $filter->priorities);
        $this->assertEquals([$this->owner->id], $filter->assignee_ids);
        $this->assertTrue($filter->hide_completed);
        $this->assertTrue($filter->unassigned);
    }

    // -----------------------------------------------------------------------
    // View persistence is per-user and per-project
    // -----------------------------------------------------------------------

    public function test_view_persistence_is_isolated_per_user(): void
    {
        $member = User::factory()->create();
        $this->workspace->users()->attach($member->id, ['role' => 'member']);
        $this->project->members()->attach($member->id, ['role' => 'member']);

        // Owner picks "all"
        $this->actingAs($this->owner)
            ->get("/projects/{$this->project->id}?all=1")
            ->assertOk();

        // Member visits without any query — should NOT inherit owner's view
        $response = $this->actingAs($member)
            ->get("/projects/{$this->project->id}");
        $props = $this->pageProps($response);
        $this->assertFalse($props['isAll']);
        $this->assertFalse($props['isBacklog']);
        // Member gets default fallback (active sprint)
        $this->assertNotNull($props['currentSprint']);
    }

    public function test_view_persistence_is_isolated_per_project(): void
    {
        $project2 = Project::create([
            'name'         => 'Project 2',
            'key'          => 'P2',
            'color'        => '#000',
            'owner_id'     => $this->owner->id,
            'workspace_id' => $this->workspace->id,
        ]);
        $project2->members()->attach($this->owner->id, ['role' => 'owner']);
        Sprint::create([
            'project_id' => $project2->id,
            'name'       => 'P2 Sprint',
            'status'     => 'active',
            'locked'     => false,
        ]);

        $this->actingAs($this->owner)
            ->get("/projects/{$this->project->id}?backlog=1")
            ->assertOk();
        $this->actingAs($this->owner)
            ->get("/projects/{$project2->id}?all=1")
            ->assertOk();

        $r1 = $this->actingAs($this->owner)->get("/projects/{$this->project->id}");
        $this->assertTrue($this->pageProps($r1)['isBacklog']);
        $this->assertFalse($this->pageProps($r1)['isAll']);

        $r2 = $this->actingAs($this->owner)->get("/projects/{$project2->id}");
        $this->assertTrue($this->pageProps($r2)['isAll']);
        $this->assertFalse($this->pageProps($r2)['isBacklog']);
    }

    // -----------------------------------------------------------------------
    // The savedFilters prop exposes view state to the frontend
    // -----------------------------------------------------------------------

    public function test_saved_filters_prop_includes_view_state(): void
    {
        $response = $this->actingAs($this->owner)
            ->get("/projects/{$this->project->id}?backlog=1");

        $response->assertOk();
        $savedFilters = $this->pageProps($response)['savedFilters'];

        $this->assertEquals('backlog', $savedFilters['view_mode']);
        $this->assertNull($savedFilters['view_sprint_id']);
    }

    // -----------------------------------------------------------------------
    // FilterController PUT also accepts view fields (frontend safety hatch)
    // -----------------------------------------------------------------------

    public function test_filter_update_endpoint_accepts_view_fields(): void
    {
        $this->actingAs($this->owner)
            ->putJson("/projects/{$this->project->id}/filters", [
                'view_mode'      => 'active',
                'view_sprint_id' => $this->sprint->id,
            ])
            ->assertOk()
            ->assertJson([
                'view_mode'      => 'active',
                'view_sprint_id' => $this->sprint->id,
            ]);
    }

    public function test_filter_update_endpoint_rejects_unknown_view_mode(): void
    {
        $this->actingAs($this->owner)
            ->putJson("/projects/{$this->project->id}/filters", [
                'view_mode' => 'bogus',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['view_mode']);
    }
}
