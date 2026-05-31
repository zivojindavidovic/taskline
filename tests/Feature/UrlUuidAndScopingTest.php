<?php

namespace Tests\Feature;

use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Ramsey\Uuid\Uuid as RamseyUuid;
use Tests\TestCase;

/**
 * Covers three fixes:
 *   #1 a new project starts with a backlog only (no auto "Sprint 1")
 *   #2 URL-exposed resources are addressed by a UUIDv7, never the integer id
 *   #3 the Members tab only lists projects the viewer can actually access
 */
class UrlUuidAndScopingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Workspace $workspace;
    private Project $project;
    private BoardColumn $column;

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
        $this->column = BoardColumn::create([
            'project_id' => $this->project->id,
            'name'       => 'Todo',
            'color'      => '#94948c',
            'position'   => 0,
        ]);
    }

    private function makeMember(string $role = 'member'): User
    {
        $user = User::factory()->create();
        $this->workspace->users()->attach($user->id, ['role' => $role]);
        $user->update(['current_workspace_id' => $this->workspace->id]);

        return $user;
    }

    // ── #1 — no default sprint ───────────────────────────────────────────────

    public function test_creating_a_project_creates_no_default_sprint(): void
    {
        $this->actingAs($this->owner)
            ->post('/projects', ['name' => 'Web App', 'key' => 'WEB', 'color' => '#0891b2'])
            ->assertRedirect();

        $project = Project::where('key', 'WEB')->firstOrFail();

        $this->assertSame(0, $project->sprints()->count(), 'A new project must start with no sprints.');
        // Default board columns still get created — only the sprint is gone.
        $this->assertSame(4, $project->boardColumns()->count());
    }

    public function test_new_project_tasks_live_in_the_backlog(): void
    {
        $this->actingAs($this->owner)
            ->post('/projects', ['name' => 'Web App', 'key' => 'WEB', 'color' => '#0891b2']);

        $project = Project::where('key', 'WEB')->firstOrFail();
        $todo = $project->boardColumns()->first();

        $task = Task::create([
            'key'             => 'WEB-1',
            'title'           => 'First task',
            'project_id'      => $project->id,
            'board_column_id' => $todo->id,
            'created_by'      => $this->owner->id,
            'priority'        => 'med',
        ]);

        $this->assertNull($task->sprint_id, 'A task in a sprintless project belongs to the backlog.');
    }

    // ── #2 — UUIDv7 in URLs ──────────────────────────────────────────────────

    public function test_models_get_a_uuid_v7_on_create(): void
    {
        foreach ([$this->project, $this->column] as $model) {
            $this->assertNotNull($model->uuid);
            $this->assertSame(7, RamseyUuid::fromString($model->uuid)->getFields()->getVersion());
        }

        $sprint = Sprint::create(['project_id' => $this->project->id, 'name' => 'S1']);
        $task = Task::create([
            'key' => 'MOB-1', 'title' => 'x',
            'project_id' => $this->project->id, 'board_column_id' => $this->column->id,
            'created_by' => $this->owner->id, 'priority' => 'med',
        ]);

        $this->assertSame(7, RamseyUuid::fromString($sprint->uuid)->getFields()->getVersion());
        $this->assertSame(7, RamseyUuid::fromString($task->uuid)->getFields()->getVersion());
    }

    public function test_project_route_resolves_by_uuid_not_id(): void
    {
        $this->actingAs($this->owner)
            ->get("/projects/{$this->project->uuid}")
            ->assertOk();

        // The integer id must no longer be a valid route key — it is not exposed
        // and cannot be enumerated.
        $this->actingAs($this->owner)
            ->get("/projects/{$this->project->id}")
            ->assertNotFound();
    }

    public function test_task_details_resolves_by_uuid(): void
    {
        $task = Task::create([
            'key' => 'MOB-1', 'title' => 'x',
            'project_id' => $this->project->id, 'board_column_id' => $this->column->id,
            'created_by' => $this->owner->id, 'priority' => 'med',
        ]);

        $this->actingAs($this->owner)
            ->getJson("/tasks/{$task->uuid}/details")
            ->assertOk()
            ->assertJsonPath('task.uuid', $task->uuid);

        $this->actingAs($this->owner)
            ->getJson("/tasks/{$task->id}/details")
            ->assertNotFound();
    }

    public function test_route_key_name_is_uuid(): void
    {
        $this->assertSame('uuid', $this->project->getRouteKeyName());
        $this->assertSame('uuid', (new Task)->getRouteKeyName());
        $this->assertSame('uuid', (new Sprint)->getRouteKeyName());
        $this->assertSame('uuid', (new BoardColumn)->getRouteKeyName());
    }

    public function test_show_payload_exposes_project_uuid(): void
    {
        $this->actingAs($this->owner)
            ->get("/projects/{$this->project->uuid}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Show')
                ->where('project.uuid', $this->project->uuid)
            );
    }

    // ── #3 — Members tab project scoping ─────────────────────────────────────

    public function test_plain_member_only_sees_projects_they_belong_to_on_members_tab(): void
    {
        $alice = $this->makeMember();

        // A second project Alice is NOT part of, and one she IS part of.
        $projectB = Project::create([
            'name' => 'Infra', 'key' => 'INF', 'color' => '#16a34a',
            'owner_id' => $this->owner->id, 'workspace_id' => $this->workspace->id,
        ]);
        $projectB->members()->attach($alice->id, ['role' => 'member']);

        $this->actingAs($alice)
            ->get('/members')
            ->assertInertia(fn (Assert $page) => $page
                ->component('WorkspaceMembers')
                ->has('projects', 1)
                ->where('projects.0.uuid', $projectB->uuid)
            );
    }

    public function test_owner_sees_all_projects_on_members_tab(): void
    {
        Project::create([
            'name' => 'Infra', 'key' => 'INF', 'color' => '#16a34a',
            'owner_id' => $this->owner->id, 'workspace_id' => $this->workspace->id,
        ]);

        $this->actingAs($this->owner)
            ->get('/members')
            ->assertInertia(fn (Assert $page) => $page->has('projects', 2));
    }
}
