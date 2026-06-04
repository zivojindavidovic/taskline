<?php

namespace Tests\Feature\Task;

use App\Models\AuditLog;
use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Subtasks within subtasks — nesting to any depth, mirroring the design.
 *
 * A subtask is just a Task whose parent_task_id points at another task, so the
 * same mechanism works at every level. These tests cover the depth-aware paths:
 * creating below a subtask, loading the whole tree, the immediate-parent update
 * contract, completion, cascade delete of a subtree, and authorization.
 */
class NestedSubtaskTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Workspace $workspace;
    private Project $project;
    private Sprint $sprint;
    private BoardColumn $column;
    private Task $root;
    private Task $child;

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

        $this->root = Task::create([
            'key'             => 'TST-1',
            'title'           => 'Root task',
            'priority'        => 'med',
            'project_id'      => $this->project->id,
            'sprint_id'       => $this->sprint->id,
            'board_column_id' => $this->column->id,
            'created_by'      => $this->owner->id,
        ]);

        $this->child = Task::create([
            'key'             => 'TST-2',
            'title'           => 'First-level subtask',
            'priority'        => 'med',
            'project_id'      => $this->project->id,
            'sprint_id'       => $this->sprint->id,
            'board_column_id' => $this->column->id,
            'parent_task_id'  => $this->root->id,
            'created_by'      => $this->owner->id,
        ]);
    }

    /** POST a subtask under $parent and return the freshly created Task. */
    private function addSubtaskUnder(Task $parent, string $title, ?User $user = null): Task
    {
        $user ??= $this->owner;

        $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('tasks.subtasks.store', $parent->uuid), ['title' => $title])
            ->assertRedirect();

        return Task::where('parent_task_id', $parent->id)
            ->where('title', $title)
            ->firstOrFail();
    }

    // ─── Creating nested subtasks ─────────────────────────────────────────────

    public function test_can_create_a_subtask_under_a_subtask(): void
    {
        $grandchild = $this->addSubtaskUnder($this->child, 'Second-level subtask');

        $this->assertSame($this->child->id, $grandchild->parent_task_id);
        $this->assertDatabaseHas('tasks', [
            'id'             => $grandchild->id,
            'parent_task_id' => $this->child->id,
            'title'          => 'Second-level subtask',
        ]);
    }

    public function test_can_nest_subtasks_three_levels_deep(): void
    {
        $level2 = $this->addSubtaskUnder($this->child, 'Level 2');
        $level3 = $this->addSubtaskUnder($level2, 'Level 3');
        $level4 = $this->addSubtaskUnder($level3, 'Level 4');

        $this->assertSame($this->child->id, $level2->parent_task_id);
        $this->assertSame($level2->id, $level3->parent_task_id);
        $this->assertSame($level3->id, $level4->parent_task_id);
    }

    public function test_nested_subtask_inherits_project_sprint_and_column_from_its_parent(): void
    {
        $grandchild = $this->addSubtaskUnder($this->child, 'Inheriting subtask');

        $this->assertSame($this->project->id, $grandchild->project_id);
        $this->assertSame($this->child->sprint_id, $grandchild->sprint_id);
        $this->assertSame($this->child->board_column_id, $grandchild->board_column_id);
    }

    public function test_nested_subtask_key_counts_all_tasks_in_the_project(): void
    {
        // Project already has TST-1 (root) + TST-2 (child) → next key is TST-3.
        $grandchild = $this->addSubtaskUnder($this->child, 'Keyed subtask');

        $this->assertSame('TST-3', $grandchild->key);
    }

    public function test_adding_a_nested_subtask_writes_an_audit_log_against_its_parent(): void
    {
        $grandchild = $this->addSubtaskUnder($this->child, 'Audited subtask');

        $this->assertDatabaseHas('audit_logs', [
            'task_id' => $this->child->id,
            'user_id' => $this->owner->id,
            'action'  => 'task.subtask_added',
        ]);
    }

    // ─── Loading the tree ─────────────────────────────────────────────────────

    public function test_details_endpoint_returns_the_full_nested_subtask_tree(): void
    {
        $level2 = $this->addSubtaskUnder($this->child, 'Level 2');
        $level3 = $this->addSubtaskUnder($level2, 'Level 3');

        $response = $this->actingAs($this->owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->get(route('tasks.details', $this->root->uuid))
            ->assertOk();

        $task = $response->json('task');

        $this->assertCount(1, $task['subtasks']);
        $this->assertSame($this->child->id, $task['subtasks'][0]['id']);

        $childNode = $task['subtasks'][0];
        $this->assertCount(1, $childNode['subtasks']);
        $this->assertSame($level2->id, $childNode['subtasks'][0]['id']);

        $level2Node = $childNode['subtasks'][0];
        $this->assertCount(1, $level2Node['subtasks']);
        $this->assertSame($level3->id, $level2Node['subtasks'][0]['id']);

        // Leaves carry an explicit empty list so the panel can render uniformly.
        $this->assertSame([], $level2Node['subtasks'][0]['subtasks']);
    }

    public function test_each_node_in_the_tree_carries_its_panel_relations(): void
    {
        $this->addSubtaskUnder($this->child, 'Level 2');

        $task = $this->actingAs($this->owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->get(route('tasks.details', $this->root->uuid))
            ->assertOk()
            ->json('task');

        // The shared subtaskTreeRelations() set is applied at every depth.
        $this->assertArrayHasKey('comments', $task['subtasks'][0]);
        $this->assertArrayHasKey('assignees', $task['subtasks'][0]);
        $this->assertArrayHasKey('comments', $task['subtasks'][0]['subtasks'][0]);
        $this->assertArrayHasKey('assignees', $task['subtasks'][0]['subtasks'][0]);
    }

    // ─── Updating nested subtasks (immediate-parent contract) ─────────────────

    public function test_can_update_a_nested_subtask_through_its_immediate_parent(): void
    {
        $grandchild = $this->addSubtaskUnder($this->child, 'Before');

        $this->actingAs($this->owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->patch(route('tasks.subtasks.update', [$this->child->uuid, $grandchild->uuid]), ['title' => 'After'])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', ['id' => $grandchild->id, 'title' => 'After']);
    }

    public function test_cannot_update_a_nested_subtask_through_a_non_immediate_ancestor(): void
    {
        $grandchild = $this->addSubtaskUnder($this->child, 'Grandchild');

        // The grandchild's parent is $child, not $root — addressing it via the
        // root must 404 (the route enforces immediate parentage).
        $this->actingAs($this->owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->patch(route('tasks.subtasks.update', [$this->root->uuid, $grandchild->uuid]), ['title' => 'x'])
            ->assertNotFound();
    }

    public function test_can_complete_and_reopen_a_nested_subtask_independently(): void
    {
        $grandchild = $this->addSubtaskUnder($this->child, 'Completable');

        $this->actingAs($this->owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('tasks.complete', $grandchild->uuid))
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', ['id' => $grandchild->id, 'completed' => true]);
        // Completing a nested subtask must not flip its parent.
        $this->assertDatabaseHas('tasks', ['id' => $this->child->id, 'completed' => false]);

        $this->actingAs($this->owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('tasks.uncomplete', $grandchild->uuid))
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', ['id' => $grandchild->id, 'completed' => false]);
    }

    // ─── Deleting subtrees ────────────────────────────────────────────────────

    public function test_deleting_a_mid_level_subtask_removes_its_whole_subtree(): void
    {
        $level2 = $this->addSubtaskUnder($this->child, 'Level 2');
        $level3 = $this->addSubtaskUnder($level2, 'Level 3');
        $sibling = $this->addSubtaskUnder($this->child, 'Level 2 sibling');

        $this->actingAs($this->owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->delete(route('tasks.destroy', $level2->uuid))
            ->assertRedirect();

        // $level2 and its descendant $level3 are gone — and never orphaned to the
        // top level (parent_task_id left dangling).
        $this->assertDatabaseMissing('tasks', ['id' => $level2->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $level3->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $level3->id, 'parent_task_id' => null]);

        // Siblings and ancestors survive.
        $this->assertDatabaseHas('tasks', ['id' => $sibling->id]);
        $this->assertDatabaseHas('tasks', ['id' => $this->child->id]);
        $this->assertDatabaseHas('tasks', ['id' => $this->root->id]);
    }

    public function test_deleting_the_root_task_removes_every_descendant(): void
    {
        $level2 = $this->addSubtaskUnder($this->child, 'Level 2');
        $level3 = $this->addSubtaskUnder($level2, 'Level 3');

        $this->actingAs($this->owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->delete(route('tasks.destroy', $this->root->uuid))
            ->assertRedirect();

        $this->assertSame(0, Task::whereIn('id', [
            $this->root->id, $this->child->id, $level2->id, $level3->id,
        ])->count());
    }

    // ─── Authorization ────────────────────────────────────────────────────────

    public function test_non_member_cannot_create_a_nested_subtask(): void
    {
        // Onboarded (owns an unrelated workspace) but not a member of this
        // project — so the gate that fires is project access (403).
        $outsider = User::factory()->create();
        $outsiderWs = Workspace::create([
            'name'     => 'Outsider Workspace',
            'owner_id' => $outsider->id,
            'color'    => '#000000',
        ]);
        $outsiderWs->users()->attach($outsider->id, ['role' => 'owner']);

        $this->actingAs($outsider)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('tasks.subtasks.store', $this->child->uuid), ['title' => 'x'])
            ->assertForbidden();

        $this->assertDatabaseMissing('tasks', ['title' => 'x', 'parent_task_id' => $this->child->id]);
    }
}
