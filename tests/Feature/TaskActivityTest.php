<?php

namespace Tests\Feature;

use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskActivityTest extends TestCase
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
            'name'     => 'Test Workspace',
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
            'name'         => 'Test Project',
            'key'          => 'TST',
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
            'key'             => "TST-{$counter}",
            'title'           => "Task {$counter}",
            'project_id'      => $this->project->id,
            'sprint_id'       => $this->sprint->id,
            'board_column_id' => $this->column->id,
            'created_by'      => $this->owner->id,
            'priority'        => 'med',
        ], $attrs));
    }

    private function activitiesFor(Task $task): \Illuminate\Database\Eloquent\Collection
    {
        return TaskActivity::where('task_id', $task->id)->oldest()->get();
    }

    // ---------- title ----------

    public function test_renaming_records_title_activity_with_from_and_to(): void
    {
        $task = $this->makeTask(['title' => 'Old name']);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$task->id}", ['title' => 'New name'])
            ->assertRedirect();

        $activities = $this->activitiesFor($task);
        $title = $activities->firstWhere('field', 'title');
        $this->assertNotNull($title);
        $this->assertSame('Old name', $title->from_value['value']);
        $this->assertSame('New name', $title->to_value['value']);
        $this->assertSame($this->owner->id, $title->user_id);
    }

    // ---------- description ----------

    public function test_changing_description_records_activity(): void
    {
        $task = $this->makeTask(['description' => null]);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$task->id}", ['description' => 'A long description.'])
            ->assertRedirect();

        $entry = $this->activitiesFor($task)->firstWhere('field', 'description');
        $this->assertNotNull($entry);
        $this->assertSame('', $entry->from_value['value']);
        $this->assertSame('A long description.', $entry->to_value['value']);
    }

    public function test_clearing_description_records_activity(): void
    {
        $task = $this->makeTask(['description' => 'something']);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$task->id}", ['description' => null])
            ->assertRedirect();

        $entry = $this->activitiesFor($task)->firstWhere('field', 'description');
        $this->assertNotNull($entry);
        $this->assertSame('something', $entry->from_value['value']);
        $this->assertSame('', $entry->to_value['value']);
    }

    // ---------- priority ----------

    public function test_changing_priority_records_activity(): void
    {
        $task = $this->makeTask(['priority' => 'med']);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$task->id}", ['priority' => 'urgent'])
            ->assertRedirect();

        $entry = $this->activitiesFor($task)->firstWhere('field', 'priority');
        $this->assertNotNull($entry);
        $this->assertSame('med', $entry->from_value['value']);
        $this->assertSame('urgent', $entry->to_value['value']);
    }

    // ---------- assignees ----------

    public function test_changing_assignees_records_activity_with_names(): void
    {
        $task = $this->makeTask();
        $task->assignees()->attach($this->alice->id);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$task->id}", ['assignee_ids' => [$this->bob->id]])
            ->assertRedirect();

        $entry = $this->activitiesFor($task)->firstWhere('field', 'assignees');
        $this->assertNotNull($entry);
        $this->assertSame([$this->alice->id], $entry->from_value['ids']);
        $this->assertSame([$this->bob->id], $entry->to_value['ids']);
        $this->assertContains('Alice', $entry->from_value['names']);
        $this->assertContains('Bob', $entry->to_value['names']);
    }

    public function test_unchanged_assignees_do_not_record_activity(): void
    {
        $task = $this->makeTask();
        $task->assignees()->attach($this->alice->id);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$task->id}", ['assignee_ids' => [$this->alice->id]])
            ->assertRedirect();

        $this->assertNull($this->activitiesFor($task)->firstWhere('field', 'assignees'));
    }

    // ---------- project ----------

    public function test_changing_project_records_activity_with_project_names(): void
    {
        $other = Project::create([
            'name' => 'Other', 'key' => 'OTH', 'color' => '#000',
            'owner_id' => $this->owner->id, 'workspace_id' => $this->workspace->id,
        ]);
        $other->members()->attach($this->owner->id, ['role' => 'owner']);
        BoardColumn::create(['project_id' => $other->id, 'name' => 'Todo', 'color' => '#000', 'position' => 0]);

        $task = $this->makeTask();

        $this->actingAs($this->owner)
            ->patch("/tasks/{$task->id}", ['project_id' => $other->id])
            ->assertRedirect();

        $entry = $this->activitiesFor($task)->firstWhere('field', 'project');
        $this->assertNotNull($entry);
        $this->assertSame('Test Project', $entry->from_value['name']);
        $this->assertSame('Other', $entry->to_value['name']);
    }

    // ---------- sprint ----------

    public function test_moving_to_backlog_records_sprint_activity(): void
    {
        $task = $this->makeTask();

        $this->actingAs($this->owner)
            ->patch("/tasks/{$task->id}", ['sprint_id' => null])
            ->assertRedirect();

        $entry = $this->activitiesFor($task)->firstWhere('field', 'sprint');
        $this->assertNotNull($entry);
        $this->assertSame('Sprint 1', $entry->from_value['name']);
        $this->assertNull($entry->to_value);
    }

    public function test_moving_from_backlog_to_sprint_records_activity(): void
    {
        $task = $this->makeTask(['sprint_id' => null]);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$task->id}", ['sprint_id' => $this->sprint->id])
            ->assertRedirect();

        $entry = $this->activitiesFor($task)->firstWhere('field', 'sprint');
        $this->assertNotNull($entry);
        $this->assertNull($entry->from_value);
        $this->assertSame('Sprint 1', $entry->to_value['name']);
    }

    // ---------- dates ----------

    public function test_setting_dates_records_two_activities(): void
    {
        $task = $this->makeTask();

        $this->actingAs($this->owner)
            ->patch("/tasks/{$task->id}", [
                'start_date' => '2026-06-01',
                'due_date'   => '2026-06-15',
            ])
            ->assertRedirect();

        $activities = $this->activitiesFor($task);
        $start = $activities->firstWhere('field', 'start_date');
        $due   = $activities->firstWhere('field', 'due_date');
        $this->assertNotNull($start);
        $this->assertNull($start->from_value['value']);
        $this->assertSame('2026-06-01', $start->to_value['value']);
        $this->assertNotNull($due);
        $this->assertSame('2026-06-15', $due->to_value['value']);
    }

    // ---------- tags ----------

    public function test_changing_tags_records_activity(): void
    {
        $task = $this->makeTask(['tags' => ['a']]);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$task->id}", ['tags' => ['a', 'b']])
            ->assertRedirect();

        $entry = $this->activitiesFor($task)->firstWhere('field', 'tags');
        $this->assertNotNull($entry);
        $this->assertEqualsCanonicalizing(['a'], $entry->from_value['value']);
        $this->assertEqualsCanonicalizing(['a', 'b'], $entry->to_value['value']);
    }

    // ---------- status (complete / uncomplete) ----------

    public function test_completing_task_records_status_activity(): void
    {
        $task = $this->makeTask();

        $this->actingAs($this->owner)
            ->post("/tasks/{$task->id}/complete")
            ->assertRedirect();

        $entry = $this->activitiesFor($task)->firstWhere('field', 'status');
        $this->assertNotNull($entry);
        $this->assertFalse($entry->from_value['value']);
        $this->assertTrue($entry->to_value['value']);
    }

    public function test_reopening_task_records_status_activity(): void
    {
        $task = $this->makeTask(['completed' => true, 'completed_at' => now(), 'completed_by' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->post("/tasks/{$task->id}/uncomplete")
            ->assertRedirect();

        $entry = $this->activitiesFor($task)->firstWhere('field', 'status');
        $this->assertNotNull($entry);
        $this->assertTrue($entry->from_value['value']);
        $this->assertFalse($entry->to_value['value']);
    }

    // ---------- subtasks ----------

    public function test_renaming_subtask_records_activity_on_parent_with_subtask_id(): void
    {
        $parent  = $this->makeTask();
        $subtask = $this->makeTask([
            'parent_task_id' => $parent->id,
            'title'          => 'Old subtask',
        ]);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$parent->id}/subtasks/{$subtask->id}", ['title' => 'New subtask'])
            ->assertRedirect();

        $entry = TaskActivity::where('task_id', $parent->id)
            ->where('subtask_id', $subtask->id)
            ->where('field', 'title')
            ->first();
        $this->assertNotNull($entry);
        $this->assertSame('Old subtask', $entry->from_value['value']);
        $this->assertSame('New subtask', $entry->to_value['value']);
    }

    public function test_changing_subtask_priority_records_activity(): void
    {
        $parent  = $this->makeTask();
        $subtask = $this->makeTask(['parent_task_id' => $parent->id, 'priority' => 'low']);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$parent->id}/subtasks/{$subtask->id}", ['priority' => 'high'])
            ->assertRedirect();

        $entry = TaskActivity::where('subtask_id', $subtask->id)
            ->where('field', 'priority')->first();
        $this->assertNotNull($entry);
        $this->assertSame('low', $entry->from_value['value']);
        $this->assertSame('high', $entry->to_value['value']);
    }

    public function test_changing_subtask_dates_records_activities(): void
    {
        $parent  = $this->makeTask();
        $subtask = $this->makeTask(['parent_task_id' => $parent->id]);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$parent->id}/subtasks/{$subtask->id}", [
                'start_date' => '2026-06-01',
                'due_date'   => '2026-06-10',
            ])->assertRedirect();

        $this->assertNotNull(TaskActivity::where('subtask_id', $subtask->id)->where('field', 'start_date')->first());
        $this->assertNotNull(TaskActivity::where('subtask_id', $subtask->id)->where('field', 'due_date')->first());
    }

    public function test_subtask_assignee_change_records_activity(): void
    {
        $parent  = $this->makeTask();
        $subtask = $this->makeTask(['parent_task_id' => $parent->id]);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$parent->id}/subtasks/{$subtask->id}", [
                'assignee_ids' => [$this->alice->id, $this->bob->id],
            ])->assertRedirect();

        $entry = TaskActivity::where('subtask_id', $subtask->id)
            ->where('field', 'assignees')->first();
        $this->assertNotNull($entry);
        $this->assertSame([], $entry->from_value['ids']);
        $this->assertEqualsCanonicalizing([$this->alice->id, $this->bob->id], $entry->to_value['ids']);
    }

    public function test_subtask_complete_records_status_activity_with_subtask_id(): void
    {
        $parent  = $this->makeTask();
        $subtask = $this->makeTask(['parent_task_id' => $parent->id]);

        $this->actingAs($this->owner)
            ->post("/tasks/{$subtask->id}/complete")
            ->assertRedirect();

        $entry = TaskActivity::where('task_id', $parent->id)
            ->where('subtask_id', $subtask->id)
            ->where('field', 'status')->first();
        $this->assertNotNull($entry);
        $this->assertTrue($entry->to_value['value']);
    }

    // ---------- multi-field update ----------

    public function test_multi_field_update_records_one_activity_per_field(): void
    {
        $task = $this->makeTask(['title' => 'Old', 'priority' => 'low']);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$task->id}", [
                'title'    => 'New',
                'priority' => 'high',
                'tags'     => ['x'],
            ])->assertRedirect();

        $fields = $this->activitiesFor($task)->pluck('field')->all();
        $this->assertContains('title',    $fields);
        $this->assertContains('priority', $fields);
        $this->assertContains('tags',     $fields);
    }

    // ---------- noise filtering ----------

    public function test_unchanged_title_does_not_record_activity(): void
    {
        $task = $this->makeTask(['title' => 'Same']);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$task->id}", ['title' => 'Same'])
            ->assertRedirect();

        $this->assertNull($this->activitiesFor($task)->firstWhere('field', 'title'));
    }

    // ---------- attribution ----------

    public function test_activity_records_the_user_who_made_the_change(): void
    {
        $task = $this->makeTask(['title' => 'Foo']);

        $this->actingAs($this->bob)
            ->patch("/tasks/{$task->id}", ['title' => 'Bar'])
            ->assertRedirect();

        $entry = $this->activitiesFor($task)->firstWhere('field', 'title');
        $this->assertSame($this->bob->id, $entry->user_id);
    }
}
