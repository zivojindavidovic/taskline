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

class InboxTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $other;
    private Project $project;
    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->other = User::factory()->create();

        $this->project = Project::create([
            'name'     => 'Test Project',
            'key'      => 'TST',
            'color'    => '#4f46e5',
            'owner_id' => $this->owner->id,
        ]);
        $this->project->members()->attach($this->owner->id, ['role' => 'owner']);
        $this->project->members()->attach($this->other->id, ['role' => 'member']);

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

    public function test_unauthenticated_user_is_redirected(): void
    {
        $this->get(route('inbox'))->assertRedirect(route('login'));
    }

    public function test_inbox_page_renders_for_authenticated_user(): void
    {
        $this->actingAs($this->owner)
            ->get(route('inbox'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Inbox'));
    }

    public function test_comment_on_assigned_task_appears_in_inbox(): void
    {
        $this->task->update(['assignee_id' => $this->owner->id]);

        TaskComment::create([
            'task_id' => $this->task->id,
            'user_id' => $this->other->id,
            'body'    => 'Nice work!',
        ]);

        $this->actingAs($this->owner)
            ->get(route('inbox'))
            ->assertInertia(fn ($page) => $page
                ->component('Inbox')
                ->has('notifications', 1)
                ->where('notifications.0.verb', 'commented on')
                ->where('notifications.0.target', 'TST-1')
                ->where('notifications.0.excerpt', 'Nice work!')
            );
    }

    public function test_own_comment_on_assigned_task_does_not_appear(): void
    {
        $this->task->update(['assignee_id' => $this->owner->id]);

        TaskComment::create([
            'task_id' => $this->task->id,
            'user_id' => $this->owner->id,
            'body'    => 'My own comment',
        ]);

        $this->actingAs($this->owner)
            ->get(route('inbox'))
            ->assertInertia(fn ($page) => $page
                ->component('Inbox')
                ->has('notifications', 0)
            );
    }

    public function test_task_assigned_by_another_user_appears_in_inbox(): void
    {
        $sprint = Sprint::first();
        $column = BoardColumn::first();

        Task::create([
            'key'             => 'TST-2',
            'title'           => 'Assigned to me',
            'project_id'      => $this->project->id,
            'sprint_id'       => $sprint->id,
            'board_column_id' => $column->id,
            'created_by'      => $this->other->id,
            'assignee_id'     => $this->owner->id,
            'priority'        => 'med',
        ]);

        $this->actingAs($this->owner)
            ->get(route('inbox'))
            ->assertInertia(fn ($page) => $page
                ->component('Inbox')
                ->has('notifications', 1)
                ->where('notifications.0.verb', 'assigned you to')
                ->where('notifications.0.target', 'TST-2')
            );
    }

    public function test_self_assigned_task_does_not_appear(): void
    {
        $sprint = Sprint::first();
        $column = BoardColumn::first();

        Task::create([
            'key'             => 'TST-3',
            'title'           => 'Self assigned',
            'project_id'      => $this->project->id,
            'sprint_id'       => $sprint->id,
            'board_column_id' => $column->id,
            'created_by'      => $this->owner->id,
            'assignee_id'     => $this->owner->id,
            'priority'        => 'med',
        ]);

        $this->actingAs($this->owner)
            ->get(route('inbox'))
            ->assertInertia(fn ($page) => $page
                ->component('Inbox')
                ->has('notifications', 0)
            );
    }

    public function test_notifications_include_actor_and_time(): void
    {
        $this->task->update(['assignee_id' => $this->owner->id]);

        TaskComment::create([
            'task_id' => $this->task->id,
            'user_id' => $this->other->id,
            'body'    => 'Hey there',
        ]);

        $this->actingAs($this->owner)
            ->get(route('inbox'))
            ->assertInertia(fn ($page) => $page
                ->component('Inbox')
                ->where('notifications.0.actor', $this->other->name)
                ->has('notifications.0.time')
            );
    }

    public function test_inbox_is_empty_when_no_relevant_activity(): void
    {
        $this->actingAs($this->owner)
            ->get(route('inbox'))
            ->assertInertia(fn ($page) => $page
                ->component('Inbox')
                ->has('notifications', 0)
            );
    }
}
