<?php

namespace Tests\Feature;

use App\Models\BoardColumn;
use App\Models\CommentMention;
use App\Models\CommentReply;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentMentionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $alice;
    private User $bob;
    private User $outsider;
    private Workspace $workspace;
    private Project $project;
    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner    = User::factory()->create(['name' => 'Owner']);
        $this->alice    = User::factory()->create(['name' => 'Alice']);
        $this->bob      = User::factory()->create(['name' => 'Bob']);
        $this->outsider = User::factory()->create(['name' => 'Outsider']);

        $this->workspace = Workspace::create([
            'name'     => 'WS',
            'owner_id' => $this->owner->id,
            'color'    => '#000',
        ]);
        $this->workspace->users()->attach([
            $this->owner->id => ['role' => 'owner'],
            $this->alice->id => ['role' => 'member'],
            $this->bob->id   => ['role' => 'member'],
        ]);

        $this->project = Project::create([
            'name'         => 'Test Project',
            'key'          => 'TST',
            'color'        => '#000',
            'owner_id'     => $this->owner->id,
            'workspace_id' => $this->workspace->id,
        ]);
        $this->project->members()->attach([
            $this->owner->id => ['role' => 'owner'],
            $this->alice->id => ['role' => 'member'],
            $this->bob->id   => ['role' => 'member'],
        ]);

        $sprint = Sprint::create([
            'project_id' => $this->project->id,
            'name'       => 'S1',
            'status'     => 'active',
            'locked'     => false,
        ]);
        $col = BoardColumn::create([
            'project_id' => $this->project->id,
            'name'       => 'Todo', 'color' => '#000', 'position' => 0,
        ]);

        $this->task = Task::create([
            'key'             => 'TST-1',
            'title'           => 'Task',
            'project_id'      => $this->project->id,
            'sprint_id'       => $sprint->id,
            'board_column_id' => $col->id,
            'created_by'      => $this->owner->id,
            'priority'        => 'med',
        ]);
    }

    // ---------- creating comments ----------

    public function test_storing_comment_parses_mention_tokens(): void
    {
        $this->actingAs($this->owner)
            ->post("/tasks/{$this->task->uuid}/comments", [
                'body' => "Hey @[Alice](user:{$this->alice->id}) take a look.",
            ])
            ->assertRedirect();

        $comment = TaskComment::firstOrFail();
        $this->assertDatabaseHas('comment_mentions', [
            'task_comment_id' => $comment->id,
            'user_id'         => $this->alice->id,
        ]);
    }

    public function test_storing_comment_parses_multiple_mentions(): void
    {
        $this->actingAs($this->owner)
            ->post("/tasks/{$this->task->uuid}/comments", [
                'body' => "Ping @[Alice](user:{$this->alice->id}) and @[Bob](user:{$this->bob->id}).",
            ])
            ->assertRedirect();

        $comment = TaskComment::firstOrFail();
        $this->assertSame(
            [$this->alice->id, $this->bob->id],
            $comment->mentions()->orderBy('user_id')->pluck('user_id')->all()
        );
    }

    public function test_storing_comment_deduplicates_repeated_mentions(): void
    {
        $this->actingAs($this->owner)
            ->post("/tasks/{$this->task->uuid}/comments", [
                'body' => "Hi @[Alice](user:{$this->alice->id}) cc @[Alice](user:{$this->alice->id}).",
            ])
            ->assertRedirect();

        $comment = TaskComment::firstOrFail();
        $this->assertCount(1, $comment->mentions);
    }

    public function test_storing_comment_drops_self_mention(): void
    {
        $this->actingAs($this->owner)
            ->post("/tasks/{$this->task->uuid}/comments", [
                'body' => "Note to self @[Owner](user:{$this->owner->id}).",
            ])
            ->assertRedirect();

        $comment = TaskComment::firstOrFail();
        $this->assertCount(0, $comment->mentions);
    }

    public function test_storing_comment_drops_non_workspace_mention(): void
    {
        $this->actingAs($this->owner)
            ->post("/tasks/{$this->task->uuid}/comments", [
                'body' => "Hello @[Outsider](user:{$this->outsider->id}).",
            ])
            ->assertRedirect();

        $comment = TaskComment::firstOrFail();
        $this->assertCount(0, $comment->mentions);
    }

    public function test_storing_comment_without_mentions_persists_no_rows(): void
    {
        $this->actingAs($this->owner)
            ->post("/tasks/{$this->task->uuid}/comments", ['body' => 'plain text'])
            ->assertRedirect();

        $comment = TaskComment::firstOrFail();
        $this->assertCount(0, $comment->mentions);
    }

    // ---------- replies ----------

    public function test_reply_parses_mentions(): void
    {
        $comment = TaskComment::create([
            'task_id' => $this->task->id,
            'user_id' => $this->owner->id,
            'body'    => 'hi',
        ]);

        $this->actingAs($this->alice)
            ->post("/tasks/{$this->task->uuid}/comments/{$comment->id}/replies", [
                'body' => "Thanks @[Bob](user:{$this->bob->id}).",
            ])
            ->assertRedirect();

        $reply = CommentReply::firstOrFail();
        $this->assertDatabaseHas('comment_mentions', [
            'comment_reply_id' => $reply->id,
            'user_id'          => $this->bob->id,
        ]);
    }

    public function test_reply_drops_author_self_mention(): void
    {
        $comment = TaskComment::create([
            'task_id' => $this->task->id, 'user_id' => $this->owner->id, 'body' => 'hi',
        ]);

        $this->actingAs($this->alice)
            ->post("/tasks/{$this->task->uuid}/comments/{$comment->id}/replies", [
                'body' => "Me again @[Alice](user:{$this->alice->id}).",
            ])
            ->assertRedirect();

        $reply = CommentReply::firstOrFail();
        $this->assertCount(0, $reply->mentions);
    }

    // ---------- mentionable users endpoint ----------

    public function test_mentionable_endpoint_returns_workspace_members_minus_current(): void
    {
        $resp = $this->actingAs($this->alice)
            ->getJson("/tasks/{$this->task->uuid}/comments/mentionable-users")
            ->assertOk()
            ->json();

        $ids = collect($resp)->pluck('id')->all();
        $this->assertContains($this->owner->id, $ids);
        $this->assertContains($this->bob->id, $ids);
        $this->assertNotContains($this->alice->id, $ids);
        $this->assertNotContains($this->outsider->id, $ids);
    }

    public function test_mentionable_endpoint_blocks_non_member(): void
    {
        $this->actingAs($this->outsider)
            ->getJson("/tasks/{$this->task->uuid}/comments/mentionable-users")
            ->assertForbidden();
    }

    // ---------- guests ----------

    public function test_storing_comment_requires_auth(): void
    {
        $this->post("/tasks/{$this->task->uuid}/comments", ['body' => 'hi'])
            ->assertRedirect('/login');
    }

    // ---------- access ----------

    public function test_outsider_cannot_comment(): void
    {
        $this->actingAs($this->outsider)
            ->post("/tasks/{$this->task->uuid}/comments", [
                'body' => "Hi @[Alice](user:{$this->alice->id}).",
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('task_comments', 0);
    }

    // ---------- body preservation ----------

    public function test_comment_body_is_persisted_verbatim(): void
    {
        $body = "Hey @[Alice](user:{$this->alice->id}), see this.";
        $this->actingAs($this->owner)
            ->post("/tasks/{$this->task->uuid}/comments", ['body' => $body])
            ->assertRedirect();

        $this->assertDatabaseHas('task_comments', [
            'task_id' => $this->task->id,
            'body'    => $body,
        ]);
    }
}
