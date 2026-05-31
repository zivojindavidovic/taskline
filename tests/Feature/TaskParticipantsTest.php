<?php

namespace Tests\Feature;

use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskParticipantsTest extends TestCase
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
            'name'         => 'Test',
            'key'          => 'TST',
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

    private function makeTask(array $attrs = []): Task
    {
        return Task::create(array_merge([
            'key'             => 'TST-' . uniqid(),
            'title'           => 'Test',
            'project_id'      => $this->project->id,
            'sprint_id'       => $this->sprint->id,
            'board_column_id' => $this->column->id,
            'created_by'      => $this->owner->id,
            'priority'        => 'med',
        ], $attrs));
    }

    public function test_endpoint_returns_creator_assignees_and_commenters(): void
    {
        $alice = User::factory()->create();
        $bob   = User::factory()->create();
        $this->attachMember($alice);
        $this->attachMember($bob);

        $task = $this->makeTask();
        $task->assignees()->sync([$alice->id]);
        $task->update(['assignee_id' => $alice->id]);
        TaskComment::create(['task_id' => $task->id, 'user_id' => $bob->id, 'body' => 'hello']);

        $response = $this->actingAs($this->owner)
            ->getJson("/tasks/{$task->uuid}/participants");

        $response->assertOk();
        $ids = collect($response->json())->pluck('id')->all();
        $this->assertContains($this->owner->id, $ids);
        $this->assertContains($alice->id, $ids);
        $this->assertContains($bob->id, $ids);
    }

    public function test_endpoint_includes_roles_for_each_user(): void
    {
        $alice = User::factory()->create();
        $this->attachMember($alice);

        $task = $this->makeTask();
        $task->assignees()->sync([$alice->id]);
        $task->update(['assignee_id' => $alice->id]);

        $response = $this->actingAs($this->owner)
            ->getJson("/tasks/{$task->uuid}/participants");

        $response->assertOk();
        $alicePayload = collect($response->json())->firstWhere('id', $alice->id);
        $this->assertNotNull($alicePayload);
        $this->assertContains('assignee', $alicePayload['roles']);
    }

    public function test_endpoint_forbidden_for_non_member(): void
    {
        $outsider = User::factory()->create();
        $task = $this->makeTask();

        $this->actingAs($outsider)
            ->getJson("/tasks/{$task->uuid}/participants")
            ->assertForbidden();
    }

    public function test_endpoint_requires_authentication(): void
    {
        $task = $this->makeTask();

        $this->get("/tasks/{$task->uuid}/participants")->assertRedirect('/login');
    }

    public function test_creating_task_with_assignee_ids_attaches_pivot(): void
    {
        $alice = User::factory()->create();
        $bob   = User::factory()->create();
        $this->attachMember($alice);
        $this->attachMember($bob);

        $this->actingAs($this->owner)
            ->post('/tasks', [
                'project_id'      => $this->project->id,
                'sprint_id'       => $this->sprint->id,
                'board_column_id' => $this->column->id,
                'title'           => 'Multi-assign me',
                'priority'        => 'med',
                'assignee_ids'    => [$alice->id, $bob->id],
            ])
            ->assertRedirect();

        $task = Task::where('title', 'Multi-assign me')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            [$alice->id, $bob->id],
            $task->assignees()->pluck('users.id')->all()
        );
        $this->assertSame($alice->id, $task->assignee_id, 'Primary assignee mirrors first in array');
    }

    public function test_updating_task_with_assignee_ids_replaces_assignees(): void
    {
        $alice = User::factory()->create();
        $bob   = User::factory()->create();
        $carol = User::factory()->create();
        $this->attachMember($alice);
        $this->attachMember($bob);
        $this->attachMember($carol);

        $task = $this->makeTask();
        $task->assignees()->sync([$alice->id, $bob->id]);
        $task->update(['assignee_id' => $alice->id]);

        $this->actingAs($this->owner)
            ->patch("/tasks/{$task->uuid}", [
                'assignee_ids' => [$carol->id],
            ])
            ->assertRedirect();

        $this->assertEquals([$carol->id], $task->fresh()->assignees()->pluck('users.id')->all());
        $this->assertSame($carol->id, $task->fresh()->assignee_id);
    }

    public function test_assignee_ids_validation_rejects_non_workspace_user(): void
    {
        $outsider = User::factory()->create();

        $this->actingAs($this->owner)
            ->postJson('/tasks', [
                'project_id'      => $this->project->id,
                'sprint_id'       => $this->sprint->id,
                'board_column_id' => $this->column->id,
                'title'           => 'Bad assign',
                'priority'        => 'med',
                'assignee_ids'    => [$outsider->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assignee_ids.0');
    }
}
