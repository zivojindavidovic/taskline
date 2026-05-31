<?php

namespace Tests\Feature;

use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskCommentTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Project $project;
    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->project = Project::create([
            'name'     => 'Test Project',
            'key'      => 'TST',
            'color'    => '#4f46e5',
            'owner_id' => $this->owner->id,
        ]);
        $this->project->members()->attach($this->owner->id, ['role' => 'owner']);

        $sprint = Sprint::create(['project_id' => $this->project->id, 'name' => 'Sprint 1']);
        $column = BoardColumn::create([
            'project_id' => $this->project->id,
            'name'       => 'Todo',
            'color'      => '#94948c',
            'position'   => 0,
        ]);
        $this->task = Task::create([
            'key'             => 'TST-1',
            'title'           => 'Test Task',
            'project_id'      => $this->project->id,
            'sprint_id'       => $sprint->id,
            'board_column_id' => $column->id,
            'created_by'      => $this->owner->id,
            'priority'        => 'med',
        ]);
    }

    public function test_member_can_add_a_comment(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson("/api/projects/{$this->project->uuid}/tasks/{$this->task->uuid}/comments", [
                'body' => 'This is a comment.',
            ]);

        $response->assertCreated()->assertJsonFragment(['body' => 'This is a comment.']);
        $this->assertDatabaseHas('task_comments', [
            'task_id' => $this->task->id,
            'user_id' => $this->owner->id,
        ]);
    }

    public function test_comment_requires_body(): void
    {
        $this->actingAs($this->owner)
            ->postJson("/api/projects/{$this->project->uuid}/tasks/{$this->task->uuid}/comments", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');
    }

    public function test_non_member_cannot_comment(): void
    {
        $other = User::factory()->create();

        $this->actingAs($other)
            ->postJson("/api/projects/{$this->project->uuid}/tasks/{$this->task->uuid}/comments", [
                'body' => 'Unauthorized comment.',
            ])
            ->assertForbidden();
    }

    public function test_member_can_reply_to_a_comment(): void
    {
        $comment = TaskComment::create([
            'task_id' => $this->task->id,
            'user_id' => $this->owner->id,
            'body'    => 'Parent comment.',
        ]);

        $this->actingAs($this->owner)
            ->postJson(
                "/api/projects/{$this->project->uuid}/tasks/{$this->task->uuid}/comments/{$comment->id}/reply",
                ['body' => 'This is a reply.']
            )
            ->assertCreated()
            ->assertJsonFragment(['body' => 'This is a reply.']);

        $this->assertDatabaseHas('comment_replies', [
            'task_comment_id' => $comment->id,
            'user_id'         => $this->owner->id,
        ]);
    }

    public function test_author_can_delete_own_comment(): void
    {
        $comment = TaskComment::create([
            'task_id' => $this->task->id,
            'user_id' => $this->owner->id,
            'body'    => 'Delete me.',
        ]);

        $this->actingAs($this->owner)
            ->deleteJson(
                "/api/projects/{$this->project->uuid}/tasks/{$this->task->uuid}/comments/{$comment->id}"
            )
            ->assertNoContent();

        $this->assertDatabaseMissing('task_comments', ['id' => $comment->id]);
    }

    public function test_non_author_cannot_delete_comment(): void
    {
        $other   = User::factory()->create();
        $this->project->members()->attach($other->id, ['role' => 'member']);

        $comment = TaskComment::create([
            'task_id' => $this->task->id,
            'user_id' => $this->owner->id,
            'body'    => 'Do not delete me.',
        ]);

        $this->actingAs($other)
            ->deleteJson(
                "/api/projects/{$this->project->uuid}/tasks/{$this->task->uuid}/comments/{$comment->id}"
            )
            ->assertForbidden();
    }
}
