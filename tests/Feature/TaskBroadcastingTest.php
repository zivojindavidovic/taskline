<?php

namespace Tests\Feature;

use App\Events\TaskActivityRecorded;
use App\Events\TaskCreated;
use App\Events\TaskUpdated;
use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TaskBroadcastingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $alice;
    private User $bob;
    private Workspace $workspace;
    private Project $project;
    private Sprint $sprint;
    private BoardColumn $column;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['name' => 'Owner']);
        $this->alice = User::factory()->create(['name' => 'Alice']);
        $this->bob   = User::factory()->create(['name' => 'Bob']);

        $this->workspace = Workspace::create([
            'name'     => 'WS',
            'owner_id' => $this->owner->id,
            'color'    => '#4f46e5',
        ]);
        $this->workspace->users()->attach([
            $this->owner->id => ['role' => 'owner'],
            $this->alice->id => ['role' => 'member'],
            $this->bob->id   => ['role' => 'member'],
        ]);
        $this->owner->update(['current_workspace_id' => $this->workspace->id]);

        $this->project = Project::create([
            'name'         => 'Proj',
            'key'          => 'PRJ',
            'color'        => '#4f46e5',
            'owner_id'     => $this->owner->id,
            'workspace_id' => $this->workspace->id,
        ]);
        $this->project->members()->attach([
            $this->owner->id => ['role' => 'owner'],
            $this->alice->id => ['role' => 'member'],
            $this->bob->id   => ['role' => 'member'],
        ]);

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
            'key'             => "PRJ-{$counter}",
            'title'           => "Task {$counter}",
            'project_id'      => $this->project->id,
            'sprint_id'       => $this->sprint->id,
            'board_column_id' => $this->column->id,
            'created_by'      => $this->owner->id,
            'priority'        => 'med',
        ], $attrs));
    }

    // ---------- TaskCreated ----------

    public function test_creating_task_via_web_route_dispatches_task_created_event(): void
    {
        Event::fake([TaskCreated::class, TaskUpdated::class, TaskActivityRecorded::class]);

        $this->actingAs($this->owner)
            ->post('/tasks', [
                'project_id'      => $this->project->id,
                'sprint_id'       => $this->sprint->id,
                'board_column_id' => $this->column->id,
                'title'           => 'A brand new task',
                'priority'        => 'med',
            ])
            ->assertRedirect();

        Event::assertDispatched(TaskCreated::class, function (TaskCreated $event) {
            return $event->task->title === 'A brand new task'
                && (int) $event->task->project_id === (int) $this->project->id;
        });
    }

    public function test_task_created_event_broadcasts_on_project_channel(): void
    {
        $task = $this->makeTask();
        $event = new TaskCreated($task);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertSame('private-project.' . $this->project->id, $channels[0]->name);
    }

    public function test_task_created_broadcast_payload_includes_loaded_relations(): void
    {
        $task = $this->makeTask();
        $task->assignees()->attach($this->alice->id);
        $task->refresh()->load([
            'assignee:id,name,email,avatar_color',
            'assignees:id,name,email,avatar_color',
            'boardColumn:id,name,color',
            'sprint:id,name,status',
        ]);

        $payload = (new TaskCreated($task))->broadcastWith();

        $this->assertArrayHasKey('task', $payload);
        $this->assertSame($task->id, $payload['task']['id']);
        $this->assertArrayHasKey('assignees', $payload['task']);
        $this->assertArrayHasKey('board_column', $payload['task']);
        $this->assertArrayHasKey('sprint', $payload['task']);
    }

    public function test_task_created_event_is_only_dispatched_for_actual_top_level_creates(): void
    {
        Event::fake([TaskCreated::class]);

        // Updating an existing task must not fire TaskCreated.
        $task = $this->makeTask();
        $this->actingAs($this->owner)
            ->patch("/tasks/{$task->uuid}", ['title' => 'Renamed']);

        Event::assertNotDispatched(TaskCreated::class);
    }

    // ---------- TaskActivityRecorded ----------

    public function test_renaming_task_dispatches_activity_recorded_event(): void
    {
        Event::fake([TaskActivityRecorded::class]);
        $task = $this->makeTask(['title' => 'Old']);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$task->uuid}", ['title' => 'New'])
            ->assertRedirect();

        Event::assertDispatched(TaskActivityRecorded::class, function (TaskActivityRecorded $e) use ($task) {
            return $e->projectId === (int) $task->project_id
                && $e->activity->field === 'title'
                && $e->activity->task_id === $task->id;
        });
    }

    public function test_changing_priority_dispatches_activity_recorded_event(): void
    {
        Event::fake([TaskActivityRecorded::class]);
        $task = $this->makeTask(['priority' => 'med']);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$task->uuid}", ['priority' => 'urgent']);

        Event::assertDispatched(TaskActivityRecorded::class, fn (TaskActivityRecorded $e) =>
            $e->activity->field === 'priority' && $e->activity->task_id === $task->id
        );
    }

    public function test_assigning_users_dispatches_activity_recorded_event(): void
    {
        Event::fake([TaskActivityRecorded::class]);
        $task = $this->makeTask();

        $this->actingAs($this->owner)
            ->patch("/tasks/{$task->uuid}", ['assignee_ids' => [$this->alice->id]]);

        Event::assertDispatched(TaskActivityRecorded::class, fn (TaskActivityRecorded $e) =>
            $e->activity->field === 'assignees' && $e->activity->task_id === $task->id
        );
    }

    public function test_completing_task_dispatches_activity_recorded_event(): void
    {
        Event::fake([TaskActivityRecorded::class]);
        $task = $this->makeTask();

        $this->actingAs($this->owner)
            ->post("/tasks/{$task->uuid}/complete");

        Event::assertDispatched(TaskActivityRecorded::class, fn (TaskActivityRecorded $e) =>
            $e->activity->field === 'status'
            && $e->activity->task_id === $task->id
            && $e->activity->subtask_id === null
        );
    }

    public function test_reopening_task_dispatches_activity_recorded_event(): void
    {
        Event::fake([TaskActivityRecorded::class]);
        $task = $this->makeTask(['completed' => true, 'completed_at' => now(), 'completed_by' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->post("/tasks/{$task->uuid}/uncomplete");

        Event::assertDispatched(TaskActivityRecorded::class, fn (TaskActivityRecorded $e) =>
            $e->activity->field === 'status' && $e->activity->task_id === $task->id
        );
    }

    public function test_subtask_change_dispatches_activity_recorded_event_with_subtask_id(): void
    {
        Event::fake([TaskActivityRecorded::class]);
        $parent = $this->makeTask();
        $subtask = Task::create([
            'key'             => 'PRJ-S1',
            'title'           => 'Sub original',
            'project_id'      => $this->project->id,
            'sprint_id'       => $this->sprint->id,
            'board_column_id' => $this->column->id,
            'parent_task_id'  => $parent->id,
            'created_by'      => $this->owner->id,
            'priority'        => 'med',
        ]);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$parent->uuid}/subtasks/{$subtask->uuid}", ['title' => 'Sub new']);

        Event::assertDispatched(TaskActivityRecorded::class, fn (TaskActivityRecorded $e) =>
            $e->activity->field === 'title'
            && $e->activity->task_id === $parent->id
            && $e->activity->subtask_id === $subtask->id
        );
    }

    public function test_multi_field_update_dispatches_one_event_per_changed_field(): void
    {
        Event::fake([TaskActivityRecorded::class]);
        $task = $this->makeTask(['title' => 'A', 'priority' => 'med']);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$task->uuid}", [
                'title'    => 'B',
                'priority' => 'urgent',
            ]);

        Event::assertDispatchedTimes(TaskActivityRecorded::class, 2);
    }

    public function test_no_op_update_does_not_dispatch_activity_event(): void
    {
        Event::fake([TaskActivityRecorded::class]);
        $task = $this->makeTask(['title' => 'Same']);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$task->uuid}", ['title' => 'Same']);

        Event::assertNotDispatched(TaskActivityRecorded::class);
    }

    public function test_cross_project_move_dispatches_activity_on_destination_project_channel(): void
    {
        $other = Project::create([
            'name' => 'Other', 'key' => 'OTH', 'color' => '#000',
            'owner_id' => $this->owner->id, 'workspace_id' => $this->workspace->id,
        ]);
        $other->members()->attach($this->owner->id, ['role' => 'owner']);
        BoardColumn::create(['project_id' => $other->id, 'name' => 'Todo', 'color' => '#000', 'position' => 0]);

        Event::fake([TaskActivityRecorded::class]);
        $task = $this->makeTask();

        $this->actingAs($this->owner)
            ->patch("/tasks/{$task->uuid}", ['project_id' => $other->id]);

        Event::assertDispatched(TaskActivityRecorded::class, fn (TaskActivityRecorded $e) =>
            $e->activity->field === 'project' && $e->projectId === $other->id
        );
    }

    public function test_activity_recorded_event_broadcasts_on_project_channel(): void
    {
        $task = $this->makeTask();
        $activity = \App\Models\TaskActivity::create([
            'task_id'    => $task->id,
            'subtask_id' => null,
            'user_id'    => $this->owner->id,
            'field'      => 'title',
            'from_value' => ['value' => 'a'],
            'to_value'   => ['value' => 'b'],
        ]);

        $event = new TaskActivityRecorded($activity, $this->project->id);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertSame('private-project.' . $this->project->id, $channels[0]->name);
    }

    public function test_activity_recorded_event_payload_includes_user_and_subtask(): void
    {
        $parent = $this->makeTask();
        $subtask = Task::create([
            'key'             => 'PRJ-S2',
            'title'           => 'Sub',
            'project_id'      => $this->project->id,
            'sprint_id'       => $this->sprint->id,
            'board_column_id' => $this->column->id,
            'parent_task_id'  => $parent->id,
            'created_by'      => $this->owner->id,
            'priority'        => 'med',
        ]);
        $activity = \App\Models\TaskActivity::create([
            'task_id'    => $parent->id,
            'subtask_id' => $subtask->id,
            'user_id'    => $this->owner->id,
            'field'      => 'title',
            'from_value' => ['value' => 'a'],
            'to_value'   => ['value' => 'b'],
        ]);

        $payload = (new TaskActivityRecorded($activity, $this->project->id))->broadcastWith();

        $this->assertArrayHasKey('activity', $payload);
        $this->assertSame($activity->id, $payload['activity']['id']);
        $this->assertSame($this->owner->id, $payload['activity']['user']['id']);
        $this->assertSame($subtask->id, $payload['activity']['subtask']['id']);
    }
}
