<?php

namespace Tests\Feature;

use App\Models\BoardColumn;
use App\Models\CommentMention;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\TaskAccessRequest;
use App\Models\TaskComment;
use App\Models\User;
use App\Models\Workspace;
use App\Services\InboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskAccessRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Workspace $workspace;
    private Project $project;
    private Sprint $sprint;
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
        $this->sprint = Sprint::create(['project_id' => $this->project->id, 'name' => 'S1']);
        $this->column = BoardColumn::create([
            'project_id' => $this->project->id,
            'name'       => 'Todo',
            'color'      => '#94948c',
            'position'   => 0,
        ]);
    }

    private function attachMember(User $user, string $role = 'member'): void
    {
        $this->workspace->users()->attach($user->id, ['role' => $role]);
        $this->project->members()->attach($user->id, ['role' => $role]);
    }

    /**
     * A user who belongs to the workspace (so they clear onboarding) but is NOT
     * a member of this project — the realistic "no access to the task" case.
     */
    private function makeOutsider(): User
    {
        $user = User::factory()->create();
        $this->workspace->users()->attach($user->id, ['role' => 'member']);
        $user->update(['current_workspace_id' => $this->workspace->id]);

        return $user;
    }

    private function makeTask(array $attrs = []): Task
    {
        return Task::create(array_merge([
            'key'             => 'MOB-' . uniqid(),
            'title'           => 'Empty-state illustrations',
            'project_id'      => $this->project->id,
            'sprint_id'       => $this->sprint->id,
            'board_column_id' => $this->column->id,
            'created_by'      => $this->owner->id,
            'priority'        => 'med',
        ], $attrs));
    }

    // ── details(): no-access payload ─────────────────────────────────────────

    public function test_non_member_gets_redacted_no_access_payload(): void
    {
        $outsider = $this->makeOutsider();
        $task = $this->makeTask();

        $response = $this->actingAs($outsider)->getJson("/tasks/{$task->id}/details");

        $response->assertForbidden()
            ->assertJson([
                'hasAccess' => false,
                'task'      => ['key' => $task->key],
                'project'   => ['name' => 'Mobile App v3'],
                'pendingRequest' => null,
            ]);
        // Real content (title, description, comments) must NOT leak.
        $response->assertJsonMissingPath('task.title');
    }

    public function test_no_access_payload_reports_existing_pending_request(): void
    {
        $outsider = $this->makeOutsider();
        $task = $this->makeTask();
        TaskAccessRequest::create([
            'task_id' => $task->id,
            'user_id' => $outsider->id,
            'status'  => TaskAccessRequest::STATUS_PENDING,
        ]);

        $this->actingAs($outsider)->getJson("/tasks/{$task->id}/details")
            ->assertForbidden()
            ->assertJsonPath('pendingRequest.status', 'pending');
    }

    public function test_member_gets_full_task_details(): void
    {
        $alice = User::factory()->create();
        $this->attachMember($alice);
        $task = $this->makeTask();

        $this->actingAs($alice)->getJson("/tasks/{$task->id}/details")
            ->assertOk()
            ->assertJsonPath('task.title', 'Empty-state illustrations');
    }

    // ── store(): requesting access ───────────────────────────────────────────

    public function test_non_member_can_request_access(): void
    {
        $outsider = $this->makeOutsider();
        $task = $this->makeTask();

        $this->actingAs($outsider)
            ->postJson("/tasks/{$task->id}/access-requests", ['message' => 'Need context'])
            ->assertCreated()
            ->assertJsonPath('status', 'pending');

        $this->assertDatabaseHas('task_access_requests', [
            'task_id' => $task->id,
            'user_id' => $outsider->id,
            'status'  => 'pending',
            'message' => 'Need context',
        ]);
    }

    public function test_requesting_access_twice_keeps_one_pending_row(): void
    {
        $outsider = $this->makeOutsider();
        $task = $this->makeTask();

        $this->actingAs($outsider)->postJson("/tasks/{$task->id}/access-requests")->assertCreated();
        $this->actingAs($outsider)->postJson("/tasks/{$task->id}/access-requests")->assertCreated();

        $this->assertSame(1, TaskAccessRequest::where('task_id', $task->id)
            ->where('user_id', $outsider->id)->count());
    }

    public function test_re_requesting_after_decline_flips_back_to_pending(): void
    {
        $outsider = $this->makeOutsider();
        $task = $this->makeTask();
        $req = TaskAccessRequest::create([
            'task_id' => $task->id,
            'user_id' => $outsider->id,
            'status'  => TaskAccessRequest::STATUS_DECLINED,
        ]);

        $this->actingAs($outsider)->postJson("/tasks/{$task->id}/access-requests")->assertCreated();

        $this->assertSame('pending', $req->fresh()->status);
    }

    public function test_member_cannot_request_access(): void
    {
        $alice = User::factory()->create();
        $this->attachMember($alice);
        $task = $this->makeTask();

        $this->actingAs($alice)
            ->postJson("/tasks/{$task->id}/access-requests")
            ->assertStatus(422);

        $this->assertDatabaseCount('task_access_requests', 0);
    }

    // ── index(): listing requests ────────────────────────────────────────────

    public function test_owner_sees_pending_requests_and_can_manage(): void
    {
        $outsider = $this->makeOutsider();
        $task = $this->makeTask();
        TaskAccessRequest::create([
            'task_id' => $task->id, 'user_id' => $outsider->id, 'status' => 'pending',
        ]);

        $response = $this->actingAs($this->owner)->getJson("/tasks/{$task->id}/access-requests");

        $response->assertOk()
            ->assertJsonPath('can_manage', true)
            ->assertJsonPath('requests.0.user.id', $outsider->id);
    }

    public function test_plain_member_can_view_but_not_manage(): void
    {
        $alice = User::factory()->create();
        $this->attachMember($alice, 'member');
        $task = $this->makeTask();

        $this->actingAs($alice)->getJson("/tasks/{$task->id}/access-requests")
            ->assertOk()
            ->assertJsonPath('can_manage', false);
    }

    public function test_admin_member_can_manage(): void
    {
        $admin = User::factory()->create();
        $this->attachMember($admin, 'admin');
        $task = $this->makeTask();

        $this->actingAs($admin)->getJson("/tasks/{$task->id}/access-requests")
            ->assertOk()
            ->assertJsonPath('can_manage', true);
    }

    public function test_non_member_cannot_list_requests(): void
    {
        $outsider = $this->makeOutsider();
        $task = $this->makeTask();

        $this->actingAs($outsider)->getJson("/tasks/{$task->id}/access-requests")
            ->assertForbidden();
    }

    // ── approve / decline ────────────────────────────────────────────────────

    public function test_owner_can_approve_and_grant_task_level_access(): void
    {
        $outsider = $this->makeOutsider();
        $task = $this->makeTask();
        $req = TaskAccessRequest::create([
            'task_id' => $task->id, 'user_id' => $outsider->id, 'status' => 'pending',
        ]);

        $this->actingAs($this->owner)
            ->postJson("/tasks/{$task->id}/access-requests/{$req->id}/approve")
            ->assertOk()
            ->assertJsonPath('status', 'approved');

        $this->assertSame($this->owner->id, $req->fresh()->reviewed_by);

        // Task-level, not project-level: the grant must NOT make them a project member.
        $this->assertFalse(
            $this->project->members()->where('users.id', $outsider->id)->exists(),
            'Approving a task request must not add the user to the project'
        );

        // They can open exactly this task, with full content…
        $this->actingAs($outsider)->getJson("/tasks/{$task->id}/details")
            ->assertOk()
            ->assertJsonPath('task.title', 'Empty-state illustrations');

        // …but a sibling task in the same project stays locked.
        $sibling = $this->makeTask(['title' => 'Other task']);
        $this->actingAs($outsider)->getJson("/tasks/{$sibling->id}/details")->assertForbidden();

        // …and they still can't open the project board (not a member).
        $this->actingAs($outsider)->get("/projects/{$this->project->id}")->assertForbidden();
    }

    public function test_granted_user_can_comment_on_task(): void
    {
        $outsider = $this->makeOutsider();
        $task = $this->makeTask();
        TaskAccessRequest::create([
            'task_id' => $task->id, 'user_id' => $outsider->id, 'status' => 'approved',
        ]);

        $this->actingAs($outsider)
            ->post("/tasks/{$task->id}/comments", ['body' => 'Thanks for the access!'])
            ->assertRedirect();

        $this->assertDatabaseHas('task_comments', [
            'task_id' => $task->id,
            'user_id' => $outsider->id,
            'body'    => 'Thanks for the access!',
        ]);
    }

    public function test_granted_user_appears_as_guest_participant(): void
    {
        $outsider = $this->makeOutsider();
        $task = $this->makeTask();
        TaskAccessRequest::create([
            'task_id' => $task->id, 'user_id' => $outsider->id, 'status' => 'approved',
        ]);

        $participants = $this->actingAs($this->owner)
            ->getJson("/tasks/{$task->id}/participants")
            ->assertOk()
            ->json();

        $entry = collect($participants)->firstWhere('id', $outsider->id);
        $this->assertNotNull($entry, 'Granted user should surface as a participant');
        $this->assertContains('guest', $entry['roles']);
    }

    public function test_declining_an_approved_request_revokes_access(): void
    {
        $outsider = $this->makeOutsider();
        $task = $this->makeTask();
        $req = TaskAccessRequest::create([
            'task_id' => $task->id, 'user_id' => $outsider->id, 'status' => 'approved',
        ]);

        // Access works while approved…
        $this->actingAs($outsider)->getJson("/tasks/{$task->id}/details")->assertOk();

        // …declining the grant revokes it.
        $this->actingAs($this->owner)
            ->postJson("/tasks/{$task->id}/access-requests/{$req->id}/decline")
            ->assertOk();

        $this->actingAs($outsider)->getJson("/tasks/{$task->id}/details")->assertForbidden();
    }

    public function test_owner_can_decline_without_granting_access(): void
    {
        $outsider = $this->makeOutsider();
        $task = $this->makeTask();
        $req = TaskAccessRequest::create([
            'task_id' => $task->id, 'user_id' => $outsider->id, 'status' => 'pending',
        ]);

        $this->actingAs($this->owner)
            ->postJson("/tasks/{$task->id}/access-requests/{$req->id}/decline")
            ->assertOk()
            ->assertJsonPath('status', 'declined');

        $this->assertFalse($this->project->members()->where('users.id', $outsider->id)->exists());
        $this->actingAs($outsider)->getJson("/tasks/{$task->id}/details")->assertForbidden();
    }

    public function test_plain_member_cannot_approve(): void
    {
        $alice = User::factory()->create();
        $this->attachMember($alice, 'member');
        $outsider = $this->makeOutsider();
        $task = $this->makeTask();
        $req = TaskAccessRequest::create([
            'task_id' => $task->id, 'user_id' => $outsider->id, 'status' => 'pending',
        ]);

        $this->actingAs($alice)
            ->postJson("/tasks/{$task->id}/access-requests/{$req->id}/approve")
            ->assertForbidden();

        $this->assertSame('pending', $req->fresh()->status);
    }

    public function test_outsider_cannot_approve(): void
    {
        $outsider = $this->makeOutsider();
        $task = $this->makeTask();
        $req = TaskAccessRequest::create([
            'task_id' => $task->id, 'user_id' => $outsider->id, 'status' => 'pending',
        ]);

        $this->actingAs($outsider)
            ->postJson("/tasks/{$task->id}/access-requests/{$req->id}/approve")
            ->assertForbidden();
    }

    public function test_cannot_resolve_request_belonging_to_another_task(): void
    {
        $outsider = $this->makeOutsider();
        $taskA = $this->makeTask();
        $taskB = $this->makeTask();
        $req = TaskAccessRequest::create([
            'task_id' => $taskB->id, 'user_id' => $outsider->id, 'status' => 'pending',
        ]);

        $this->actingAs($this->owner)
            ->postJson("/tasks/{$taskA->id}/access-requests/{$req->id}/approve")
            ->assertNotFound();
    }

    // ── Inbox: restricted flag ───────────────────────────────────────────────

    public function test_inbox_flags_mention_in_inaccessible_project_as_restricted(): void
    {
        // A task in a project the outsider is NOT a member of.
        $task = $this->makeTask();
        $comment = TaskComment::create([
            'task_id' => $task->id,
            'user_id' => $this->owner->id,
            'body'    => 'Hey @[Outsider](user:1), can you help here?',
        ]);
        $outsider = $this->makeOutsider();
        CommentMention::create([
            'task_comment_id' => $comment->id,
            'user_id'         => $outsider->id,
        ]);

        $feed = app(InboxService::class)->build($outsider);
        $mention = $feed->firstWhere('type', 'mention');

        $this->assertNotNull($mention, 'Outsider should still receive the mention notification');
        $this->assertTrue($mention['restricted'], 'Mention into a non-member project is restricted');
    }

    public function test_inbox_mention_in_accessible_project_is_not_restricted(): void
    {
        $member = User::factory()->create();
        $this->attachMember($member);
        $task = $this->makeTask();
        $comment = TaskComment::create([
            'task_id' => $task->id,
            'user_id' => $this->owner->id,
            'body'    => 'Ping @[Member](user:1)',
        ]);
        CommentMention::create([
            'task_comment_id' => $comment->id,
            'user_id'         => $member->id,
        ]);

        $feed = app(InboxService::class)->build($member);
        $mention = $feed->firstWhere('type', 'mention');

        $this->assertNotNull($mention);
        $this->assertFalse($mention['restricted']);
    }

    public function test_inbox_grant_clears_restricted_flag_for_non_member(): void
    {
        // Same setup as the "restricted" case, but the outsider now holds an
        // approved task-level grant — the mention should no longer be locked.
        $task = $this->makeTask();
        $comment = TaskComment::create([
            'task_id' => $task->id,
            'user_id' => $this->owner->id,
            'body'    => 'Hey @[Outsider](user:1), can you help here?',
        ]);
        $outsider = $this->makeOutsider();
        CommentMention::create([
            'task_comment_id' => $comment->id,
            'user_id'         => $outsider->id,
        ]);
        TaskAccessRequest::create([
            'task_id' => $task->id, 'user_id' => $outsider->id, 'status' => 'approved',
        ]);

        $feed = app(InboxService::class)->build($outsider);
        $mention = $feed->firstWhere('type', 'mention');

        $this->assertNotNull($mention);
        $this->assertFalse(
            $mention['restricted'],
            'A granted task should not be flagged restricted even without project membership'
        );
    }
}
