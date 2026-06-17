<?php

namespace Tests\Feature;

use App\Events\BoardColumnUpdated;
use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Covers reordering board columns left-to-right. The endpoint receives the full
 * ordered list of column UUIDs and rewrites each column's `position` to its
 * index — the same contract used by both drag-to-reorder and the
 * "Move left / Move right" menu actions in the UI.
 */
class BoardColumnReorderTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Workspace $workspace;
    private Project $project;
    /** @var BoardColumn[] */
    private array $columns;

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

        // Deterministic three-column board: Todo(0) → Doing(1) → Done(2).
        $this->columns = [
            BoardColumn::create(['project_id' => $this->project->id, 'name' => 'Todo',  'color' => '#94948c', 'position' => 0]),
            BoardColumn::create(['project_id' => $this->project->id, 'name' => 'Doing', 'color' => '#3b82f6', 'position' => 1]),
            BoardColumn::create(['project_id' => $this->project->id, 'name' => 'Done',  'color' => '#22c55e', 'position' => 2]),
        ];
    }

    private function reorder(array $order)
    {
        return $this->actingAs($this->owner)
            ->patch(route('columns.reorder', $this->project), ['order' => $order]);
    }

    private function uuids(int ...$indexes): array
    {
        return array_map(fn ($i) => $this->columns[$i]->uuid, $indexes);
    }

    // ── Happy path ────────────────────────────────────────────────────────────

    public function test_full_reorder_persists_positions_by_index(): void
    {
        // Done, Todo, Doing → positions 0,1,2 respectively.
        $this->reorder($this->uuids(2, 0, 1))->assertRedirect();

        $this->assertSame(1, $this->columns[0]->fresh()->position); // Todo
        $this->assertSame(2, $this->columns[1]->fresh()->position); // Doing
        $this->assertSame(0, $this->columns[2]->fresh()->position); // Done
    }

    public function test_move_right_swaps_a_column_with_its_neighbour(): void
    {
        // "Move right" on Todo => swap Todo(0) and Doing(1): Doing, Todo, Done.
        $this->reorder($this->uuids(1, 0, 2))->assertRedirect();

        $this->assertSame(1, $this->columns[0]->fresh()->position); // Todo
        $this->assertSame(0, $this->columns[1]->fresh()->position); // Doing
        $this->assertSame(2, $this->columns[2]->fresh()->position); // Done unchanged
    }

    public function test_move_left_swaps_a_column_with_its_neighbour(): void
    {
        // "Move left" on Done => swap Doing(1) and Done(2): Todo, Done, Doing.
        $this->reorder($this->uuids(0, 2, 1))->assertRedirect();

        $this->assertSame(0, $this->columns[0]->fresh()->position); // Todo unchanged
        $this->assertSame(2, $this->columns[1]->fresh()->position); // Doing
        $this->assertSame(1, $this->columns[2]->fresh()->position); // Done
    }

    public function test_reorder_records_an_audit_log_entry(): void
    {
        $this->reorder($this->uuids(2, 1, 0))->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action'     => 'column.reordered',
            'project_id' => $this->project->id,
            'user_id'    => $this->owner->id,
        ]);
    }

    public function test_reorder_broadcasts_a_board_column_updated_event(): void
    {
        Event::fake([BoardColumnUpdated::class]);

        $this->reorder($this->uuids(2, 1, 0))->assertRedirect();

        Event::assertDispatched(
            BoardColumnUpdated::class,
            fn (BoardColumnUpdated $e) => $e->event === 'reordered' && $e->project->is($this->project),
        );
    }

    // ── Validation / scoping ───────────────────────────────────────────────────

    public function test_reorder_rejects_a_partial_order(): void
    {
        $this->reorder($this->uuids(2, 0)) // missing Doing
            ->assertSessionHasErrors('order');

        $this->assertUnchangedPositions();
    }

    public function test_reorder_rejects_duplicate_columns(): void
    {
        $this->reorder([$this->columns[0]->uuid, $this->columns[0]->uuid, $this->columns[1]->uuid])
            ->assertSessionHasErrors('order');

        $this->assertUnchangedPositions();
    }

    public function test_reorder_rejects_columns_from_another_project(): void
    {
        $otherProject = Project::create([
            'name'         => 'Other',
            'key'          => 'OTH',
            'color'        => '#4f46e5',
            'owner_id'     => $this->owner->id,
            'workspace_id' => $this->workspace->id,
        ]);
        $foreign = BoardColumn::create([
            'project_id' => $otherProject->id, 'name' => 'Foreign', 'color' => '#000000', 'position' => 0,
        ]);

        // Right count, but one uuid belongs to a different board.
        $this->reorder([$this->columns[0]->uuid, $this->columns[1]->uuid, $foreign->uuid])
            ->assertSessionHasErrors('order');

        $this->assertUnchangedPositions();
        $this->assertSame(0, $foreign->fresh()->position);
    }

    public function test_reorder_requires_an_order_payload(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('columns.reorder', $this->project), [])
            ->assertSessionHasErrors('order');

        $this->assertUnchangedPositions();
    }

    public function test_guest_cannot_reorder_columns(): void
    {
        $this->patch(route('columns.reorder', $this->project), ['order' => $this->uuids(2, 1, 0)])
            ->assertRedirect(route('login'));

        $this->assertUnchangedPositions();
    }

    private function assertUnchangedPositions(): void
    {
        $this->assertSame(0, $this->columns[0]->fresh()->position);
        $this->assertSame(1, $this->columns[1]->fresh()->position);
        $this->assertSame(2, $this->columns[2]->fresh()->position);
    }
}
