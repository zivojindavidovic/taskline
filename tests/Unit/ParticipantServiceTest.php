<?php

namespace Tests\Unit;

use App\Models\AuditLog;
use App\Models\BoardColumn;
use App\Models\CommentReply;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\ParticipantRepository;
use App\Repositories\TaskRepository;
use App\Services\ParticipantService;
use App\Services\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipantServiceTest extends TestCase
{
    use RefreshDatabase;

    private ParticipantService $service;
    private TaskService $tasks;
    private User $owner;
    private Project $project;
    private Sprint $sprint;
    private BoardColumn $column;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ParticipantService(new ParticipantRepository());
        $this->tasks   = new TaskService(new TaskRepository());

        $this->owner = User::factory()->create();
        $workspace = Workspace::create([
            'name'     => 'Acme',
            'owner_id' => $this->owner->id,
            'color'    => '#4f46e5',
        ]);
        $this->project = Project::create([
            'name'         => 'Test',
            'key'          => 'TST',
            'color'        => '#4f46e5',
            'owner_id'     => $this->owner->id,
            'workspace_id' => $workspace->id,
        ]);
        $this->sprint = Sprint::create(['project_id' => $this->project->id, 'name' => 'S1']);
        $this->column = BoardColumn::create([
            'project_id' => $this->project->id,
            'name'       => 'Todo',
            'color'      => '#94948c',
            'position'   => 0,
        ]);
    }

    private function makeTask(?int $createdBy = null): Task
    {
        return Task::create([
            'key'             => 'TST-' . uniqid(),
            'title'           => 'Test',
            'project_id'      => $this->project->id,
            'sprint_id'       => $this->sprint->id,
            'board_column_id' => $this->column->id,
            'created_by'      => $createdBy ?? $this->owner->id,
            'priority'        => 'med',
        ]);
    }

    public function test_creator_is_a_participant_with_reporter_role(): void
    {
        $task = $this->makeTask();

        $participants = $this->service->forTask($task);

        $this->assertCount(1, $participants);
        $entry = $participants->firstWhere('user.id', $this->owner->id);
        $this->assertNotNull($entry);
        $this->assertContains('reporter', $entry['roles']);
    }

    public function test_current_assignees_are_included(): void
    {
        $task = $this->makeTask();
        $alice = User::factory()->create();
        $bob   = User::factory()->create();
        $this->tasks->setAssignees($task, [$alice->id, $bob->id], $this->owner->id);

        $participants = $this->service->forTask($task);

        $this->assertContains('assignee', $participants->firstWhere('user.id', $alice->id)['roles']);
        $this->assertContains('assignee', $participants->firstWhere('user.id', $bob->id)['roles']);
    }

    public function test_past_assignees_are_included_via_audit_trail(): void
    {
        $task = $this->makeTask();
        $alice = User::factory()->create();
        $bob   = User::factory()->create();

        $this->tasks->setAssignees($task, [$alice->id], $this->owner->id);
        $this->tasks->setAssignees($task, [$bob->id], $this->owner->id);

        $participants = $this->service->forTask($task);

        $entry = $participants->firstWhere('user.id', $alice->id);
        $this->assertNotNull($entry, 'Past assignee should still be a participant');
        $this->assertContains('past_assignee', $entry['roles']);
    }

    public function test_completer_is_included(): void
    {
        $task = $this->makeTask();
        $finisher = User::factory()->create();
        $task->update(['completed' => true, 'completed_by' => $finisher->id, 'completed_at' => now()]);

        $participants = $this->service->forTask($task);

        $this->assertContains('completer', $participants->firstWhere('user.id', $finisher->id)['roles']);
    }

    public function test_commenter_is_included(): void
    {
        $task = $this->makeTask();
        $commenter = User::factory()->create();
        TaskComment::create(['task_id' => $task->id, 'user_id' => $commenter->id, 'body' => 'hi']);

        $participants = $this->service->forTask($task);

        $this->assertContains('commenter', $participants->firstWhere('user.id', $commenter->id)['roles']);
    }

    public function test_reply_author_is_included_as_commenter(): void
    {
        $task = $this->makeTask();
        $commenter = User::factory()->create();
        $replier = User::factory()->create();

        $comment = TaskComment::create(['task_id' => $task->id, 'user_id' => $commenter->id, 'body' => 'hi']);
        CommentReply::create(['task_comment_id' => $comment->id, 'user_id' => $replier->id, 'body' => 'reply']);

        $participants = $this->service->forTask($task);

        $this->assertContains('commenter', $participants->firstWhere('user.id', $replier->id)['roles']);
    }

    public function test_editor_via_audit_log_is_included(): void
    {
        $task = $this->makeTask();
        $editor = User::factory()->create();

        AuditLog::create([
            'user_id'    => $editor->id,
            'project_id' => $task->project_id,
            'task_id'    => $task->id,
            'action'     => 'task.renamed',
            'meta'       => ['title' => 'New'],
        ]);

        $participants = $this->service->forTask($task);

        $this->assertNotNull($participants->firstWhere('user.id', $editor->id));
    }

    public function test_a_user_appearing_in_multiple_capacities_collapses_to_one_entry(): void
    {
        $task = $this->makeTask();
        $bob = User::factory()->create();

        $this->tasks->setAssignees($task, [$bob->id], $this->owner->id);
        $task->update(['completed' => true, 'completed_by' => $bob->id]);
        TaskComment::create(['task_id' => $task->id, 'user_id' => $bob->id, 'body' => 'done']);

        $participants = $this->service->forTask($task);

        $bobEntries = $participants->filter(fn ($p) => $p['user']->id === $bob->id);
        $this->assertCount(1, $bobEntries);

        $roles = $bobEntries->first()['roles'];
        $this->assertContains('assignee', $roles);
        $this->assertContains('completer', $roles);
        $this->assertContains('commenter', $roles);
    }

    public function test_reporter_appears_before_assignee_in_role_order(): void
    {
        $task = $this->makeTask();
        $this->tasks->setAssignees($task, [$this->owner->id], $this->owner->id);

        $entry = $this->service->forTask($task)->firstWhere('user.id', $this->owner->id);

        $this->assertSame(['reporter', 'assignee'], array_values(array_intersect($entry['roles'], ['reporter', 'assignee'])));
    }
}
