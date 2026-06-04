<?php

namespace Tests\Feature\Task;

use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubtaskUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Workspace $workspace;
    private Project $project;
    private Sprint $sprint;
    private BoardColumn $column;
    private Task $parentTask;
    private Task $subtask;

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

        $this->parentTask = Task::create([
            'key'             => 'TST-1',
            'title'           => 'Parent task',
            'priority'        => 'med',
            'project_id'      => $this->project->id,
            'board_column_id' => $this->column->id,
            'created_by'      => $this->owner->id,
        ]);

        $this->subtask = Task::create([
            'key'             => 'TST-2',
            'title'           => 'Subtask',
            'priority'        => 'med',
            'project_id'      => $this->project->id,
            'board_column_id' => $this->column->id,
            'parent_task_id'  => $this->parentTask->id,
            'created_by'      => $this->owner->id,
        ]);
    }

    private function patchSubtask(array $data, ?User $user = null): \Illuminate\Testing\TestResponse
    {
        $user ??= $this->owner;
        return $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->patch(route('tasks.subtasks.update', [$this->parentTask->uuid, $this->subtask->uuid]), $data);
    }

    // ─── Happy-path field updates ───────────────────────────────────────────

    public function test_update_subtask_title(): void
    {
        $this->patchSubtask(['title' => 'New title'])->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id'    => $this->subtask->id,
            'title' => 'New title',
        ]);
    }

    public function test_update_subtask_priority(): void
    {
        $this->patchSubtask(['priority' => 'urgent'])->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id'       => $this->subtask->id,
            'priority' => 'urgent',
        ]);
    }

    public function test_update_subtask_assignee(): void
    {
        $member = User::factory()->create();
        $this->workspace->users()->attach($member->id, ['role' => 'member']);
        $this->project->members()->attach($member->id, ['role' => 'member']);

        $this->patchSubtask(['assignee_id' => $member->id])->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id'          => $this->subtask->id,
            'assignee_id' => $member->id,
        ]);
    }

    public function test_update_subtask_assignee_to_null(): void
    {
        $this->subtask->update(['assignee_id' => $this->owner->id]);

        $this->patchSubtask(['assignee_id' => null])->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id'          => $this->subtask->id,
            'assignee_id' => null,
        ]);
    }

    public function test_update_subtask_due_date(): void
    {
        $this->patchSubtask(['due_date' => '2026-06-30'])->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id'       => $this->subtask->id,
            'due_date' => '2026-06-30 00:00:00',
        ]);
    }

    public function test_update_subtask_due_date_to_null(): void
    {
        $this->subtask->update(['due_date' => '2026-06-30']);

        $this->patchSubtask(['due_date' => null])->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id'       => $this->subtask->id,
            'due_date' => null,
        ]);
    }

    public function test_update_subtask_tags(): void
    {
        $this->patchSubtask(['tags' => ['frontend', 'bug']])->assertRedirect();

        $subtask = Task::find($this->subtask->id);
        $this->assertEquals(['frontend', 'bug'], $subtask->tags);
    }

    public function test_update_subtask_tags_to_empty(): void
    {
        $this->subtask->update(['tags' => ['frontend']]);

        $this->patchSubtask(['tags' => []])->assertRedirect();

        $subtask = Task::find($this->subtask->id);
        $this->assertEquals([], $subtask->tags);
    }

    public function test_update_subtask_description(): void
    {
        $this->patchSubtask(['description' => 'A new description'])->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id'          => $this->subtask->id,
            'description' => 'A new description',
        ]);
    }

    public function test_update_subtask_description_to_null(): void
    {
        $this->subtask->update(['description' => 'old']);

        $this->patchSubtask(['description' => null])->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id'          => $this->subtask->id,
            'description' => null,
        ]);
    }

    public function test_multiple_fields_updated_at_once(): void
    {
        $this->patchSubtask([
            'priority'    => 'high',
            'description' => 'Combined update',
            'tags'        => ['backend'],
        ])->assertRedirect();

        $subtask = Task::find($this->subtask->id);
        $this->assertEquals('high', $subtask->priority);
        $this->assertEquals('Combined update', $subtask->description);
        $this->assertEquals(['backend'], $subtask->tags);
    }

    public function test_audit_log_created_on_update(): void
    {
        $this->patchSubtask(['priority' => 'high'])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'task_id' => $this->parentTask->id,
            'user_id' => $this->owner->id,
            'action'  => 'task.subtask_updated',
        ]);
    }

    // ─── Validation ─────────────────────────────────────────────────────────

    public function test_invalid_priority_rejected(): void
    {
        $this->patchSubtask(['priority' => 'invalid'])->assertUnprocessable();

        $this->assertDatabaseHas('tasks', [
            'id'       => $this->subtask->id,
            'priority' => 'med',
        ]);
    }

    public function test_title_too_long_rejected(): void
    {
        $this->patchSubtask(['title' => str_repeat('a', 256)])->assertUnprocessable();
    }

    public function test_invalid_due_date_rejected(): void
    {
        $this->patchSubtask(['due_date' => 'not-a-date'])->assertUnprocessable();
    }

    public function test_assignee_must_exist(): void
    {
        $this->patchSubtask(['assignee_id' => 99999])->assertUnprocessable();
    }

    public function test_tags_must_be_array(): void
    {
        $this->patchSubtask(['tags' => 'not-an-array'])->assertUnprocessable();
    }

    // ─── Authorization ───────────────────────────────────────────────────────

    public function test_unauthenticated_cannot_update_subtask(): void
    {
        $this->withHeaders(['Accept' => 'application/json'])
            ->patch(route('tasks.subtasks.update', [$this->parentTask->uuid, $this->subtask->uuid]), ['title' => 'x'])
            ->assertUnauthorized();
    }

    public function test_non_member_cannot_update_subtask(): void
    {
        // The outsider owns their own (unrelated) workspace so they clear the
        // onboarding gate — the point under test is project-level access (403),
        // not onboarding.
        $outsider = User::factory()->create();
        $outsiderWs = Workspace::create([
            'name'     => 'Outsider Workspace',
            'owner_id' => $outsider->id,
            'color'    => '#000000',
        ]);
        $outsiderWs->users()->attach($outsider->id, ['role' => 'owner']);

        $this->actingAs($outsider)
            ->withHeaders(['Accept' => 'application/json'])
            ->patch(route('tasks.subtasks.update', [$this->parentTask->uuid, $this->subtask->uuid]), ['title' => 'x'])
            ->assertForbidden();
    }

    public function test_project_member_can_update_subtask(): void
    {
        $member = User::factory()->create();
        $this->workspace->users()->attach($member->id, ['role' => 'member']);
        $this->project->members()->attach($member->id, ['role' => 'member']);

        $this->actingAs($member)
            ->withHeaders(['Accept' => 'application/json'])
            ->patch(route('tasks.subtasks.update', [$this->parentTask->uuid, $this->subtask->uuid]), ['title' => 'By member'])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id'    => $this->subtask->id,
            'title' => 'By member',
        ]);
    }

    public function test_cannot_update_subtask_that_belongs_to_different_parent(): void
    {
        $otherParent = Task::create([
            'key'             => 'TST-3',
            'title'           => 'Other parent',
            'priority'        => 'med',
            'project_id'      => $this->project->id,
            'board_column_id' => $this->column->id,
            'created_by'      => $this->owner->id,
        ]);

        // Route uses $otherParent but $this->subtask belongs to $this->parentTask
        $this->actingAs($this->owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->patch(route('tasks.subtasks.update', [$otherParent->uuid, $this->subtask->uuid]), ['title' => 'x'])
            ->assertNotFound();
    }
}
