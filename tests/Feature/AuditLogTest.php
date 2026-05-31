<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Workspace $workspace;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();

        $this->workspace = Workspace::create([
            'name'     => 'Acme',
            'color'    => '#4f46e5',
            'owner_id' => $this->owner->id,
        ]);
        $this->workspace->users()->attach($this->owner->id, ['role' => 'owner']);
        $this->owner->update(['current_workspace_id' => $this->workspace->id]);

        $this->project = Project::create([
            'name'         => 'Northstar',
            'key'          => 'NS',
            'color'        => '#16a34a',
            'owner_id'     => $this->owner->id,
            'workspace_id' => $this->workspace->id,
        ]);

        BoardColumn::create([
            'project_id' => $this->project->id,
            'name'       => 'Todo',
            'color'      => '#94948c',
            'position'   => 0,
        ]);
    }

    public function test_audit_page_renders_with_workspace_logs(): void
    {
        AuditLog::create([
            'user_id'    => $this->owner->id,
            'project_id' => $this->project->id,
            'action'     => 'project.created',
            'meta'       => ['name' => $this->project->name],
        ]);

        $response = $this->actingAs($this->owner)->get('/audit');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('AuditLog')
            ->has('logs.data', 1)
            ->where('logs.data.0.action', 'project.created')
            ->has('projects', 1)
            ->has('members', 1)
            ->where('filters.range', '7d')
        );
    }

    public function test_audit_page_filters_by_project(): void
    {
        $secondProject = Project::create([
            'name'         => 'Atlas',
            'key'          => 'AT',
            'color'        => '#d97706',
            'owner_id'     => $this->owner->id,
            'workspace_id' => $this->workspace->id,
        ]);

        AuditLog::create(['user_id' => $this->owner->id, 'project_id' => $this->project->id, 'action' => 'project.created']);
        AuditLog::create(['user_id' => $this->owner->id, 'project_id' => $secondProject->id, 'action' => 'project.created']);

        $response = $this->actingAs($this->owner)->get('/audit?project_id='.$secondProject->id);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.project_id', $secondProject->id)
            ->where('filters.project_id', $secondProject->id)
        );
    }

    public function test_audit_page_filters_by_range_today(): void
    {
        // Eloquent overwrites timestamps on create — bypass with a raw insert
        // to plant a row in the distant past for the range-filter assertion.
        \DB::table('audit_logs')->insert([
            'user_id'    => $this->owner->id,
            'project_id' => $this->project->id,
            'action'     => 'task.completed',
            'meta'       => null,
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);
        AuditLog::create([
            'user_id'    => $this->owner->id,
            'project_id' => $this->project->id,
            'action'     => 'task.created',
        ]);

        $response = $this->actingAs($this->owner)->get('/audit?range=today');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.action', 'task.created')
        );
    }

    public function test_audit_scopes_to_current_workspace(): void
    {
        // Another workspace the user has no access to
        $other = User::factory()->create();
        $otherWorkspace = Workspace::create([
            'name'     => 'Other',
            'color'    => '#dc2626',
            'owner_id' => $other->id,
        ]);
        $otherProject = Project::create([
            'name'         => 'Foreign',
            'key'          => 'FO',
            'color'        => '#dc2626',
            'owner_id'     => $other->id,
            'workspace_id' => $otherWorkspace->id,
        ]);

        AuditLog::create(['user_id' => $other->id, 'project_id' => $otherProject->id, 'action' => 'project.created']);
        AuditLog::create(['user_id' => $this->owner->id, 'project_id' => $this->project->id, 'action' => 'project.created']);

        $response = $this->actingAs($this->owner)->get('/audit');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.project_id', $this->project->id)
        );
    }

    public function test_task_deletion_writes_audit_entry(): void
    {
        $task = Task::create([
            'project_id'      => $this->project->id,
            'board_column_id' => $this->project->boardColumns()->first()->id,
            'key'             => 'NS-1',
            'title'           => 'Ship the rocket',
            'priority'        => 'med',
            'created_by'      => $this->owner->id,
        ]);

        $this->actingAs($this->owner)->delete("/tasks/{$task->uuid}")->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->owner->id,
            'action'  => 'task.deleted',
        ]);
    }

    public function test_column_creation_writes_audit_entry(): void
    {
        $this->actingAs($this->owner)
            ->post("/projects/{$this->project->uuid}/columns", ['name' => 'Blocked', 'color' => '#dc2626'])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'user_id'    => $this->owner->id,
            'project_id' => $this->project->id,
            'action'     => 'column.created',
        ]);
    }
}
