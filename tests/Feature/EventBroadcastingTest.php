<?php

namespace Tests\Feature;

use App\Events\AuditLogRecorded;
use App\Events\BoardColumnUpdated;
use App\Events\InboxNotificationSent;
use App\Events\ProjectCreated;
use App\Events\ProjectMembersChanged;
use App\Events\SprintUpdated;
use App\Events\TaskAccessRequestUpdated;
use App\Events\TaskDeleted;
use App\Events\WorkspaceMembersChanged;
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
}
