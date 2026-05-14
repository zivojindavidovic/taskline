<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SprintTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Project $project;

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
    }

    public function test_member_can_list_sprints(): void
    {
        Sprint::create(['project_id' => $this->project->id, 'name' => 'Sprint 1', 'status' => 'active']);
        Sprint::create(['project_id' => $this->project->id, 'name' => 'Sprint 2', 'status' => 'planned']);

        $this->actingAs($this->owner)
            ->getJson("/api/projects/{$this->project->id}/sprints")
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_member_can_create_a_sprint(): void
    {
        $response = $this->actingAs($this->owner)->postJson("/api/projects/{$this->project->id}/sprints", [
            'name'       => 'Sprint 1',
            'start_date' => '2026-05-05',
            'end_date'   => '2026-05-19',
            'status'     => 'active',
        ]);

        $response->assertCreated()->assertJsonFragment(['name' => 'Sprint 1']);
        $this->assertDatabaseHas('sprints', ['project_id' => $this->project->id, 'name' => 'Sprint 1']);
    }

    public function test_sprint_creation_requires_name(): void
    {
        $this->actingAs($this->owner)
            ->postJson("/api/projects/{$this->project->id}/sprints", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_sprint_end_date_must_be_after_start_date(): void
    {
        $this->actingAs($this->owner)
            ->postJson("/api/projects/{$this->project->id}/sprints", [
                'name'       => 'Sprint 1',
                'start_date' => '2026-05-19',
                'end_date'   => '2026-05-05',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('end_date');
    }

    public function test_non_member_cannot_create_sprint(): void
    {
        $other = User::factory()->create();

        $this->actingAs($other)
            ->postJson("/api/projects/{$this->project->id}/sprints", ['name' => 'Sprint 1'])
            ->assertForbidden();
    }

    public function test_member_can_lock_an_unlocked_sprint(): void
    {
        $sprint = Sprint::create([
            'project_id' => $this->project->id,
            'name'       => 'Sprint 1',
            'locked'     => false,
        ]);

        $this->actingAs($this->owner)
            ->postJson("/api/projects/{$this->project->id}/sprints/{$sprint->id}/lock")
            ->assertOk()
            ->assertJsonFragment(['locked' => true]);

        $this->assertDatabaseHas('sprints', ['id' => $sprint->id, 'locked' => true]);
    }

    public function test_cannot_lock_an_already_locked_sprint(): void
    {
        $sprint = Sprint::create([
            'project_id' => $this->project->id,
            'name'       => 'Sprint 1',
            'locked'     => true,
        ]);

        $this->actingAs($this->owner)
            ->postJson("/api/projects/{$this->project->id}/sprints/{$sprint->id}/lock")
            ->assertUnprocessable();
    }

    public function test_member_can_unlock_a_locked_sprint(): void
    {
        $sprint = Sprint::create([
            'project_id' => $this->project->id,
            'name'       => 'Sprint 1',
            'locked'     => true,
        ]);

        $this->actingAs($this->owner)
            ->postJson("/api/projects/{$this->project->id}/sprints/{$sprint->id}/unlock")
            ->assertOk()
            ->assertJsonFragment(['locked' => false]);
    }

    public function test_locking_sprint_creates_audit_log(): void
    {
        $sprint = Sprint::create([
            'project_id' => $this->project->id,
            'name'       => 'Sprint 1',
            'locked'     => false,
        ]);

        $this->actingAs($this->owner)
            ->postJson("/api/projects/{$this->project->id}/sprints/{$sprint->id}/lock");

        $this->assertDatabaseHas('audit_logs', [
            'user_id'    => $this->owner->id,
            'project_id' => $this->project->id,
            'action'     => 'sprint.locked',
        ]);
    }

    public function test_member_can_delete_a_sprint(): void
    {
        $sprint = Sprint::create(['project_id' => $this->project->id, 'name' => 'Sprint 1']);

        $this->actingAs($this->owner)
            ->deleteJson("/api/projects/{$this->project->id}/sprints/{$sprint->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('sprints', ['id' => $sprint->id]);
    }
}
