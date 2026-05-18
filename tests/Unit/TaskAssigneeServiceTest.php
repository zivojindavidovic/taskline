<?php

namespace Tests\Unit;

use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\TaskRepository;
use App\Services\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskAssigneeServiceTest extends TestCase
{
    use RefreshDatabase;

    private TaskService $service;
    private User $owner;
    private Project $project;
    private Sprint $sprint;
    private BoardColumn $column;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TaskService(new TaskRepository());

        $this->owner = User::factory()->create();
        $workspace = Workspace::create([
            'name'     => 'Acme',
            'owner_id' => $this->owner->id,
            'color'    => '#4f46e5',
        ]);
        $this->project = Project::create([
            'name'         => 'Test',
            'key'          => 'TST',
            'color'        => '#4f46e5',
            'owner_id'     => $this->owner->id,
            'workspace_id' => $workspace->id,
        ]);
        $this->sprint = Sprint::create(['project_id' => $this->project->id, 'name' => 'S1']);
        $this->column = BoardColumn::create([
            'project_id' => $this->project->id,
            'name'       => 'Todo',
            'color'      => '#94948c',
            'position'   => 0,
        ]);
    }

    private function makeTask(): Task
    {
        return Task::create([
            'key'             => 'TST-1',
            'title'           => 'Test',
            'project_id'      => $this->project->id,
            'sprint_id'       => $this->sprint->id,
            'board_column_id' => $this->column->id,
            'created_by'      => $this->owner->id,
            'priority'        => 'med',
        ]);
    }

    public function test_set_assignees_attaches_multiple_users(): void
    {
        $task = $this->makeTask();
        $alice = User::factory()->create();
        $bob   = User::factory()->create();

        $this->service->setAssignees($task, [$alice->id, $bob->id], $this->owner->id);

        $this->assertDatabaseHas('task_assignees', ['task_id' => $task->id, 'user_id' => $alice->id]);
        $this->assertDatabaseHas('task_assignees', ['task_id' => $task->id, 'user_id' => $bob->id]);
        $this->assertSame(2, $task->fresh()->assignees()->count());
    }

    public function test_set_assignees_mirrors_primary_into_legacy_column(): void
    {
        $task = $this->makeTask();
        $alice = User::factory()->create();
        $bob   = User::factory()->create();

        $this->service->setAssignees($task, [$alice->id, $bob->id], $this->owner->id);

        $this->assertSame($alice->id, $task->fresh()->assignee_id);
    }

    public function test_set_assignees_writes_audit_log_with_previous_and_new_ids(): void
    {
        $task = $this->makeTask();
        $alice = User::factory()->create();
        $bob   = User::factory()->create();

        $this->service->setAssignees($task, [$alice->id], $this->owner->id);
        $this->service->setAssignees($task, [$bob->id], $this->owner->id);

        $log = \App\Models\AuditLog::where('task_id', $task->id)
            ->where('action', 'task.assigned')
            ->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertEquals([$bob->id], $log->meta['assignee_ids']);
        $this->assertEquals([$alice->id], $log->meta['previous_assignee_ids']);
    }

    public function test_update_with_assignee_ids_syncs_pivot_and_clears_legacy_when_empty(): void
    {
        $task = $this->makeTask();
        $alice = User::factory()->create();
        $task->assignee_id = $alice->id;
        $task->save();
        $task->assignees()->sync([$alice->id]);

        $this->service->update($task, ['assignee_ids' => []], $this->owner->id);

        $this->assertSame(0, $task->fresh()->assignees()->count());
        $this->assertNull($task->fresh()->assignee_id);
    }

    public function test_update_with_assignee_ids_replaces_existing_assignees(): void
    {
        $task = $this->makeTask();
        $alice = User::factory()->create();
        $bob   = User::factory()->create();
        $carol = User::factory()->create();

        $this->service->setAssignees($task, [$alice->id, $bob->id], $this->owner->id);
        $this->service->update($task, ['assignee_ids' => [$carol->id]], $this->owner->id);

        $ids = $task->fresh()->assignees()->pluck('users.id')->all();
        $this->assertEquals([$carol->id], $ids);
    }

    public function test_sync_dedupes_repeated_ids(): void
    {
        $task = $this->makeTask();
        $alice = User::factory()->create();

        $this->service->setAssignees($task, [$alice->id, $alice->id, $alice->id], $this->owner->id);

        $this->assertSame(1, $task->fresh()->assignees()->count());
    }
}
