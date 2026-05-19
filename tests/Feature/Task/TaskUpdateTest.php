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

    public function test_moving_task_to_another_project_places_it_in_first_column(): void
    {
        $otherProject = Project::create([
            'name'         => 'Other Project',
            'key'          => 'OTH',
            'color'        => '#000',
            'owner_id'     => $this->owner->id,
            'workspace_id' => $this->workspace->id,
        ]);
        $otherProject->members()->attach($this->owner->id, ['role' => 'owner']);

        // Seed columns out of order so we can confirm ordering by position
        // (not insert order) picks the leftmost column.
        $doing = BoardColumn::create([
            'project_id' => $otherProject->id,
            'name'       => 'Doing',
            'color'      => '#fff',
            'position'   => 1,
        ]);
        $todo = BoardColumn::create([
            'project_id' => $otherProject->id,
            'name'       => 'Todo',
            'color'      => '#fff',
            'position'   => 0,
        ]);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$this->task->id}", ['project_id' => $otherProject->id])
            ->assertRedirect();

        $this->task->refresh();
        $this->assertEquals($otherProject->id, $this->task->project_id);
        $this->assertEquals($todo->id, $this->task->board_column_id);
        $this->assertNotEquals($doing->id, $this->task->board_column_id);
    }

    public function test_moving_task_to_another_project_moves_it_to_backlog(): void
    {
        $otherProject = Project::create([
            'name'         => 'Other Project',
            'key'          => 'OTH',
            'color'        => '#000',
            'owner_id'     => $this->owner->id,
            'workspace_id' => $this->workspace->id,
        ]);
        $otherProject->members()->attach($this->owner->id, ['role' => 'owner']);
        BoardColumn::create([
            'project_id' => $otherProject->id,
            'name'       => 'Todo',
            'color'      => '#fff',
            'position'   => 0,
        ]);

        // Task starts inside Sprint 1 of the source project.
        $this->assertNotNull($this->task->sprint_id);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$this->task->id}", ['project_id' => $otherProject->id])
            ->assertRedirect();

        $this->task->refresh();
        $this->assertEquals($otherProject->id, $this->task->project_id);
        $this->assertNull($this->task->sprint_id);
    }

    public function test_moving_task_to_project_with_no_columns_clears_column(): void
    {
        $emptyProject = Project::create([
            'name'         => 'Empty Project',
            'key'          => 'EMP',
            'color'        => '#000',
            'owner_id'     => $this->owner->id,
            'workspace_id' => $this->workspace->id,
        ]);
        $emptyProject->members()->attach($this->owner->id, ['role' => 'owner']);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$this->task->id}", ['project_id' => $emptyProject->id])
            ->assertRedirect();

        $this->task->refresh();
        $this->assertEquals($emptyProject->id, $this->task->project_id);
        $this->assertNull($this->task->board_column_id);
        $this->assertNull($this->task->sprint_id);
    }

    public function test_first_column_is_resolved_per_destination_project(): void
    {
        // Source project's column at position 0 should not "shadow" the
        // destination's column at position 0 — we resolve per project_id.
        $otherProject = Project::create([
            'name'         => 'Other Project',
            'key'          => 'OTH',
            'color'        => '#000',
            'owner_id'     => $this->owner->id,
            'workspace_id' => $this->workspace->id,
        ]);
        $otherProject->members()->attach($this->owner->id, ['role' => 'owner']);
        $otherTodo = BoardColumn::create([
            'project_id' => $otherProject->id,
            'name'       => 'Todo',
            'color'      => '#fff',
            'position'   => 0,
        ]);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$this->task->id}", ['project_id' => $otherProject->id]);

        $this->task->refresh();
        $this->assertEquals($otherTodo->id, $this->task->board_column_id);
        $this->assertNotEquals($this->column->id, $this->task->board_column_id);
    }

    public function test_same_project_update_preserves_column_and_sprint(): void
    {
        $originalColumnId = $this->task->board_column_id;
        $originalSprintId = $this->task->sprint_id;

        // Issue a same-project update touching only the title — confirm the
        // cross-project reset logic does not fire when the project is unchanged.
        $this->actingAs($this->owner)
            ->patch("/tasks/{$this->task->id}", [
                'project_id' => $this->project->id,
                'title'      => 'Renamed in place',
            ])
            ->assertRedirect();

        $this->task->refresh();
        $this->assertEquals($originalColumnId, $this->task->board_column_id);
        $this->assertEquals($originalSprintId, $this->task->sprint_id);
        $this->assertEquals('Renamed in place', $this->task->title);
    }

    // -----------------------------------------------------------------------
    // Move to Backlog (no project change)
    // -----------------------------------------------------------------------

    public function test_task_can_be_moved_to_backlog_without_changing_project(): void
    {
        $this->actingAs($this->owner)
            ->patch("/tasks/{$this->task->id}", ['sprint_id' => null])
            ->assertRedirect();

        $this->task->refresh();
        $this->assertEquals($this->project->id, $this->task->project_id);
        // Backlog is just "no sprint" — the task stays in the same column.
        $this->assertEquals($this->column->id, $this->task->board_column_id);
        $this->assertNull($this->task->sprint_id);
    }

    public function test_backlog_move_preserves_current_column(): void
    {
        // Put the task in a non-first column so we can prove the column is
        // not touched by the backlog move.
        $doing = BoardColumn::create([
            'project_id' => $this->project->id,
            'name'       => 'Doing',
            'color'      => '#aaa',
            'position'   => 1,
        ]);
        $this->task->update(['board_column_id' => $doing->id]);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$this->task->id}", ['sprint_id' => null]);

        $this->task->refresh();
        $this->assertEquals($doing->id, $this->task->board_column_id);
        $this->assertNull($this->task->sprint_id);
    }

    public function test_backlog_move_logs_dedicated_audit_action(): void
    {
        $originalSprintId = $this->task->sprint_id;

        $this->actingAs($this->owner)
            ->patch("/tasks/{$this->task->id}", ['sprint_id' => null]);

        $log = \App\Models\AuditLog::where('task_id', $this->task->id)
            ->where('action', 'task.moved_to_backlog')
            ->latest('id')
            ->first();

        $this->assertNotNull($log, 'Expected a task.moved_to_backlog audit log entry.');
        $this->assertEquals($originalSprintId, $log->meta['from_sprint_id']);
        $this->assertArrayNotHasKey('from_column_id', $log->meta);
    }

    public function test_backlog_move_is_idempotent_when_task_already_in_backlog(): void
    {
        // Task starts in a column with no sprint — already in backlog.
        $backlogTask = Task::create([
            'key'             => 'TST-9',
            'title'           => 'Already in backlog',
            'project_id'      => $this->project->id,
            'sprint_id'       => null,
            'board_column_id' => $this->column->id,
            'created_by'      => $this->owner->id,
            'priority'        => 'med',
        ]);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$backlogTask->id}", ['sprint_id' => null])
            ->assertRedirect();

        $backlogTask->refresh();
        $this->assertEquals($this->project->id, $backlogTask->project_id);
        $this->assertEquals($this->column->id, $backlogTask->board_column_id);
        $this->assertNull($backlogTask->sprint_id);

        // A null-to-null sprint update is not a backlog move — sprint didn't
        // actually transition. No moved_to_backlog audit should be recorded.
        $this->assertDatabaseMissing('audit_logs', [
            'task_id' => $backlogTask->id,
            'action'  => 'task.moved_to_backlog',
        ]);
    }

    public function test_cross_project_move_is_not_logged_as_backlog_move(): void
    {
        // Cross-project moves null the destination sprint as a side effect, but
        // the user intent was "change project". The audit log must reflect that.
        $emptyProject = Project::create([
            'name'         => 'Empty Project',
            'key'          => 'EMP',
            'color'        => '#000',
            'owner_id'     => $this->owner->id,
            'workspace_id' => $this->workspace->id,
        ]);
        $emptyProject->members()->attach($this->owner->id, ['role' => 'owner']);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$this->task->id}", ['project_id' => $emptyProject->id]);

        $this->assertDatabaseHas('audit_logs', [
            'task_id' => $this->task->id,
            'action'  => 'task.project_changed',
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'task_id' => $this->task->id,
            'action'  => 'task.moved_to_backlog',
        ]);
    }

    // -----------------------------------------------------------------------

    public function test_cross_project_move_records_from_and_to_in_audit_meta(): void
    {
        $otherProject = Project::create([
            'name'         => 'Other Project',
            'key'          => 'OTH',
            'color'        => '#000',
            'owner_id'     => $this->owner->id,
            'workspace_id' => $this->workspace->id,
        ]);
        $otherProject->members()->attach($this->owner->id, ['role' => 'owner']);
        BoardColumn::create([
            'project_id' => $otherProject->id,
            'name'       => 'Todo',
            'color'      => '#fff',
            'position'   => 0,
        ]);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$this->task->id}", ['project_id' => $otherProject->id]);

        $log = \App\Models\AuditLog::where('task_id', $this->task->id)
            ->where('action', 'task.project_changed')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($this->project->id, $log->meta['from_project_id']);
        $this->assertEquals($otherProject->id, $log->meta['to_project_id']);
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
    // Locked sprint guard — no new tasks may be assigned to a locked sprint
    // -----------------------------------------------------------------------

    public function test_task_cannot_be_added_to_locked_sprint(): void
    {
        $lockedSprint = Sprint::create([
            'project_id' => $this->project->id,
            'name'       => 'Locked Sprint',
            'locked'     => true,
        ]);

        $backlogTask = Task::create([
            'key'             => 'TST-2',
            'title'           => 'Backlog task',
            'project_id'      => $this->project->id,
            'sprint_id'       => null,
            'board_column_id' => $this->column->id,
            'created_by'      => $this->owner->id,
            'priority'        => 'med',
        ]);

        $this->actingAs($this->owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->patch("/tasks/{$backlogTask->id}", ['sprint_id' => $lockedSprint->id])
            ->assertStatus(422);

        $this->assertDatabaseHas('tasks', [
            'id'        => $backlogTask->id,
            'sprint_id' => null,
        ]);
    }

    public function test_task_cannot_be_moved_from_one_sprint_to_locked_sprint(): void
    {
        $lockedSprint = Sprint::create([
            'project_id' => $this->project->id,
            'name'       => 'Locked Sprint',
            'locked'     => true,
        ]);

        $this->actingAs($this->owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->patch("/tasks/{$this->task->id}", ['sprint_id' => $lockedSprint->id])
            ->assertStatus(422);

        $this->assertDatabaseHas('tasks', [
            'id'        => $this->task->id,
            'sprint_id' => $this->sprint->id,
        ]);
    }

    public function test_task_can_be_removed_from_locked_sprint(): void
    {
        // Lock the sprint AFTER the task is already in it. Removing the task
        // back to backlog is allowed (the guard only blocks adding).
        $this->sprint->update(['locked' => true]);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$this->task->id}", ['sprint_id' => null])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id'        => $this->task->id,
            'sprint_id' => null,
        ]);
    }

    public function test_no_op_sprint_update_does_not_trigger_lock_guard(): void
    {
        // Setting sprint_id to the SAME sprint should be a no-op even when
        // that sprint is locked — the guard targets transitions INTO a sprint,
        // not idempotent payloads.
        $this->sprint->update(['locked' => true]);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$this->task->id}", ['sprint_id' => $this->sprint->id])
            ->assertRedirect();
    }

    public function test_task_cannot_be_created_in_locked_sprint(): void
    {
        $lockedSprint = Sprint::create([
            'project_id' => $this->project->id,
            'name'       => 'Locked Sprint',
            'locked'     => true,
        ]);

        $this->actingAs($this->owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/tasks', [
                'project_id'      => $this->project->id,
                'sprint_id'       => $lockedSprint->id,
                'board_column_id' => $this->column->id,
                'title'           => 'New task',
                'priority'        => 'med',
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('tasks', [
            'title' => 'New task',
        ]);
    }

    public function test_task_can_be_created_in_backlog_when_locked_sprint_exists(): void
    {
        Sprint::create([
            'project_id' => $this->project->id,
            'name'       => 'Locked Sprint',
            'locked'     => true,
        ]);

        $this->actingAs($this->owner)
            ->post('/tasks', [
                'project_id'      => $this->project->id,
                'sprint_id'       => null,
                'board_column_id' => $this->column->id,
                'title'           => 'Backlog task',
                'priority'        => 'med',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'title'     => 'Backlog task',
            'sprint_id' => null,
        ]);
    }

    public function test_task_can_be_created_in_unlocked_sprint_even_when_another_is_locked(): void
    {
        Sprint::create([
            'project_id' => $this->project->id,
            'name'       => 'Locked Sprint',
            'locked'     => true,
        ]);
        $openSprint = Sprint::create([
            'project_id' => $this->project->id,
            'name'       => 'Open Sprint',
            'locked'     => false,
        ]);

        $this->actingAs($this->owner)
            ->post('/tasks', [
                'project_id'      => $this->project->id,
                'sprint_id'       => $openSprint->id,
                'board_column_id' => $this->column->id,
                'title'           => 'Open task',
                'priority'        => 'med',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'title'     => 'Open task',
            'sprint_id' => $openSprint->id,
        ]);
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
