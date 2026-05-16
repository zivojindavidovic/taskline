<?php

namespace Tests\Feature\Task;

use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Workspace $workspace;
    private Project $project;
    private Sprint $sprint;
    private BoardColumn $column;
    private Task $task;

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

        $this->task = Task::create([
            'key'             => 'TST-1',
            'title'           => 'Test task',
            'project_id'      => $this->project->id,
            'sprint_id'       => $this->sprint->id,
            'board_column_id' => $this->column->id,
            'created_by'      => $this->owner->id,
            'priority'        => 'med',
        ]);
    }

    // -----------------------------------------------------------------------
    // Project update
    // -----------------------------------------------------------------------

    public function test_task_can_be_moved_to_another_project(): void
    {
        $otherProject = Project::create([
            'name'         => 'Other Project',
            'key'          => 'OTH',
            'color'        => '#000',
            'owner_id'     => $this->owner->id,
            'workspace_id' => $this->workspace->id,
        ]);
        $otherProject->members()->attach($this->owner->id, ['role' => 'owner']);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$this->task->id}", ['project_id' => $otherProject->id])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id'         => $this->task->id,
            'project_id' => $otherProject->id,
        ]);
    }

    public function test_task_project_update_creates_audit_log(): void
    {
        $otherProject = Project::create([
            'name'         => 'Other Project',
            'key'          => 'OTH',
            'color'        => '#000',
            'owner_id'     => $this->owner->id,
            'workspace_id' => $this->workspace->id,
        ]);
        $otherProject->members()->attach($this->owner->id, ['role' => 'owner']);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$this->task->id}", ['project_id' => $otherProject->id]);

        $this->assertDatabaseHas('audit_logs', [
            'task_id' => $this->task->id,
            'action'  => 'task.project_changed',
        ]);
    }

    public function test_task_project_update_requires_valid_project(): void
    {
        $this->actingAs($this->owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->patch("/tasks/{$this->task->id}", ['project_id' => 99999])
            ->assertUnprocessable();
    }

    // -----------------------------------------------------------------------
    // Sprint update
    // -----------------------------------------------------------------------

    public function test_task_can_be_added_to_sprint(): void
    {
        $sprint2 = Sprint::create([
            'project_id' => $this->project->id,
            'name'       => 'Sprint 2',
            'locked'     => false,
        ]);

        $taskWithoutSprint = Task::create([
            'key'             => 'TST-2',
            'title'           => 'Backlog task',
            'project_id'      => $this->project->id,
            'sprint_id'       => null,
            'board_column_id' => $this->column->id,
            'created_by'      => $this->owner->id,
            'priority'        => 'med',
        ]);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$taskWithoutSprint->id}", ['sprint_id' => $sprint2->id])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id'        => $taskWithoutSprint->id,
            'sprint_id' => $sprint2->id,
        ]);
    }

    public function test_task_can_be_removed_from_sprint(): void
    {
        $this->actingAs($this->owner)
            ->patch("/tasks/{$this->task->id}", ['sprint_id' => null])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id'        => $this->task->id,
            'sprint_id' => null,
        ]);
    }

    public function test_task_can_be_moved_between_sprints(): void
    {
        $sprint2 = Sprint::create([
            'project_id' => $this->project->id,
            'name'       => 'Sprint 2',
            'locked'     => false,
        ]);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$this->task->id}", ['sprint_id' => $sprint2->id])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id'        => $this->task->id,
            'sprint_id' => $sprint2->id,
        ]);
    }

    public function test_sprint_update_creates_audit_log(): void
    {
        $sprint2 = Sprint::create([
            'project_id' => $this->project->id,
            'name'       => 'Sprint 2',
            'locked'     => false,
        ]);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$this->task->id}", ['sprint_id' => $sprint2->id]);

        $this->assertDatabaseHas('audit_logs', [
            'task_id' => $this->task->id,
            'action'  => 'task.sprint_changed',
        ]);
    }

    public function test_task_sprint_update_requires_valid_sprint(): void
    {
        $this->actingAs($this->owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->patch("/tasks/{$this->task->id}", ['sprint_id' => 99999])
            ->assertUnprocessable();
    }

    // -----------------------------------------------------------------------
    // Tags update
    // -----------------------------------------------------------------------

    public function test_task_tags_can_be_set(): void
    {
        $this->actingAs($this->owner)
            ->patch("/tasks/{$this->task->id}", ['tags' => ['frontend', 'backend']])
            ->assertRedirect();

        $this->task->refresh();
        $this->assertEquals(['frontend', 'backend'], $this->task->tags);
    }

    public function test_task_tags_can_be_added_to_existing_tags(): void
    {
        $this->task->update(['tags' => ['frontend']]);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$this->task->id}", ['tags' => ['frontend', 'backend']]);

        $this->task->refresh();
        $this->assertContains('frontend', $this->task->tags);
        $this->assertContains('backend', $this->task->tags);
    }

    public function test_task_tags_can_be_cleared(): void
    {
        $this->task->update(['tags' => ['frontend', 'design']]);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$this->task->id}", ['tags' => []])
            ->assertRedirect();

        $this->task->refresh();
        $this->assertEmpty($this->task->tags);
    }

    public function test_task_tags_update_creates_audit_log(): void
    {
        $this->actingAs($this->owner)
            ->patch("/tasks/{$this->task->id}", ['tags' => ['frontend', 'bug']]);

        $this->assertDatabaseHas('audit_logs', [
            'task_id' => $this->task->id,
            'action'  => 'task.tags_updated',
        ]);
    }

    public function test_task_tags_must_be_strings(): void
    {
        $this->actingAs($this->owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->patch("/tasks/{$this->task->id}", ['tags' => [123, 'valid']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tags.0']);
    }

    // -----------------------------------------------------------------------
    // Subtask creation
    // -----------------------------------------------------------------------

    public function test_subtask_can_be_created_with_default_priority(): void
    {
        $this->actingAs($this->owner)
            ->post("/tasks/{$this->task->id}/subtasks", ['title' => 'My subtask'])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'title'          => 'My subtask',
            'parent_task_id' => $this->task->id,
            'priority'       => 'med',
            'project_id'     => $this->task->project_id,
            'sprint_id'      => $this->task->sprint_id,
        ]);
    }

    public function test_subtask_can_be_created_with_explicit_priority(): void
    {
        $this->actingAs($this->owner)
            ->post("/tasks/{$this->task->id}/subtasks", [
                'title'    => 'Urgent subtask',
                'priority' => 'urgent',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'title'          => 'Urgent subtask',
            'parent_task_id' => $this->task->id,
            'priority'       => 'urgent',
        ]);
    }

    public function test_subtask_inherits_parent_project_and_sprint(): void
    {
        $this->actingAs($this->owner)
            ->post("/tasks/{$this->task->id}/subtasks", ['title' => 'Child task']);

        $subtask = Task::where('parent_task_id', $this->task->id)->first();
        $this->assertEquals($this->task->project_id, $subtask->project_id);
        $this->assertEquals($this->task->sprint_id, $subtask->sprint_id);
        $this->assertEquals($this->task->board_column_id, $subtask->board_column_id);
    }

    public function test_subtask_creation_creates_audit_log(): void
    {
        $this->actingAs($this->owner)
            ->post("/tasks/{$this->task->id}/subtasks", ['title' => 'Audit subtask']);

        $this->assertDatabaseHas('audit_logs', [
            'task_id' => $this->task->id,
            'action'  => 'task.subtask_added',
        ]);
    }

    public function test_subtask_title_is_required(): void
    {
        $this->actingAs($this->owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->post("/tasks/{$this->task->id}/subtasks", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_subtask_priority_must_be_valid_value(): void
    {
        $this->actingAs($this->owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->post("/tasks/{$this->task->id}/subtasks", [
                'title'    => 'Bad priority',
                'priority' => 'invalid',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['priority']);
    }

    public function test_subtask_gets_sequential_key(): void
    {
        $this->actingAs($this->owner)
            ->post("/tasks/{$this->task->id}/subtasks", ['title' => 'Sub 1']);

        $subtask = Task::where('parent_task_id', $this->task->id)->first();
        $this->assertStringStartsWith('TST-', $subtask->key);
    }

    // -----------------------------------------------------------------------
    // Subtask deletion
    // -----------------------------------------------------------------------

    public function test_subtask_can_be_deleted(): void
    {
        $subtask = Task::create([
            'key'             => 'TST-2',
            'title'           => 'Subtask to delete',
            'project_id'      => $this->project->id,
            'sprint_id'       => $this->sprint->id,
            'board_column_id' => $this->column->id,
            'parent_task_id'  => $this->task->id,
            'created_by'      => $this->owner->id,
            'priority'        => 'med',
        ]);

        $this->actingAs($this->owner)
            ->delete("/tasks/{$subtask->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('tasks', ['id' => $subtask->id]);
    }

    public function test_deleting_subtask_does_not_delete_parent(): void
    {
        $subtask = Task::create([
            'key'             => 'TST-2',
            'title'           => 'Child',
            'project_id'      => $this->project->id,
            'sprint_id'       => $this->sprint->id,
            'board_column_id' => $this->column->id,
            'parent_task_id'  => $this->task->id,
            'created_by'      => $this->owner->id,
            'priority'        => 'low',
        ]);

        $this->actingAs($this->owner)
            ->delete("/tasks/{$subtask->id}");

        $this->assertDatabaseHas('tasks', ['id' => $this->task->id]);
    }

    // -----------------------------------------------------------------------
    // Authorization
    // -----------------------------------------------------------------------

    public function test_task_update_requires_authentication(): void
    {
        $this->patch("/tasks/{$this->task->id}", ['tags' => ['x']])
            ->assertRedirect('/login');
    }

    public function test_subtask_store_requires_authentication(): void
    {
        $this->post("/tasks/{$this->task->id}/subtasks", ['title' => 'Sub'])
            ->assertRedirect('/login');
    }

    public function test_task_delete_requires_authentication(): void
    {
        $this->delete("/tasks/{$this->task->id}")
            ->assertRedirect('/login');
    }

    public function test_non_member_cannot_update_task(): void
    {
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->patch("/tasks/{$this->task->id}", ['tags' => ['x']])
            ->assertForbidden();
    }

    public function test_non_member_cannot_create_subtask(): void
    {
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->post("/tasks/{$this->task->id}/subtasks", ['title' => 'Sub'])
            ->assertForbidden();
    }

    public function test_non_member_cannot_delete_task(): void
    {
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->delete("/tasks/{$this->task->id}")
            ->assertForbidden();
    }
}
