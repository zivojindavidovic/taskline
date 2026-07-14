<?php

namespace Tests\Feature;

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

/**
 * Covers reordering task cards top-to-bottom within a board column. The
 * endpoint receives the full ordered list of task UUIDs that should live in a
 * column and rewrites each task's `position` to its index (and its
 * `board_column_id` to that column). The same contract backs within-column
 * drag-reorder, the "Move up / Move down" menu, and a card dragged in from a
 * sibling column — the vertical twin of BoardColumnReorderTest.
 */
class TaskReorderTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Workspace $workspace;
    private Project $project;
    private BoardColumn $todo;
    private BoardColumn $doing;
    /** @var Task[] Todo cards A(0), B(1), C(2). */
    private array $todoTasks;
    private Task $doingTask; // D(0)

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

        $this->todo  = BoardColumn::create(['project_id' => $this->project->id, 'name' => 'Todo',  'color' => '#94948c', 'position' => 0]);
        $this->doing = BoardColumn::create(['project_id' => $this->project->id, 'name' => 'Doing', 'color' => '#3b82f6', 'position' => 1]);

        $this->todoTasks = [
            $this->makeTask($this->todo, 0, 'MOB-1'),
            $this->makeTask($this->todo, 1, 'MOB-2'),
            $this->makeTask($this->todo, 2, 'MOB-3'),
        ];
        $this->doingTask = $this->makeTask($this->doing, 0, 'MOB-4');
    }

    private function makeTask(BoardColumn $column, int $position, string $key): Task
    {
        return Task::create([
            'key'             => $key,
            'title'           => $key,
            'project_id'      => $this->project->id,
            'board_column_id' => $column->id,
            'created_by'      => $this->owner->id,
            'priority'        => 'med',
            'position'        => $position,
        ]);
    }

    private function reorder(BoardColumn $column, array $uuids)
    {
        return $this->actingAs($this->owner)
            ->patch(route('columns.tasks.reorder', $column), ['order' => $uuids]);
    }

    // ── Happy path ──────────────────────────────────────────────────────────

    public function test_reorder_persists_positions_by_index(): void
    {
        [$a, $b, $c] = $this->todoTasks;

        // New order in Todo: C, A, B.
        $this->reorder($this->todo, [$c->uuid, $a->uuid, $b->uuid])->assertRedirect();

        $this->assertSame(1, $a->fresh()->position);
        $this->assertSame(2, $b->fresh()->position);
        $this->assertSame(0, $c->fresh()->position);
        $this->assertSame($this->todo->id, $a->fresh()->board_column_id);
    }

    public function test_move_up_swaps_a_card_with_its_neighbour(): void
    {
        [$a, $b, $c] = $this->todoTasks;

        // "Move up" on B => swap A(0) and B(1): B, A, C.
        $this->reorder($this->todo, [$b->uuid, $a->uuid, $c->uuid])->assertRedirect();

        $this->assertSame(1, $a->fresh()->position);
        $this->assertSame(0, $b->fresh()->position);
        $this->assertSame(2, $c->fresh()->position);
    }

    public function test_dropping_a_card_into_another_column_adopts_it(): void
    {
        [$a] = $this->todoTasks;

        // Drop A above D in Doing: order = [A, D].
        $this->reorder($this->doing, [$a->uuid, $this->doingTask->uuid])->assertRedirect();

        $a = $a->fresh();
        $this->assertSame($this->doing->id, $a->board_column_id);
        $this->assertSame(0, $a->position);
        $this->assertSame(1, $this->doingTask->fresh()->position);
    }

    public function test_task_in_a_locked_sprint_can_move_to_another_column(): void
    {
        $sprint = Sprint::create([
            'project_id' => $this->project->id,
            'name'       => 'Locked Sprint',
            'locked'     => true,
        ]);
        [$task] = $this->todoTasks;
        $task->update(['sprint_id' => $sprint->id]);

        $this->actingAs($this->owner)
            ->post(route('tasks.move', $task), ['board_column_id' => $this->doing->id])
            ->assertRedirect();

        $this->assertSame($this->doing->id, $task->fresh()->board_column_id);
    }

    public function test_partial_order_leaves_unlisted_cards_untouched(): void
    {
        [$a, $b, $c] = $this->todoTasks;

        // Only C is repositioned; A and B keep their positions.
        $this->reorder($this->todo, [$c->uuid])->assertRedirect();

        $this->assertSame(0, $c->fresh()->position);
        $this->assertSame(0, $a->fresh()->position);
        $this->assertSame(1, $b->fresh()->position);
    }

    public function test_reorder_records_an_audit_log_entry(): void
    {
        [$a, $b, $c] = $this->todoTasks;

        $this->reorder($this->todo, [$c->uuid, $b->uuid, $a->uuid])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action'     => 'task.reordered',
            'project_id' => $this->project->id,
            'user_id'    => $this->owner->id,
        ]);
    }

    public function test_cross_column_move_broadcasts_a_task_updated_event(): void
    {
        Event::fake([TaskUpdated::class]);
        [$a] = $this->todoTasks;

        $this->reorder($this->doing, [$a->uuid, $this->doingTask->uuid])->assertRedirect();

        Event::assertDispatched(
            TaskUpdated::class,
            fn (TaskUpdated $e) => (int) $e->task->id === (int) $a->id,
        );
    }

    public function test_pure_within_column_reorder_does_not_broadcast(): void
    {
        Event::fake([TaskUpdated::class]);
        [$a, $b, $c] = $this->todoTasks;

        $this->reorder($this->todo, [$c->uuid, $a->uuid, $b->uuid])->assertRedirect();

        Event::assertNotDispatched(TaskUpdated::class);
    }

    // ── Validation / scoping ──────────────────────────────────────────────────

    public function test_reorder_rejects_duplicate_tasks(): void
    {
        [$a, $b] = $this->todoTasks;

        $this->reorder($this->todo, [$a->uuid, $a->uuid, $b->uuid])
            ->assertSessionHasErrors('order');

        $this->assertSame(0, $a->fresh()->position);
    }

    public function test_reorder_rejects_tasks_from_another_project(): void
    {
        $otherProject = Project::create([
            'name'         => 'Other',
            'key'          => 'OTH',
            'color'        => '#000000',
            'owner_id'     => $this->owner->id,
            'workspace_id' => $this->workspace->id,
        ]);
        $otherColumn = BoardColumn::create([
            'project_id' => $otherProject->id, 'name' => 'X', 'color' => '#000000', 'position' => 0,
        ]);
        $foreign = Task::create([
            'key' => 'OTH-1', 'title' => 'Foreign',
            'project_id' => $otherProject->id, 'board_column_id' => $otherColumn->id,
            'created_by' => $this->owner->id, 'priority' => 'med', 'position' => 0,
        ]);

        [$a, $b] = $this->todoTasks;
        $this->reorder($this->todo, [$a->uuid, $b->uuid, $foreign->uuid])
            ->assertSessionHasErrors('order');

        $this->assertSame($otherColumn->id, $foreign->fresh()->board_column_id);
    }

    public function test_reorder_requires_an_order_payload(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('columns.tasks.reorder', $this->todo), [])
            ->assertSessionHasErrors('order');
    }

    public function test_guest_cannot_reorder_tasks(): void
    {
        [$a, $b, $c] = $this->todoTasks;

        $this->patch(route('columns.tasks.reorder', $this->todo), ['order' => [$a->uuid, $b->uuid, $c->uuid]])
            ->assertRedirect(route('login'));

        $this->assertSame(0, $a->fresh()->position);
    }

    public function test_non_member_cannot_reorder_tasks(): void
    {
        $intruder = User::factory()->create();
        $intruderWorkspace = Workspace::create([
            'name' => 'Theirs', 'owner_id' => $intruder->id, 'color' => '#000000',
        ]);
        $intruderWorkspace->users()->attach($intruder->id, ['role' => 'owner']);
        $intruder->update(['current_workspace_id' => $intruderWorkspace->id]);

        [$a, $b, $c] = $this->todoTasks;
        $this->actingAs($intruder)
            ->patch(route('columns.tasks.reorder', $this->todo), ['order' => [$a->uuid, $b->uuid, $c->uuid]])
            ->assertForbidden();

        $this->assertSame(0, $a->fresh()->position);
    }
}
