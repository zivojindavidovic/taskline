<?php

namespace Tests\Feature;

use App\Events\AuditLogRecorded;
use App\Events\BoardColumnUpdated;
use App\Events\CommentAdded;
use App\Events\InboxNotificationSent;
use App\Events\MemberProjectAccessUpdated;
use App\Events\ProjectCreated;
use App\Events\ProjectMembersChanged;
use App\Events\SprintUpdated;
use App\Events\TaskAccessRequestUpdated;
use App\Events\TaskDeleted;
use App\Events\TaskUpdated;
use App\Events\WorkspaceMembersChanged;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use App\Models\AuditLog;
use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\TaskAccessRequest;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Covers every write action that the websocket audit flagged as silent:
 * columns, sprints, project creation, task deletion, task-level access
 * requests, workspace + project membership, audit-log entries, and inbox
 * notifications (mention / assignment).
 *
 * Two assertion styles, matching the existing broadcasting tests:
 *   - route-level: Event::fake([...]) + assert the action dispatches the event
 *   - unit-level:  instantiate the event and assert broadcastOn()/broadcastWith()
 */
class EventBroadcastingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $alice;
    private User $bob;
    private Workspace $workspace;
    private Project $project;
    private Sprint $sprint;
    private BoardColumn $column;

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
    }

    private function makeTask(array $attrs = []): Task
    {
        static $counter = 0;
        $counter++;

        return Task::create(array_merge([
            'key'             => "PRJ-{$counter}",
            'title'           => "Task {$counter}",
            'project_id'      => $this->project->id,
            'sprint_id'       => $this->sprint->id,
            'board_column_id' => $this->column->id,
            'created_by'      => $this->owner->id,
            'priority'        => 'med',
        ], $attrs));
    }

    private function makeSubtask(Task $parent, array $attrs = []): Task
    {
        static $counter = 0;
        $counter++;

        return Task::create(array_merge([
            'key'             => "{$parent->key}-S{$counter}",
            'title'           => "Subtask {$counter}",
            'project_id'      => $parent->project_id,
            'parent_task_id'  => $parent->id,
            'board_column_id' => $parent->board_column_id,
            'created_by'      => $this->owner->id,
            'priority'        => 'med',
        ], $attrs));
    }

    /** A workspace member who is NOT on the project — the access-request actor. */
    private function makeOutsider(): User
    {
        $user = User::factory()->create(['name' => 'Outsider']);
        $this->workspace->users()->attach($user->id, ['role' => 'member']);
        $user->update(['current_workspace_id' => $this->workspace->id]);

        return $user;
    }

    // ───────────────────────── Board columns ─────────────────────────

    public function test_adding_column_dispatches_board_column_created(): void
    {
        Event::fake([BoardColumnUpdated::class]);

        $this->actingAs($this->owner)
            ->post("/projects/{$this->project->id}/columns", ['name' => 'Blocked'])
            ->assertRedirect();

        Event::assertDispatched(BoardColumnUpdated::class, fn (BoardColumnUpdated $e) =>
            $e->event === 'created'
            && $e->column->name === 'Blocked'
            && $e->project->id === $this->project->id
        );
    }

    public function test_renaming_column_dispatches_board_column_updated(): void
    {
        Event::fake([BoardColumnUpdated::class]);

        $this->actingAs($this->owner)
            ->patch("/columns/{$this->column->id}", ['name' => 'In review'])
            ->assertRedirect();

        Event::assertDispatched(BoardColumnUpdated::class, fn (BoardColumnUpdated $e) =>
            $e->event === 'updated' && $e->column->name === 'In review'
        );
    }

    public function test_deleting_empty_column_dispatches_board_column_deleted(): void
    {
        $empty = BoardColumn::create([
            'project_id' => $this->project->id,
            'name'       => 'Scratch',
            'color'      => '#94948c',
            'position'   => 9,
        ]);

        Event::fake([BoardColumnUpdated::class]);

        $this->actingAs($this->owner)
            ->delete("/columns/{$empty->id}")
            ->assertRedirect();

        Event::assertDispatched(BoardColumnUpdated::class, fn (BoardColumnUpdated $e) =>
            $e->event === 'deleted' && $e->column->id === $empty->id
        );
    }

    public function test_board_column_event_broadcasts_on_project_channel(): void
    {
        $channels = (new BoardColumnUpdated($this->project, $this->column, 'created'))->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertSame('private-project.' . $this->project->id, $channels[0]->name);
    }

    // ───────────────────────── Sprints ─────────────────────────

    public function test_creating_sprint_dispatches_sprint_updated_created(): void
    {
        Event::fake([SprintUpdated::class]);

        $this->actingAs($this->owner)
            ->post("/projects/{$this->project->id}/sprints", ['name' => 'Sprint 2'])
            ->assertRedirect();

        Event::assertDispatched(SprintUpdated::class, fn (SprintUpdated $e) =>
            $e->event === 'created' && $e->sprint->name === 'Sprint 2'
        );
    }

    private function assertSprintActionDispatches(Sprint $sprint, string $route, string $verb): void
    {
        Event::fake([SprintUpdated::class]);

        $this->actingAs($this->owner)
            ->post("/sprints/{$sprint->id}/{$route}")
            ->assertRedirect();

        Event::assertDispatched(SprintUpdated::class, fn (SprintUpdated $e) =>
            $e->event === $verb && $e->sprint->id === $sprint->id
        );
    }

    public function test_locking_sprint_dispatches_locked_event(): void
    {
        $this->assertSprintActionDispatches($this->sprint, 'lock', 'locked');
    }

    public function test_unlocking_sprint_dispatches_unlocked_event(): void
    {
        $this->sprint->update(['locked' => true]);
        $this->assertSprintActionDispatches($this->sprint, 'unlock', 'unlocked');
    }

    public function test_completing_sprint_dispatches_completed_event(): void
    {
        $this->assertSprintActionDispatches($this->sprint, 'complete', 'completed');
    }

    public function test_reopening_sprint_dispatches_reopened_event(): void
    {
        $done = Sprint::create([
            'project_id' => $this->project->id,
            'name'       => 'Done',
            'status'     => 'completed',
            'locked'     => false,
        ]);
        $this->assertSprintActionDispatches($done, 'reopen', 'reopened');
    }

    public function test_sprint_event_broadcasts_on_project_channel(): void
    {
        $channels = (new SprintUpdated($this->sprint, 'locked'))->broadcastOn();

        $this->assertSame('private-project.' . $this->project->id, $channels[0]->name);
    }

    // ───────────────────────── Project creation ─────────────────────────

    public function test_creating_project_dispatches_project_created_on_workspace_channel(): void
    {
        Event::fake([ProjectCreated::class]);

        $this->actingAs($this->owner)
            ->post('/projects', ['name' => 'Mobile App v3', 'key' => 'MOB', 'color' => '#dc2626'])
            ->assertRedirect();

        Event::assertDispatched(ProjectCreated::class, function (ProjectCreated $e) {
            $channels = $e->broadcastOn();

            return $e->project->name === 'Mobile App v3'
                && $channels[0]->name === 'private-workspace.' . $this->workspace->id;
        });
    }

    // ───────────────────────── Task deletion ─────────────────────────

    public function test_deleting_task_dispatches_task_deleted(): void
    {
        $task = $this->makeTask();

        Event::fake([TaskDeleted::class]);

        $this->actingAs($this->owner)
            ->delete("/tasks/{$task->id}")
            ->assertRedirect();

        Event::assertDispatched(TaskDeleted::class, fn (TaskDeleted $e) =>
            $e->taskId === $task->id && $e->projectId === $this->project->id
        );
    }

    // ───────────────────────── Task-level access requests ─────────────────────────

    public function test_requesting_access_dispatches_requested_event(): void
    {
        $task     = $this->makeTask();
        $outsider = $this->makeOutsider();

        Event::fake([TaskAccessRequestUpdated::class]);

        $this->actingAs($outsider)
            ->postJson("/tasks/{$task->id}/access-requests", ['message' => 'Need context'])
            ->assertCreated();

        Event::assertDispatched(TaskAccessRequestUpdated::class, fn (TaskAccessRequestUpdated $e) =>
            $e->event === 'requested'
            && $e->taskId === $task->id
            && $e->userId === $outsider->id
        );
    }

    public function test_approving_access_dispatches_approved_event_targeting_grantee(): void
    {
        $task     = $this->makeTask();
        $outsider = $this->makeOutsider();
        $req = TaskAccessRequest::create([
            'task_id' => $task->id, 'user_id' => $outsider->id, 'status' => 'pending',
        ]);

        Event::fake([TaskAccessRequestUpdated::class]);

        $this->actingAs($this->owner)
            ->postJson("/tasks/{$task->id}/access-requests/{$req->id}/approve")
            ->assertOk();

        Event::assertDispatched(TaskAccessRequestUpdated::class, fn (TaskAccessRequestUpdated $e) =>
            $e->event === 'approved' && $e->userId === $outsider->id
        );
    }

    public function test_declining_access_dispatches_declined_event(): void
    {
        $task     = $this->makeTask();
        $outsider = $this->makeOutsider();
        $req = TaskAccessRequest::create([
            'task_id' => $task->id, 'user_id' => $outsider->id, 'status' => 'pending',
        ]);

        Event::fake([TaskAccessRequestUpdated::class]);

        $this->actingAs($this->owner)
            ->postJson("/tasks/{$task->id}/access-requests/{$req->id}/decline")
            ->assertOk();

        Event::assertDispatched(TaskAccessRequestUpdated::class, fn (TaskAccessRequestUpdated $e) =>
            $e->event === 'declined' && $e->userId === $outsider->id
        );
    }

    public function test_access_request_event_broadcasts_on_project_and_user_channels(): void
    {
        $channels = (new TaskAccessRequestUpdated(7, $this->project->id, $this->alice->id, 'approved'))
            ->broadcastOn();

        $names = array_map(fn ($c) => $c->name, $channels);

        $this->assertContains('private-project.' . $this->project->id, $names);
        $this->assertContains('private-App.Models.User.' . $this->alice->id, $names);
    }

    // ───────────────────────── Workspace membership ─────────────────────────

    public function test_updating_member_role_dispatches_workspace_members_changed(): void
    {
        Event::fake([WorkspaceMembersChanged::class]);

        $this->actingAs($this->owner)
            ->patch("/settings/members/{$this->bob->id}/role", ['role' => 'admin'])
            ->assertRedirect();

        Event::assertDispatched(WorkspaceMembersChanged::class, fn (WorkspaceMembersChanged $e) =>
            $e->event === 'role_updated'
            && $e->memberId === $this->bob->id
            && $e->workspaceId === $this->workspace->id
        );
    }

    public function test_removing_member_dispatches_workspace_members_changed(): void
    {
        Event::fake([WorkspaceMembersChanged::class]);

        $this->actingAs($this->owner)
            ->delete("/settings/members/{$this->bob->id}")
            ->assertRedirect();

        Event::assertDispatched(WorkspaceMembersChanged::class, fn (WorkspaceMembersChanged $e) =>
            $e->event === 'member_removed' && $e->memberId === $this->bob->id
        );
    }

    public function test_workspace_members_event_broadcasts_on_workspace_channel(): void
    {
        $channels = (new WorkspaceMembersChanged($this->workspace->id, 'role_updated', 5))->broadcastOn();

        $this->assertSame('private-workspace.' . $this->workspace->id, $channels[0]->name);
    }

    // ───────────────────────── Project membership ─────────────────────────

    public function test_inviting_project_member_dispatches_project_members_changed(): void
    {
        $carol = User::factory()->create(['name' => 'Carol']);
        $this->workspace->users()->attach($carol->id, ['role' => 'member']);

        Event::fake([ProjectMembersChanged::class]);

        $this->actingAs($this->owner)
            ->post("/projects/{$this->project->id}/members/invite", [
                'email' => $carol->email,
                'role'  => 'member',
            ])
            ->assertRedirect();

        Event::assertDispatched(ProjectMembersChanged::class, fn (ProjectMembersChanged $e) =>
            $e->event === 'member_invited'
            && $e->memberId === $carol->id
            && $e->projectId === $this->project->id
        );
    }

    public function test_removing_project_member_dispatches_project_members_changed(): void
    {
        Event::fake([ProjectMembersChanged::class]);

        $this->actingAs($this->owner)
            ->delete("/projects/{$this->project->id}/members/{$this->bob->id}")
            ->assertRedirect();

        Event::assertDispatched(ProjectMembersChanged::class, fn (ProjectMembersChanged $e) =>
            $e->event === 'member_removed' && $e->memberId === $this->bob->id
        );
    }

    // ───────────────────────── Audit log (model hook) ─────────────────────────

    public function test_creating_audit_log_dispatches_audit_log_recorded(): void
    {
        Event::fake([AuditLogRecorded::class]);

        AuditLog::create([
            'user_id'    => $this->owner->id,
            'project_id' => $this->project->id,
            'action'     => 'column.created',
            'meta'       => ['column' => 'Todo'],
        ]);

        Event::assertDispatched(AuditLogRecorded::class, fn (AuditLogRecorded $e) =>
            $e->log->action === 'column.created'
            && $e->log->project_id === $this->project->id
        );
    }

    public function test_audit_log_recorded_broadcasts_on_project_channel(): void
    {
        $log = AuditLog::create([
            'user_id'    => $this->owner->id,
            'project_id' => $this->project->id,
            'action'     => 'sprint.locked',
        ]);

        $channels = (new AuditLogRecorded($log))->broadcastOn();

        $this->assertSame('private-project.' . $this->project->id, $channels[0]->name);
    }

    public function test_project_less_audit_log_has_no_broadcast_channel(): void
    {
        // Build a project-less entry without persisting (the created hook only
        // broadcasts when project_id is set anyway).
        $log = new AuditLog(['user_id' => $this->owner->id, 'action' => 'workspace.created']);

        $this->assertSame([], (new AuditLogRecorded($log))->broadcastOn());
    }

    // ───────────────────────── Inbox notifications ─────────────────────────

    public function test_assigning_another_user_dispatches_inbox_notification(): void
    {
        $task = $this->makeTask();

        Event::fake([InboxNotificationSent::class]);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$task->id}", ['assignee_ids' => [$this->alice->id]])
            ->assertRedirect();

        Event::assertDispatched(InboxNotificationSent::class, fn (InboxNotificationSent $e) =>
            $e->userId === $this->alice->id && $e->kind === 'assigned'
        );
    }

    public function test_assigning_yourself_does_not_dispatch_inbox_notification(): void
    {
        $task = $this->makeTask();

        Event::fake([InboxNotificationSent::class]);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$task->id}", ['assignee_ids' => [$this->owner->id]]);

        Event::assertNotDispatched(InboxNotificationSent::class);
    }

    public function test_mentioning_a_user_in_a_comment_dispatches_inbox_notification(): void
    {
        $task = $this->makeTask();

        Event::fake([InboxNotificationSent::class]);

        $this->actingAs($this->owner)
            ->post("/tasks/{$task->id}/comments", [
                'body' => "Heads up @[Alice](user:{$this->alice->id})",
            ])
            ->assertRedirect();

        Event::assertDispatched(InboxNotificationSent::class, fn (InboxNotificationSent $e) =>
            $e->userId === $this->alice->id && $e->kind === 'mention'
        );
    }

    public function test_inbox_notification_broadcasts_on_user_channel(): void
    {
        $channels = (new InboxNotificationSent($this->alice->id, 'mention'))->broadcastOn();

        $this->assertSame('private-App.Models.User.' . $this->alice->id, $channels[0]->name);
    }

    // ──────────────── Task & subtask completion (issues 1 & 2) ────────────────

    public function test_completing_task_dispatches_task_updated(): void
    {
        $task = $this->makeTask();

        Event::fake([TaskUpdated::class]);

        $this->actingAs($this->owner)
            ->post("/tasks/{$task->id}/complete")
            ->assertRedirect();

        Event::assertDispatched(TaskUpdated::class, fn (TaskUpdated $e) =>
            $e->task->id === $task->id && $e->task->completed === true
        );
    }

    public function test_uncompleting_task_dispatches_task_updated(): void
    {
        $task = $this->makeTask([
            'completed'    => true,
            'completed_at' => now(),
            'completed_by' => $this->owner->id,
        ]);

        Event::fake([TaskUpdated::class]);

        $this->actingAs($this->owner)
            ->post("/tasks/{$task->id}/uncomplete")
            ->assertRedirect();

        Event::assertDispatched(TaskUpdated::class, fn (TaskUpdated $e) =>
            $e->task->id === $task->id && $e->task->completed === false
        );
    }

    public function test_completing_subtask_broadcasts_the_parent_not_the_subtask(): void
    {
        $parent  = $this->makeTask();
        $subtask = $this->makeSubtask($parent);

        Event::fake([TaskUpdated::class]);

        $this->actingAs($this->owner)
            ->post("/tasks/{$subtask->id}/complete")
            ->assertRedirect();

        // A subtask isn't its own board card; the parent is broadcast so the
        // parent card progress + open panel checkbox update for other viewers.
        Event::assertDispatched(TaskUpdated::class, fn (TaskUpdated $e) =>
            $e->task->id === $parent->id
        );
        Event::assertNotDispatched(TaskUpdated::class, fn (TaskUpdated $e) =>
            $e->task->id === $subtask->id
        );
    }

    public function test_uncompleting_subtask_broadcasts_the_parent(): void
    {
        $parent  = $this->makeTask();
        $subtask = $this->makeSubtask($parent, [
            'completed'    => true,
            'completed_at' => now(),
            'completed_by' => $this->owner->id,
        ]);

        Event::fake([TaskUpdated::class]);

        $this->actingAs($this->owner)
            ->post("/tasks/{$subtask->id}/uncomplete")
            ->assertRedirect();

        Event::assertDispatched(TaskUpdated::class, fn (TaskUpdated $e) =>
            $e->task->id === $parent->id
        );
    }

    // ──────────────── Subtask comments (issue 3) ────────────────

    public function test_commenting_on_subtask_dispatches_comment_added(): void
    {
        $parent  = $this->makeTask();
        $subtask = $this->makeSubtask($parent);

        Event::fake([CommentAdded::class]);

        $this->actingAs($this->owner)
            ->post("/tasks/{$subtask->id}/comments", ['body' => 'Note on the subtask'])
            ->assertRedirect();

        Event::assertDispatched(CommentAdded::class, fn (CommentAdded $e) =>
            $e->task->id === $subtask->id && $e->comment->body === 'Note on the subtask'
        );

        // It also persists (the old frontend kept it in-memory only).
        $this->assertDatabaseHas('task_comments', [
            'task_id' => $subtask->id,
            'body'    => 'Note on the subtask',
        ]);
    }

    // ──────────────── Immediate (non-queued) broadcasting (issue 4) ────────────────

    public function test_realtime_events_broadcast_immediately_not_queued(): void
    {
        // QUEUE_CONNECTION=database means an event implementing only
        // ShouldBroadcast is enqueued and never reaches websockets without a
        // running worker. Every live-listened event must broadcast NOW.
        foreach ([
            BoardColumnUpdated::class,
            TaskDeleted::class,
            SprintUpdated::class,
            TaskUpdated::class,
            CommentAdded::class,
            ProjectCreated::class,
            MemberProjectAccessUpdated::class,
        ] as $event) {
            $instance = (new \ReflectionClass($event))->newInstanceWithoutConstructor();
            $this->assertInstanceOf(
                ShouldBroadcastNow::class,
                $instance,
                "{$event} must implement ShouldBroadcastNow to broadcast without a queue worker."
            );
        }
    }

    // ──────────────── Duplicate project name/key (issue 5) ────────────────

    public function test_creating_project_with_duplicate_name_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->post('/projects', ['name' => 'Proj', 'key' => 'NEWK', 'color' => '#dc2626'])
            ->assertSessionHasErrors('name');

        $this->assertSame(
            1,
            Project::where('workspace_id', $this->workspace->id)->where('name', 'Proj')->count()
        );
    }

    public function test_creating_project_with_duplicate_key_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->post('/projects', ['name' => 'Brand New Name', 'key' => 'PRJ', 'color' => '#dc2626'])
            ->assertSessionHasErrors('key');
    }

    public function test_duplicate_project_name_is_allowed_in_a_different_workspace(): void
    {
        $other = Workspace::create([
            'name'     => 'Other WS',
            'owner_id' => $this->owner->id,
            'color'    => '#16a34a',
        ]);
        $other->users()->attach($this->owner->id, ['role' => 'owner']);
        $this->owner->update(['current_workspace_id' => $other->id]);

        // "Proj" already exists in the first workspace — allowed here.
        $this->actingAs($this->owner)
            ->post('/projects', ['name' => 'Proj', 'key' => 'PRJ', 'color' => '#dc2626'])
            ->assertRedirect();

        $this->assertTrue(
            Project::where('workspace_id', $other->id)->where('name', 'Proj')->exists()
        );
    }

    // ──────────────── New-project access for non-viewers (issue 6) ────────────────

    public function test_creating_project_grants_access_to_non_viewer_members_only(): void
    {
        $viewer = User::factory()->create(['name' => 'Viewer']);
        $this->workspace->users()->attach($viewer->id, ['role' => 'viewer']);

        $this->actingAs($this->owner)
            ->post('/projects', ['name' => 'Fresh Project', 'key' => 'FRESH', 'color' => '#dc2626'])
            ->assertRedirect();

        $project = Project::where('workspace_id', $this->workspace->id)->where('key', 'FRESH')->firstOrFail();

        // The creator owns the project (access is via owner_id, not the pivot).
        $this->assertSame($this->owner->id, $project->owner_id);
        // Non-viewer members get pivot access...
        $this->assertTrue($project->members()->where('users.id', $this->alice->id)->exists());
        $this->assertTrue($project->members()->where('users.id', $this->bob->id)->exists());
        // ...but viewers do NOT.
        $this->assertFalse($project->members()->where('users.id', $viewer->id)->exists());
    }

    public function test_creating_project_notifies_non_viewer_members_on_their_user_channel(): void
    {
        $viewer = User::factory()->create(['name' => 'Viewer']);
        $this->workspace->users()->attach($viewer->id, ['role' => 'viewer']);

        Event::fake([MemberProjectAccessUpdated::class]);

        $this->actingAs($this->owner)
            ->post('/projects', ['name' => 'Notify Project', 'key' => 'NOTIF', 'color' => '#dc2626'])
            ->assertRedirect();

        Event::assertDispatched(MemberProjectAccessUpdated::class, fn (MemberProjectAccessUpdated $e) =>
            $e->memberId === $this->alice->id
        );
        Event::assertDispatched(MemberProjectAccessUpdated::class, fn (MemberProjectAccessUpdated $e) =>
            $e->memberId === $this->bob->id
        );
        // The creator already navigates to the project — no self-notification.
        Event::assertNotDispatched(MemberProjectAccessUpdated::class, fn (MemberProjectAccessUpdated $e) =>
            $e->memberId === $this->owner->id
        );
        // Viewers aren't granted access, so they aren't notified.
        Event::assertNotDispatched(MemberProjectAccessUpdated::class, fn (MemberProjectAccessUpdated $e) =>
            $e->memberId === $viewer->id
        );
    }
}
