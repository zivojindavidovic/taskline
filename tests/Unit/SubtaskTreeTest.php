<?php

namespace Tests\Unit;

use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\TaskRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Data-layer behaviour for nested subtasks: the recursive tree loader, the
 * descendant collector, and the cascade delete. Exercised directly against the
 * model/repository, independent of the HTTP layer.
 */
class SubtaskTreeTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Project $project;
    private BoardColumn $column;
    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $workspace = Workspace::create([
            'name'     => 'WS',
            'owner_id' => $this->owner->id,
            'color'    => '#4f46e5',
        ]);
        $workspace->users()->attach($this->owner->id, ['role' => 'owner']);

        $this->project = Project::create([
            'name'         => 'P',
            'key'          => 'P',
            'color'        => '#4f46e5',
            'owner_id'     => $this->owner->id,
            'workspace_id' => $workspace->id,
        ]);
        $this->column = BoardColumn::create([
            'project_id' => $this->project->id,
            'name'       => 'Todo',
            'color'      => '#94948c',
            'position'   => 0,
        ]);
    }

    private function task(string $title, ?Task $parent = null): Task
    {
        $this->seq++;

        return Task::create([
            'key'             => 'P-' . $this->seq,
            'title'           => $title,
            'priority'        => 'med',
            'project_id'      => $this->project->id,
            'board_column_id' => $this->column->id,
            'parent_task_id'  => $parent?->id,
            'created_by'      => $this->owner->id,
        ]);
    }

    public function test_descendant_ids_collects_the_whole_subtree(): void
    {
        $root = $this->task('root');
        $a    = $this->task('a', $root);
        $b    = $this->task('b', $root);
        $a1   = $this->task('a1', $a);   // grandchild
        $a1x  = $this->task('a1x', $a1); // great-grandchild

        $ids = $root->descendantIds();
        sort($ids);
        $expected = [$a->id, $b->id, $a1->id, $a1x->id];
        sort($expected);

        $this->assertSame($expected, $ids);

        // A leaf has no descendants.
        $this->assertSame([], $a1x->descendantIds());
    }

    public function test_load_subtask_tree_hydrates_every_level(): void
    {
        $root = $this->task('root');
        $a    = $this->task('a', $root);
        $a1   = $this->task('a1', $a);

        $fresh = Task::findOrFail($root->id);
        $fresh->loadSubtaskTree();

        $this->assertTrue($fresh->relationLoaded('subtasks'));
        $this->assertCount(1, $fresh->subtasks);

        $childNode = $fresh->subtasks->first();
        $this->assertTrue($childNode->relationLoaded('subtasks'));
        $this->assertSame($a->id, $childNode->id);
        $this->assertCount(1, $childNode->subtasks);
        $this->assertSame($a1->id, $childNode->subtasks->first()->id);

        // Leaf node ends with an empty (but loaded) subtasks relation.
        $leaf = $childNode->subtasks->first();
        $this->assertTrue($leaf->relationLoaded('subtasks'));
        $this->assertCount(0, $leaf->subtasks);
    }

    public function test_repository_delete_cascades_descendants(): void
    {
        $root = $this->task('root');
        $a    = $this->task('a', $root);
        $a1   = $this->task('a1', $a);

        app(TaskRepository::class)->delete($a);

        $this->assertNull(Task::find($a->id));
        $this->assertNull(Task::find($a1->id));
        // Ancestor untouched; descendant not orphaned to the top level.
        $this->assertNotNull(Task::find($root->id));
        $this->assertSame(0, Task::whereNull('parent_task_id')->where('id', $a1->id)->count());
    }
}
