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
 * Covers recoloring a board column — the action behind clicking the colored dot
 * in a column header and choosing a swatch. It rides the existing
 * `columns.update` endpoint with a `color` payload.
 */
class BoardColumnColorTest extends TestCase
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

        $this->column = BoardColumn::create([
            'project_id' => $this->project->id,
            'name'       => 'Todo',
            'color'      => '#94948c',
            'position'   => 0,
        ]);
    }

    public function test_user_can_recolor_a_column(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('columns.update', $this->column), ['color' => '#2563eb'])
            ->assertRedirect();

        $this->assertSame('#2563eb', $this->column->fresh()->color);
    }

    public function test_recolor_leaves_the_name_untouched(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('columns.update', $this->column), ['color' => '#16a34a'])
            ->assertRedirect();

        $fresh = $this->column->fresh();
        $this->assertSame('#16a34a', $fresh->color);
        $this->assertSame('Todo', $fresh->name);
    }

    public function test_recolor_broadcasts_board_column_updated(): void
    {
        Event::fake([BoardColumnUpdated::class]);

        $this->actingAs($this->owner)
            ->patch(route('columns.update', $this->column), ['color' => '#db2777'])
            ->assertRedirect();

        Event::assertDispatched(
            BoardColumnUpdated::class,
            fn (BoardColumnUpdated $e) => $e->event === 'updated' && $e->column->is($this->column),
        );
    }

    public function test_recolor_rejects_an_overly_long_color(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('columns.update', $this->column), ['color' => '#1234567890'])
            ->assertSessionHasErrors('color');

        $this->assertSame('#94948c', $this->column->fresh()->color);
    }

    public function test_guest_cannot_recolor_a_column(): void
    {
        $this->patch(route('columns.update', $this->column), ['color' => '#2563eb'])
            ->assertRedirect(route('login'));

        $this->assertSame('#94948c', $this->column->fresh()->color);
    }
}
