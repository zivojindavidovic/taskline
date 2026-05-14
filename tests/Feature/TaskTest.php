<?php

namespace Tests\Feature;

use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Project $project;
    private Sprint $sprint;
    private BoardColumn $column;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->project = Project::create([
            'name'     => 'Test Project',
            'key'      => 'TST',
            'color'    => '#4f46e5',
            'owner_id' => $this->owner->id,
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
        return Task::create(array_merge([
            'key'             => 'TST-1',
            'title'           => 'Test Task',
            'project_id'      => $this->project->id,
            'sprint_id'       => $this->sprint->id,
            'board_column_id' => $this->column->id,
            'created_by'      => $this->owner->id,
            'priority'        => 'med',
        ], $attrs));
    }

    public function test_member_can_list_project_tasks(): void
    {
        $this->createTask(['key' => 'TST-1']);
        $this->createTask(['key' => 'TST-2', 'title' => 'Another task']);

        $this->actingAs($this->owner)
            ->getJson("/api/projects/{$this->project->id}/tasks")
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_tasks_can_be_filtered_by_sprint(): void
    {
        $sprint2 = Sprint::create(['project_id' => $this->project->id, 'name' => 'Sprint 2']);
        $this->createTask(['key' => 'TST-1', 'sprint_id' => $this->sprint->id]);
        $this->createTask(['key' => 'TST-2', 'sprint_id' => $sprint2->id, 'title' => 'Other sprint']);

        $this->actingAs($this->owner)
            ->getJson("/api/projects/{$this->project->id}/tasks?sprint_id={$this->sprint->id}")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['title' => 'Test Task']);
    }

    public function test_member_can_create_a_task(): void
    {
        $response = $this->actingAs($this->owner)->postJson(
            "/api/projects/{$this->project->id}/tasks",
            [
                'title'           => 'Build login screen',
                'priority'        => 'high',
                'sprint_id'       => $this->sprint->id,
                'board_column_id' => $this->column->id,
                'tags'            => ['frontend', 'design'],
            ]
        );

        $response->assertCreated()
            ->assertJsonFragment(['title' => 'Build login screen'])
            ->assertJsonFragment(['key' => 'TST-1']);

        $this->assertDatabaseHas('tasks', [
            'title'      => 'Build login screen',
            'project_id' => $this->project->id,
        ]);
    }

    public function test_task_creation_generates_correct_key(): void
    {
        $this->createTask(['key' => 'TST-1']);  // existing
        $this->createTask(['key' => 'TST-2']);  // existing

        $response = $this->actingAs($this->owner)->postJson(
            "/api/projects/{$this->project->id}/tasks",
            [
                'title'     => 'Third task',
                'sprint_id' => $this->sprint->id,
            ]
        );

        $response->assertCreated()->assertJsonFragment(['key' => 'TST-3']);
    }

    public function test_task_creation_requires_title(): void
    {
        $this->actingAs($this->owner)
            ->postJson("/api/projects/{$this->project->id}/tasks", ['sprint_id' => $this->sprint->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_cannot_create_task_in_locked_sprint(): void
    {
        $this->sprint->update(['locked' => true]);

        $this->actingAs($this->owner)
            ->postJson("/api/projects/{$this->project->id}/tasks", [
                'title'     => 'New task',
                'sprint_id' => $this->sprint->id,
            ])
            ->assertUnprocessable();
    }

    public function test_non_member_cannot_create_task(): void
    {
        $other = User::factory()->create();

        $this->actingAs($other)
            ->postJson("/api/projects/{$this->project->id}/tasks", [
                'title'     => 'Hacked task',
                'sprint_id' => $this->sprint->id,
            ])
            ->assertForbidden();
    }

    public function test_member_can_view_a_task(): void
    {
        $task = $this->createTask();

        $this->actingAs($this->owner)
            ->getJson("/api/projects/{$this->project->id}/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonFragment(['title' => 'Test Task']);
    }

    public function test_member_can_update_task_title(): void
    {
        $task = $this->createTask();

        $this->actingAs($this->owner)
            ->patchJson("/api/projects/{$this->project->id}/tasks/{$task->id}", [
                'title' => 'Updated title',
            ])
            ->assertOk()
            ->assertJsonFragment(['title' => 'Updated title']);
    }

    public function test_member_can_move_task_to_different_column(): void
    {
        $doneColumn = BoardColumn::create([
            'project_id' => $this->project->id,
            'name'       => 'Done',
            'color'      => '#16a34a',
            'position'   => 3,
        ]);
        $task = $this->createTask();

        $this->actingAs($this->owner)
            ->patchJson("/api/projects/{$this->project->id}/tasks/{$task->id}", [
                'board_column_id' => $doneColumn->id,
            ])
            ->assertOk()
            ->assertJsonFragment(['board_column_id' => $doneColumn->id]);
    }

    public function test_member_can_complete_a_task(): void
    {
        $task = $this->createTask();

        $this->actingAs($this->owner)
            ->patchJson("/api/projects/{$this->project->id}/tasks/{$task->id}", [
                'completed' => true,
            ])
            ->assertOk()
            ->assertJsonFragment(['completed' => true]);

        $this->assertDatabaseHas('tasks', [
            'id'           => $task->id,
            'completed'    => true,
            'completed_by' => $this->owner->id,
        ]);
        $this->assertNotNull(Task::find($task->id)->completed_at);
    }

    public function test_member_can_reopen_a_completed_task(): void
    {
        $task = $this->createTask(['completed' => true, 'completed_by' => $this->owner->id]);
        $task->update(['completed_at' => now()]);

        $this->actingAs($this->owner)
            ->patchJson("/api/projects/{$this->project->id}/tasks/{$task->id}", [
                'completed' => false,
            ])
            ->assertOk()
            ->assertJsonFragment(['completed' => false]);

        $this->assertNull(Task::find($task->id)->completed_at);
    }

    public function test_cannot_update_task_in_locked_sprint(): void
    {
        $this->sprint->update(['locked' => true]);
        $task = $this->createTask();

        $this->actingAs($this->owner)
            ->patchJson("/api/projects/{$this->project->id}/tasks/{$task->id}", [
                'title' => 'Attempted edit',
            ])
            ->assertUnprocessable();
    }

    public function test_member_can_delete_task(): void
    {
        $task = $this->createTask();

        $this->actingAs($this->owner)
            ->deleteJson("/api/projects/{$this->project->id}/tasks/{$task->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_cannot_delete_task_in_locked_sprint(): void
    {
        $this->sprint->update(['locked' => true]);
        $task = $this->createTask();

        $this->actingAs($this->owner)
            ->deleteJson("/api/projects/{$this->project->id}/tasks/{$task->id}")
            ->assertUnprocessable();
    }

    public function test_task_update_creates_audit_log(): void
    {
        $task = $this->createTask();

        $this->actingAs($this->owner)
            ->patchJson("/api/projects/{$this->project->id}/tasks/{$task->id}", [
                'title' => 'Updated',
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'task_id' => $task->id,
            'action'  => 'task.updated',
        ]);
    }

    public function test_task_completion_creates_audit_log(): void
    {
        $task = $this->createTask();

        $this->actingAs($this->owner)
            ->patchJson("/api/projects/{$this->project->id}/tasks/{$task->id}", [
                'completed' => true,
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'task_id' => $task->id,
            'action'  => 'task.completed',
        ]);
    }

    public function test_broadcasting_events_are_fired_on_task_creation(): void
    {
        Event::fake();

        $this->actingAs($this->owner)->postJson(
            "/api/projects/{$this->project->id}/tasks",
            [
                'title'     => 'Broadcasted task',
                'sprint_id' => $this->sprint->id,
            ]
        );

        Event::assertDispatched(\App\Events\TaskCreated::class);
    }

    public function test_broadcasting_events_are_fired_on_task_update(): void
    {
        Event::fake();
        $task = $this->createTask();

        $this->actingAs($this->owner)->patchJson(
            "/api/projects/{$this->project->id}/tasks/{$task->id}",
            ['title' => 'Updated']
        );

        Event::assertDispatched(\App\Events\TaskUpdated::class);
    }

    public function test_broadcasting_events_are_fired_on_task_deletion(): void
    {
        Event::fake();
        $task = $this->createTask();

        $this->actingAs($this->owner)->deleteJson(
            "/api/projects/{$this->project->id}/tasks/{$task->id}"
        );

        Event::assertDispatched(\App\Events\TaskDeleted::class);
    }
}
