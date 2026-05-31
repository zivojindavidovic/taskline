<?php

namespace Tests\Feature;

use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SprintCompletionTest extends TestCase
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

    private function makeTask(array $attrs = []): Task
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
    // POST /sprints/{sprint}/complete
    // -----------------------------------------------------------------------

    public function test_member_can_complete_an_active_sprint(): void
    {
        $this->actingAs($this->owner)
            ->post("/sprints/{$this->sprint->uuid}/complete")
            ->assertRedirect();

        $this->assertDatabaseHas('sprints', [
            'id'     => $this->sprint->id,
            'status' => 'completed',
        ]);
    }

    public function test_completing_sprint_writes_audit_log(): void
    {
        $this->actingAs($this->owner)
            ->post("/sprints/{$this->sprint->uuid}/complete");

        $this->assertDatabaseHas('audit_logs', [
            'user_id'    => $this->owner->id,
            'project_id' => $this->project->id,
            'action'     => 'sprint.completed',
        ]);
    }

    public function test_cannot_complete_an_already_completed_sprint(): void
    {
        $this->sprint->update(['status' => 'completed']);

        $this->actingAs($this->owner)
            ->post("/sprints/{$this->sprint->uuid}/complete")
            ->assertStatus(422);
    }

    public function test_completing_a_locked_sprint_is_allowed(): void
    {
        $this->sprint->update(['locked' => true]);

        $this->actingAs($this->owner)
            ->post("/sprints/{$this->sprint->uuid}/complete")
            ->assertRedirect();

        $this->assertDatabaseHas('sprints', [
            'id'     => $this->sprint->id,
            'status' => 'completed',
            'locked' => true,
        ]);
    }

    public function test_guest_cannot_complete_sprint(): void
    {
        $this->post("/sprints/{$this->sprint->uuid}/complete")
            ->assertRedirect('/login');

        $this->assertDatabaseHas('sprints', [
            'id'     => $this->sprint->id,
            'status' => 'active',
        ]);
    }

    // -----------------------------------------------------------------------
    // POST /sprints/{sprint}/reopen
    // -----------------------------------------------------------------------

    public function test_member_can_reopen_a_completed_sprint(): void
    {
        $this->sprint->update(['status' => 'completed']);

        $this->actingAs($this->owner)
            ->post("/sprints/{$this->sprint->uuid}/reopen")
            ->assertRedirect();

        $this->assertDatabaseHas('sprints', [
            'id'     => $this->sprint->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->owner->id,
            'action'  => 'sprint.reopened',
        ]);
    }

    public function test_cannot_reopen_a_sprint_that_is_not_completed(): void
    {
        $this->actingAs($this->owner)
            ->post("/sprints/{$this->sprint->uuid}/reopen")
            ->assertStatus(422);
    }

    // -----------------------------------------------------------------------
    // POST /tasks/{task}/complete  +  /tasks/{task}/uncomplete  on a locked sprint
    // -----------------------------------------------------------------------

    public function test_task_complete_works_on_a_locked_sprint(): void
    {
        $this->sprint->update(['locked' => true]);
        $task = $this->makeTask();

        $this->actingAs($this->owner)
            ->post("/tasks/{$task->uuid}/complete")
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id'           => $task->id,
            'completed'    => true,
            'completed_by' => $this->owner->id,
        ]);
        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_task_uncomplete_works_on_a_locked_sprint(): void
    {
        $this->sprint->update(['locked' => true]);
        $task = $this->makeTask([
            'completed'    => true,
            'completed_at' => now(),
            'completed_by' => $this->owner->id,
        ]);

        $this->actingAs($this->owner)
            ->post("/tasks/{$task->uuid}/uncomplete")
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id'           => $task->id,
            'completed'    => false,
            'completed_at' => null,
            'completed_by' => null,
        ]);
    }

    public function test_task_complete_on_locked_sprint_writes_audit_log(): void
    {
        $this->sprint->update(['locked' => true]);
        $task = $this->makeTask();

        $this->actingAs($this->owner)
            ->post("/tasks/{$task->uuid}/complete");

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->owner->id,
            'task_id' => $task->id,
            'action'  => 'task.completed',
        ]);
    }

    public function test_task_uncomplete_on_locked_sprint_writes_audit_log(): void
    {
        $this->sprint->update(['locked' => true]);
        $task = $this->makeTask([
            'completed'    => true,
            'completed_at' => now(),
            'completed_by' => $this->owner->id,
        ]);

        $this->actingAs($this->owner)
            ->post("/tasks/{$task->uuid}/uncomplete");

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->owner->id,
            'task_id' => $task->id,
            'action'  => 'task.reopened',
        ]);
    }
}
