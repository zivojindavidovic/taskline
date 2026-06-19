<?php

namespace Tests\Unit;

use App\Models\Tag;
use App\Models\User;
use App\Models\Workspace;
use App\Services\TagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The global tag pool: every tag typed on a task is registered into its
 * workspace's vocabulary and stays available forever, independent of whether
 * any task still carries it. These exercise TagService directly.
 */
class TagServiceTest extends TestCase
{
    use RefreshDatabase;

    private TagService $service;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TagService();
        $owner = User::factory()->create();
        $this->workspace = Workspace::create([
            'name'     => 'Acme',
            'owner_id' => $owner->id,
            'color'    => '#4f46e5',
        ]);
    }

    public function test_normalize_lowercases_trims_and_dashes_whitespace(): void
    {
        $this->assertSame('frontend', $this->service->normalize('  Frontend '));
        $this->assertSame('needs-review', $this->service->normalize('Needs   Review'));
        $this->assertSame('', $this->service->normalize('   '));
    }

    public function test_registering_adds_tags_to_the_workspace_pool(): void
    {
        $this->service->registerForWorkspace($this->workspace->id, ['Frontend', 'bug']);

        $this->assertSame(['bug', 'frontend'], $this->service->allForWorkspace($this->workspace->id));
        $this->assertDatabaseHas('tags', ['workspace_id' => $this->workspace->id, 'name' => 'frontend']);
    }

    public function test_registering_is_idempotent_and_normalizes(): void
    {
        $this->service->registerForWorkspace($this->workspace->id, ['Frontend']);
        $this->service->registerForWorkspace($this->workspace->id, ['  frontend ', 'FRONTEND']);

        $this->assertSame(
            1,
            Tag::where('workspace_id', $this->workspace->id)->where('name', 'frontend')->count()
        );
    }

    public function test_blank_tags_are_ignored(): void
    {
        $this->service->registerForWorkspace($this->workspace->id, ['', '   ', 'real']);

        $this->assertSame(['real'], $this->service->allForWorkspace($this->workspace->id));
    }

    public function test_a_registered_tag_survives_with_no_task_using_it(): void
    {
        // The whole point of the global pool: once introduced, a tag stays
        // available as a suggestion regardless of which tasks reference it.
        $this->service->registerForWorkspace($this->workspace->id, ['legacy']);

        $this->assertContains('legacy', $this->service->allForWorkspace($this->workspace->id));
    }

    public function test_tags_are_scoped_to_their_workspace(): void
    {
        $other = Workspace::create([
            'name'     => 'Other',
            'owner_id' => User::factory()->create()->id,
            'color'    => '#000000',
        ]);

        $this->service->registerForWorkspace($this->workspace->id, ['alpha']);
        $this->service->registerForWorkspace($other->id, ['beta']);

        $this->assertSame(['alpha'], $this->service->allForWorkspace($this->workspace->id));
        $this->assertSame(['beta'], $this->service->allForWorkspace($other->id));
    }

    public function test_all_for_workspace_returns_names_sorted(): void
    {
        $this->service->registerForWorkspace($this->workspace->id, ['zeta', 'alpha', 'mid']);

        $this->assertSame(['alpha', 'mid', 'zeta'], $this->service->allForWorkspace($this->workspace->id));
    }
}
