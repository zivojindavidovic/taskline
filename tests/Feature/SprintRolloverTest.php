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

class SprintRolloverTest extends TestCase
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

        $this->column = BoardColumn::create([
            'project_id' => $this->project->id,
            'name'       => 'Todo',
            'color'      => '#94948c',
            'position'   => 0,
        ]);
    }

    private function makeSprint(array $attrs = []): Sprint
    {
        static $sprintCounter = 0;
        $sprintCounter++;

        return Sprint::create(array_merge([
            'project_id' => $this->project->id,
            'name'       => "Sprint {$sprintCounter}",
            'status'     => 'active',
            'locked'     => false,
        ], $attrs));
    }

    private function makeTask(Sprint $sprint, array $attrs = []): Task
    {
        static $taskCounter = 0;
        $taskCounter++;

        return Task::create(array_merge([
            'key'             => "TST-{$taskCounter}",
            'title'           => "Task {$taskCounter}",
            'project_id'      => $this->project->id,
            'sprint_id'       => $sprint->id,
            'board_column_id' => $this->column->id,
            'created_by'      => $this->owner->id,
            'priority'        => 'med',
        ], $attrs));
    }

    public function test_uncompleted_tasks_in_expired_sprint_move_to_backlog(): void
    {
        $sprint = $this->makeSprint([
            'start_date' => now()->subDays(14)->toDateString(),
            'end_date'   => now()->subDay()->toDateString(),
        ]);
        $a = $this->makeTask($sprint);
        $b = $this->makeTask($sprint);

        $this->artisan('sprints:rollover-overdue-tasks')->assertSuccessful();

        $this->assertNull($a->fresh()->sprint_id);
        $this->assertNull($b->fresh()->sprint_id);
    }

    public function test_completed_tasks_stay_in_the_sprint(): void
    {
        $sprint = $this->makeSprint([
            'start_date' => now()->subDays(14)->toDateString(),
            'end_date'   => now()->subDay()->toDateString(),
        ]);
        $done = $this->makeTask($sprint, [
            'completed'    => true,
            'completed_at' => now(),
            'completed_by' => $this->owner->id,
        ]);

        $this->artisan('sprints:rollover-overdue-tasks')->assertSuccessful();

        $this->assertSame($sprint->id, $done->fresh()->sprint_id);
    }

    public function test_tasks_in_sprints_still_within_window_are_untouched(): void
    {
        $sprint = $this->makeSprint([
            'start_date' => now()->subDays(2)->toDateString(),
            'end_date'   => now()->addDays(7)->toDateString(),
        ]);
        $task = $this->makeTask($sprint);

        $this->artisan('sprints:rollover-overdue-tasks')->assertSuccessful();

        $this->assertSame($sprint->id, $task->fresh()->sprint_id);
    }

    public function test_tasks_in_sprint_ending_today_are_untouched(): void
    {
        $sprint = $this->makeSprint([
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date'   => now()->toDateString(),
        ]);
        $task = $this->makeTask($sprint);

        $this->artisan('sprints:rollover-overdue-tasks')->assertSuccessful();

        $this->assertSame($sprint->id, $task->fresh()->sprint_id);
    }

    public function test_tasks_in_already_completed_sprint_are_untouched(): void
    {
        $sprint = $this->makeSprint([
            'status'     => 'completed',
            'start_date' => now()->subDays(14)->toDateString(),
            'end_date'   => now()->subDay()->toDateString(),
        ]);
        $task = $this->makeTask($sprint);

        $this->artisan('sprints:rollover-overdue-tasks')->assertSuccessful();

        $this->assertSame($sprint->id, $task->fresh()->sprint_id);
    }

    public function test_tasks_in_sprint_without_end_date_are_untouched(): void
    {
        $sprint = $this->makeSprint([
            'start_date' => now()->subDays(30)->toDateString(),
            'end_date'   => null,
        ]);
        $task = $this->makeTask($sprint);

        $this->artisan('sprints:rollover-overdue-tasks')->assertSuccessful();

        $this->assertSame($sprint->id, $task->fresh()->sprint_id);
    }

    public function test_command_moves_tasks_across_multiple_overdue_sprints(): void
    {
        $sprintA = $this->makeSprint([
            'start_date' => now()->subDays(20)->toDateString(),
            'end_date'   => now()->subDays(5)->toDateString(),
        ]);
        $sprintB = $this->makeSprint([
            'start_date' => now()->subDays(20)->toDateString(),
            'end_date'   => now()->subDay()->toDateString(),
        ]);
        $taskA = $this->makeTask($sprintA);
        $taskB = $this->makeTask($sprintB);

        $this->artisan('sprints:rollover-overdue-tasks')->assertSuccessful();

        $this->assertNull($taskA->fresh()->sprint_id);
        $this->assertNull($taskB->fresh()->sprint_id);
    }

    public function test_locked_overdue_sprint_tasks_also_roll_over(): void
    {
        $sprint = $this->makeSprint([
            'locked'     => true,
            'start_date' => now()->subDays(14)->toDateString(),
            'end_date'   => now()->subDay()->toDateString(),
        ]);
        $task = $this->makeTask($sprint);

        $this->artisan('sprints:rollover-overdue-tasks')->assertSuccessful();

        $this->assertNull($task->fresh()->sprint_id);
        $this->assertTrue($sprint->fresh()->locked);
    }

    public function test_command_does_not_change_sprint_status(): void
    {
        $sprint = $this->makeSprint([
            'status'     => 'active',
            'start_date' => now()->subDays(14)->toDateString(),
            'end_date'   => now()->subDay()->toDateString(),
        ]);
        $this->makeTask($sprint);

        $this->artisan('sprints:rollover-overdue-tasks')->assertSuccessful();

        $this->assertSame('active', $sprint->fresh()->status);
    }
}
