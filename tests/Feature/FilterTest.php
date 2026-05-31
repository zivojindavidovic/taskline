<?php

namespace Tests\Feature;

use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use App\Models\UserProjectFilter;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilterTest extends TestCase
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
            'locked'     => false,
        ]);
        $this->column = BoardColumn::create([
            'project_id' => $this->project->id,
            'name'       => 'Todo',
            'color'      => '#94948c',
            'position'   => 0,
        ]);
    }

    private function createTask(array $attrs = []): Task
    {
        static $counter = 0;
        $counter++;
        return Task::create(array_merge([
            'key'             => "TST-{$counter}",
            'title'           => "Task {$counter}",
            'project_id'      => $this->project->id,
            'sprint_id'       => $this->sprint->id,
            'board_column_id' => $this->column->id,
            'created_by'      => $this->owner->id,
            'priority'        => 'med',
        ], $attrs));
    }

    // -----------------------------------------------------------------------
    // GET /projects/{project}/filters
    // -----------------------------------------------------------------------

    public function test_get_filters_returns_empty_defaults_when_no_filters_saved(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson("/projects/{$this->project->uuid}/filters");

        $response->assertOk()->assertJson([
            'sprint_ids'     => [],
            'assignee_ids'   => [],
            'priorities'     => [],
            'status_ids'     => [],
            'statuses'       => [],
            'hide_completed' => false,
            'unassigned'     => false,
        ]);
    }

    public function test_get_filters_returns_previously_saved_filters(): void
    {
        UserProjectFilter::create([
            'user_id'      => $this->owner->id,
            'project_id'   => $this->project->id,
            'sprint_ids'   => [$this->sprint->id],
            'assignee_ids' => [$this->owner->id],
            'priorities'   => ['high'],
            'status_ids'   => [$this->column->id],
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson("/projects/{$this->project->uuid}/filters");

        $response->assertOk()->assertJson([
            'sprint_ids'   => [$this->sprint->id],
            'assignee_ids' => [$this->owner->id],
            'priorities'   => ['high'],
            'status_ids'   => [$this->column->id],
        ]);
    }

    public function test_get_filters_is_isolated_per_user(): void
    {
        $otherUser = User::factory()->create();
        $this->workspace->users()->attach($otherUser->id, ['role' => 'member']);
        $this->project->members()->attach($otherUser->id, ['role' => 'member']);

        UserProjectFilter::create([
            'user_id'    => $this->owner->id,
            'project_id' => $this->project->id,
            'priorities' => ['urgent'],
        ]);

        $response = $this->actingAs($otherUser)
            ->getJson("/projects/{$this->project->uuid}/filters");

        $response->assertOk()->assertJson(['priorities' => []]);
    }

    public function test_get_filters_requires_authentication(): void
    {
        $this->getJson("/projects/{$this->project->uuid}/filters")
            ->assertUnauthorized();
    }

    public function test_get_filters_forbidden_for_non_member(): void
    {
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->getJson("/projects/{$this->project->uuid}/filters")
            ->assertForbidden();
    }

    // -----------------------------------------------------------------------
    // PUT /projects/{project}/filters
    // -----------------------------------------------------------------------

    public function test_save_sprint_filter(): void
    {
        $sprint2 = Sprint::create([
            'project_id' => $this->project->id,
            'name'       => 'Sprint 2',
            'locked'     => false,
        ]);

        $response = $this->actingAs($this->owner)
            ->putJson("/projects/{$this->project->uuid}/filters", [
                'sprint_ids' => [$this->sprint->id, $sprint2->id],
            ]);

        $response->assertOk()->assertJson([
            'sprint_ids' => [$this->sprint->id, $sprint2->id],
        ]);

        $this->assertDatabaseHas('user_project_filters', [
            'user_id'    => $this->owner->id,
            'project_id' => $this->project->id,
        ]);
    }

    public function test_save_assignee_filter(): void
    {
        $member = User::factory()->create();
        $this->workspace->users()->attach($member->id, ['role' => 'member']);
        $this->project->members()->attach($member->id, ['role' => 'member']);

        $response = $this->actingAs($this->owner)
            ->putJson("/projects/{$this->project->uuid}/filters", [
                'assignee_ids' => [$member->id],
            ]);

        $response->assertOk()->assertJson([
            'assignee_ids' => [$member->id],
        ]);
    }

    public function test_save_priority_filter(): void
    {
        $response = $this->actingAs($this->owner)
            ->putJson("/projects/{$this->project->uuid}/filters", [
                'priorities' => ['high', 'urgent'],
            ]);

        $response->assertOk()->assertJson([
            'priorities' => ['high', 'urgent'],
        ]);
    }

    public function test_save_status_filter(): void
    {
        $column2 = BoardColumn::create([
            'project_id' => $this->project->id,
            'name'       => 'In Progress',
            'color'      => '#d97706',
            'position'   => 1,
        ]);

        $response = $this->actingAs($this->owner)
            ->putJson("/projects/{$this->project->uuid}/filters", [
                'status_ids' => [$this->column->id, $column2->id],
            ]);

        $response->assertOk()->assertJson([
            'status_ids' => [$this->column->id, $column2->id],
        ]);
    }

    public function test_save_statuses_filter(): void
    {
        $response = $this->actingAs($this->owner)
            ->putJson("/projects/{$this->project->uuid}/filters", [
                'statuses' => ['open', 'completed'],
            ]);

        $response->assertOk()->assertJson([
            'statuses' => ['open', 'completed'],
        ]);
    }

    public function test_save_hide_completed_filter(): void
    {
        $response = $this->actingAs($this->owner)
            ->putJson("/projects/{$this->project->uuid}/filters", [
                'hide_completed' => true,
            ]);

        $response->assertOk()->assertJson([
            'hide_completed' => true,
        ]);
    }

    public function test_save_unassigned_filter(): void
    {
        $response = $this->actingAs($this->owner)
            ->putJson("/projects/{$this->project->uuid}/filters", [
                'unassigned' => true,
            ]);

        $response->assertOk()->assertJson([
            'unassigned' => true,
        ]);
    }

    public function test_save_combined_filters(): void
    {
        $response = $this->actingAs($this->owner)
            ->putJson("/projects/{$this->project->uuid}/filters", [
                'sprint_ids'     => [$this->sprint->id],
                'assignee_ids'   => [$this->owner->id],
                'priorities'     => ['low', 'med'],
                'status_ids'     => [$this->column->id],
                'statuses'       => ['open'],
                'hide_completed' => false,
                'unassigned'     => true,
            ]);

        $response->assertOk()->assertJson([
            'sprint_ids'     => [$this->sprint->id],
            'assignee_ids'   => [$this->owner->id],
            'priorities'     => ['low', 'med'],
            'status_ids'     => [$this->column->id],
            'statuses'       => ['open'],
            'hide_completed' => false,
            'unassigned'     => true,
        ]);
    }

    public function test_save_empty_arrays_clears_filters(): void
    {
        UserProjectFilter::create([
            'user_id'    => $this->owner->id,
            'project_id' => $this->project->id,
            'priorities' => ['high'],
        ]);

        $this->actingAs($this->owner)
            ->putJson("/projects/{$this->project->uuid}/filters", [
                'priorities' => [],
            ])
            ->assertOk()
            ->assertJson(['priorities' => []]);
    }

    public function test_filters_persist_across_requests(): void
    {
        $this->actingAs($this->owner)
            ->putJson("/projects/{$this->project->uuid}/filters", [
                'priorities'     => ['urgent'],
                'hide_completed' => true,
                'unassigned'     => true,
            ]);

        $response = $this->actingAs($this->owner)
            ->getJson("/projects/{$this->project->uuid}/filters");

        $response->assertOk()->assertJson([
            'priorities'     => ['urgent'],
            'hide_completed' => true,
            'unassigned'     => true,
        ]);
    }

    public function test_save_overwrites_existing_filters(): void
    {
        UserProjectFilter::create([
            'user_id'    => $this->owner->id,
            'project_id' => $this->project->id,
            'priorities' => ['low'],
        ]);

        $this->actingAs($this->owner)
            ->putJson("/projects/{$this->project->uuid}/filters", [
                'priorities' => ['high', 'urgent'],
            ])
            ->assertOk()
            ->assertJson(['priorities' => ['high', 'urgent']]);

        $this->assertDatabaseCount('user_project_filters', 1);
    }

    public function test_filters_are_isolated_per_project(): void
    {
        $project2 = Project::create([
            'name'         => 'Project 2',
            'key'          => 'P2',
            'color'        => '#000',
            'owner_id'     => $this->owner->id,
            'workspace_id' => $this->workspace->id,
        ]);
        $project2->members()->attach($this->owner->id, ['role' => 'owner']);

        $this->actingAs($this->owner)
            ->putJson("/projects/{$this->project->uuid}/filters", ['priorities' => ['high']]);

        $this->actingAs($this->owner)
            ->putJson("/projects/{$project2->uuid}/filters", ['priorities' => ['low']]);

        $this->actingAs($this->owner)
            ->getJson("/projects/{$this->project->uuid}/filters")
            ->assertJson(['priorities' => ['high']]);

        $this->actingAs($this->owner)
            ->getJson("/projects/{$project2->uuid}/filters")
            ->assertJson(['priorities' => ['low']]);
    }

    public function test_invalid_priority_value_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->putJson("/projects/{$this->project->uuid}/filters", [
                'priorities' => ['invalid_priority'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['priorities.0']);
    }

    public function test_invalid_status_value_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->putJson("/projects/{$this->project->uuid}/filters", [
                'statuses' => ['invalid_status'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['statuses.0']);
    }

    public function test_non_array_sprint_ids_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->putJson("/projects/{$this->project->uuid}/filters", [
                'sprint_ids' => 'not-an-array',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sprint_ids']);
    }

    public function test_non_integer_assignee_id_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->putJson("/projects/{$this->project->uuid}/filters", [
                'assignee_ids' => ['not-an-integer'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assignee_ids.0']);
    }

    public function test_save_filters_forbidden_for_non_member(): void
    {
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->putJson("/projects/{$this->project->uuid}/filters", [
                'priorities' => ['high'],
            ])
            ->assertForbidden();
    }

    public function test_save_filters_requires_authentication(): void
    {
        $this->putJson("/projects/{$this->project->uuid}/filters", [
            'priorities' => ['high'],
        ])->assertUnauthorized();
    }

    // -----------------------------------------------------------------------
    // ProjectController::show() — savedFilters prop, no server-side filtering
    // -----------------------------------------------------------------------

    public function test_project_show_passes_saved_filters_to_frontend(): void
    {
        UserProjectFilter::create([
            'user_id'        => $this->owner->id,
            'project_id'     => $this->project->id,
            'priorities'     => ['high', 'urgent'],
            'hide_completed' => true,
            'unassigned'     => true,
        ]);

        $response = $this->actingAs($this->owner)
            ->get("/projects/{$this->project->uuid}");

        $response->assertOk();
        $savedFilters = $response->original->getData()['page']['props']['savedFilters'];
        $this->assertEquals(['high', 'urgent'], $savedFilters['priorities']);
        $this->assertTrue($savedFilters['hide_completed']);
        $this->assertTrue($savedFilters['unassigned']);
    }

    public function test_project_show_passes_empty_saved_filters_when_none_set(): void
    {
        $response = $this->actingAs($this->owner)
            ->get("/projects/{$this->project->uuid}");

        $response->assertOk();
        $savedFilters = $response->original->getData()['page']['props']['savedFilters'];
        $this->assertEquals([], $savedFilters['priorities']);
        $this->assertEquals([], $savedFilters['sprint_ids']);
        $this->assertEquals([], $savedFilters['assignee_ids']);
        $this->assertEquals([], $savedFilters['status_ids']);
        $this->assertEquals([], $savedFilters['statuses']);
        $this->assertFalse($savedFilters['hide_completed']);
        $this->assertFalse($savedFilters['unassigned']);
    }

    public function test_project_show_returns_all_tasks_regardless_of_saved_filters(): void
    {
        $task1 = $this->createTask(['priority' => 'high', 'title' => 'High priority']);
        $task2 = $this->createTask(['priority' => 'low',  'title' => 'Low priority']);

        UserProjectFilter::create([
            'user_id'    => $this->owner->id,
            'project_id' => $this->project->id,
            'priorities' => ['high'],
        ]);

        $response = $this->actingAs($this->owner)
            ->get("/projects/{$this->project->uuid}?all=1");

        $response->assertOk();
        $taskIds = collect($response->original->getData()['page']['props']['tasks'])->pluck('id');
        $this->assertContains($task1->id, $taskIds->all());
        $this->assertContains($task2->id, $taskIds->all());
    }

    public function test_no_filters_applied_when_none_saved(): void
    {
        $task1 = $this->createTask(['priority' => 'high']);
        $task2 = $this->createTask(['priority' => 'low']);

        $response = $this->actingAs($this->owner)
            ->get("/projects/{$this->project->uuid}?all=1");

        $response->assertOk();
        $taskIds = collect($response->original->getData()['page']['props']['tasks'])->pluck('id');
        $this->assertContains($task1->id, $taskIds->all());
        $this->assertContains($task2->id, $taskIds->all());
    }
}
