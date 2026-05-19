<?php

namespace Tests\Feature;

use App\Events\CommentAdded;
use App\Events\ReplyAdded;
use App\Models\BoardColumn;
use App\Models\CommentReply;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CommentBroadcastingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $alice;
    private User $bob;
    private Workspace $workspace;
    private Project $project;
    private Sprint $sprint;
    private BoardColumn $column;
    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['name' => 'Owner']);
        $this->alice = User::factory()->create(['name' => 'Alice']);
        $this->bob   = User::factory()->create(['name' => 'Bob']);

        $this->workspace = Workspace::create([
            'name'     => 'WS',
            'owner_id' => $this->owner->id,
            'color'    => '#4f46e5',
        ]);
        $this->workspace->users()->attach([
            $this->owner->id => ['role' => 'owner'],
            $this->alice->id => ['role' => 'member'],
            $this->bob->id   => ['role' => 'member'],
        ]);
        $this->owner->update(['current_workspace_id' => $this->workspace->id]);

        $this->project = Project::create([
            'name'         => 'Proj',
            'key'          => 'PRJ',
            'color'        => '#4f46e5',
            'owner_id'     => $this->owner->id,
            'workspace_id' => $this->workspace->id,
        ]);
        $this->project->members()->attach([
            $this->owner->id => ['role' => 'owner'],
            $this->alice->id => ['role' => 'member'],
            $this->bob->id   => ['role' => 'member'],
        ]);

        $this->sprint = Sprint::create([
            'project_id' => $this->project->id,
            'name'       => 'Sprint 1',
            'status'     => 'active',
            'locked'     => false,
        ]);

        $this->column = BoardColumn::create([
            'project_id' => $this->project->id,
            'name'       => 'Todo',
            'color'      => '#94948c',
            'position'   => 0,
        ]);

        $this->task = Task::create([
            'key'             => 'PRJ-1',
            'title'           => 'Task',
            'project_id'      => $this->project->id,
            'sprint_id'       => $this->sprint->id,
            'board_column_id' => $this->column->id,
            'created_by'      => $this->owner->id,
            'priority'        => 'med',
        ]);
    }

    // ---------- CommentAdded ----------

    public function test_storing_a_comment_dispatches_comment_added_event(): void
    {
        Event::fake([CommentAdded::class]);

        $this->actingAs($this->alice)
            ->post(route('tasks.comments.store', $this->task), [
                'body' => 'Hello world',
            ])
            ->assertRedirect();

        Event::assertDispatched(CommentAdded::class, function (CommentAdded $e) {
            return $e->task->id === $this->task->id
                && $e->comment->body === 'Hello world'
                && (int) $e->comment->user_id === (int) $this->alice->id;
        });
    }

    public function test_comment_added_event_broadcasts_on_project_channel(): void
    {
        $comment = TaskComment::create([
            'task_id' => $this->task->id,
            'user_id' => $this->alice->id,
            'body'    => 'Hi',
        ]);

        $channels = (new CommentAdded($this->task, $comment))->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertSame('private-project.' . $this->project->id, $channels[0]->name);
    }

    public function test_comment_added_broadcast_payload_includes_user_and_mentioned_users(): void
    {
        $comment = TaskComment::create([
            'task_id' => $this->task->id,
            'user_id' => $this->alice->id,
            'body'    => 'Hi @[Bob](user:' . $this->bob->id . ')',
        ]);
        $comment->mentionedUsers()->attach($this->bob->id);

        $payload = (new CommentAdded($this->task, $comment))->broadcastWith();

        $this->assertSame($this->task->id, $payload['task_id']);
        $this->assertSame($comment->id, $payload['comment']['id']);
        $this->assertSame($this->alice->id, $payload['comment']['user']['id']);
        $this->assertCount(1, $payload['comment']['mentioned_users']);
        $this->assertSame($this->bob->id, $payload['comment']['mentioned_users'][0]['id']);
        $this->assertIsArray($payload['comment']['replies']);
    }

    public function test_comment_added_uses_should_broadcast_now(): void
    {
        $this->assertInstanceOf(
            \Illuminate\Contracts\Broadcasting\ShouldBroadcastNow::class,
            new CommentAdded($this->task, TaskComment::create([
                'task_id' => $this->task->id,
                'user_id' => $this->owner->id,
                'body'    => 'x',
            ])),
        );
    }

    public function test_failing_validation_does_not_dispatch_comment_added(): void
    {
        Event::fake([CommentAdded::class]);

        $this->actingAs($this->alice)
            ->from('/dashboard')
            ->post(route('tasks.comments.store', $this->task), ['body' => ''])
            ->assertRedirect();

        Event::assertNotDispatched(CommentAdded::class);
    }

    public function test_outsider_cannot_comment_and_no_event_is_dispatched(): void
    {
        Event::fake([CommentAdded::class]);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->post(route('tasks.comments.store', $this->task), ['body' => 'sneaky'])
            ->assertStatus(403);

        Event::assertNotDispatched(CommentAdded::class);
    }

    // ---------- ReplyAdded ----------

    public function test_storing_a_reply_dispatches_reply_added_event(): void
    {
        Event::fake([ReplyAdded::class]);

        $comment = TaskComment::create([
            'task_id' => $this->task->id,
            'user_id' => $this->alice->id,
            'body'    => 'parent',
        ]);

        $this->actingAs($this->bob)
            ->post(route('tasks.comments.reply', [$this->task, $comment]), [
                'body' => 'thanks!',
            ])
            ->assertRedirect();

        Event::assertDispatched(ReplyAdded::class, function (ReplyAdded $e) use ($comment) {
            return $e->task->id === $this->task->id
                && $e->reply->task_comment_id === $comment->id
                && $e->reply->body === 'thanks!'
                && (int) $e->reply->user_id === (int) $this->bob->id;
        });
    }

    public function test_reply_added_event_broadcasts_on_project_channel(): void
    {
        $comment = TaskComment::create([
            'task_id' => $this->task->id,
            'user_id' => $this->alice->id,
            'body'    => 'parent',
        ]);
        $reply = CommentReply::create([
            'task_comment_id' => $comment->id,
            'user_id'         => $this->bob->id,
            'body'            => 'reply',
        ]);

        $channels = (new ReplyAdded($this->task, $reply))->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertSame('private-project.' . $this->project->id, $channels[0]->name);
    }

    public function test_reply_added_broadcast_payload_includes_user_and_mentioned_users(): void
    {
        $comment = TaskComment::create([
            'task_id' => $this->task->id,
            'user_id' => $this->alice->id,
            'body'    => 'parent',
        ]);
        $reply = CommentReply::create([
            'task_comment_id' => $comment->id,
            'user_id'         => $this->bob->id,
            'body'            => 'Yo @[Alice](user:' . $this->alice->id . ')',
        ]);
        $reply->mentionedUsers()->attach($this->alice->id);

        $payload = (new ReplyAdded($this->task, $reply))->broadcastWith();

        $this->assertSame($this->task->id, $payload['task_id']);
        $this->assertSame($comment->id, $payload['comment_id']);
        $this->assertSame($reply->id, $payload['reply']['id']);
        $this->assertSame($this->bob->id, $payload['reply']['user']['id']);
        $this->assertCount(1, $payload['reply']['mentioned_users']);
        $this->assertSame($this->alice->id, $payload['reply']['mentioned_users'][0]['id']);
    }

    public function test_reply_added_uses_should_broadcast_now(): void
    {
        $comment = TaskComment::create([
            'task_id' => $this->task->id,
            'user_id' => $this->alice->id,
            'body'    => 'parent',
        ]);
        $reply = CommentReply::create([
            'task_comment_id' => $comment->id,
            'user_id'         => $this->bob->id,
            'body'            => 'reply',
        ]);

        $this->assertInstanceOf(
            \Illuminate\Contracts\Broadcasting\ShouldBroadcastNow::class,
            new ReplyAdded($this->task, $reply),
        );
    }

    public function test_failing_validation_on_reply_does_not_dispatch_event(): void
    {
        Event::fake([ReplyAdded::class]);
        $comment = TaskComment::create([
            'task_id' => $this->task->id,
            'user_id' => $this->alice->id,
            'body'    => 'parent',
        ]);

        $this->actingAs($this->bob)
            ->from('/dashboard')
            ->post(route('tasks.comments.reply', [$this->task, $comment]), ['body' => ''])
            ->assertRedirect();

        Event::assertNotDispatched(ReplyAdded::class);
    }
}
