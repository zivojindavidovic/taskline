<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    private function createProjectForUser(User $user, array $attrs = []): Project
    {
        $project = Project::create(array_merge([
            'name'     => 'Test Project',
            'key'      => 'TST',
            'color'    => '#4f46e5',
            'owner_id' => $user->id,
        ], $attrs));
        $project->members()->attach($user->id, ['role' => 'owner']);
        return $project;
    }

    public function test_authenticated_user_can_list_their_projects(): void
    {
        $user = User::factory()->create();
        $this->createProjectForUser($user);

        $this->actingAs($user)
            ->getJson('/api/projects')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_user_cannot_see_projects_they_are_not_a_member_of(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->createProjectForUser($owner);

        $this->actingAs($other)
            ->getJson('/api/projects')
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_user_can_create_a_project(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/projects', [
            'name'  => 'Mobile App',
            'key'   => 'MOB',
            'color' => '#4f46e5',
        ]);

        $response->assertCreated()
            ->assertJsonFragment(['name' => 'Mobile App', 'key' => 'MOB']);

        $this->assertDatabaseHas('projects', ['name' => 'Mobile App', 'owner_id' => $user->id]);
        // Owner is auto-added as member
        $project = Project::where('name', 'Mobile App')->first();
        $this->assertTrue($project->members()->where('user_id', $user->id)->exists());
        // Default columns are seeded
        $this->assertCount(4, $project->boardColumns);
    }

    public function test_project_creation_requires_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/projects', [
            'key'   => 'MOB',
            'color' => '#4f46e5',
        ])->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_project_key_is_uppercased_automatically(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/projects', [
            'name'  => 'Test',
            'key'   => 'tst',
            'color' => '#4f46e5',
        ]);

        $response->assertCreated()->assertJsonFragment(['key' => 'TST']);
    }

    public function test_project_creation_validates_color_format(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/projects', [
            'name'  => 'Test',
            'key'   => 'TST',
            'color' => 'red',
        ])->assertUnprocessable()->assertJsonValidationErrors('color');
    }

    public function test_owner_can_update_project(): void
    {
        $user    = User::factory()->create();
        $project = $this->createProjectForUser($user);

        $this->actingAs($user)
            ->patchJson("/api/projects/{$project->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Renamed']);
    }

    public function test_non_member_cannot_update_project(): void
    {
        $owner   = User::factory()->create();
        $other   = User::factory()->create();
        $project = $this->createProjectForUser($owner);

        $this->actingAs($other)
            ->patchJson("/api/projects/{$project->id}", ['name' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_owner_can_delete_project(): void
    {
        $user    = User::factory()->create();
        $project = $this->createProjectForUser($user);

        $this->actingAs($user)
            ->deleteJson("/api/projects/{$project->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_non_owner_member_cannot_delete_project(): void
    {
        $owner  = User::factory()->create();
        $member = User::factory()->create();
        $project = $this->createProjectForUser($owner);
        $project->members()->attach($member->id, ['role' => 'member']);

        $this->actingAs($member)
            ->deleteJson("/api/projects/{$project->id}")
            ->assertForbidden();
    }
}
