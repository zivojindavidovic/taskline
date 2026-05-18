<?php

namespace Tests\Unit;

use App\Models\User;
use App\Repositories\ProfileRepository;
use App\Services\ProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProfileService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProfileService(new ProfileRepository());
    }

    public function test_update_profile_persists_name_and_email(): void
    {
        $user = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com']);

        $this->service->updateProfile($user, [
            'name'  => 'New Name',
            'email' => 'new@example.com',
        ]);

        $user->refresh();

        $this->assertSame('New Name', $user->name);
        $this->assertSame('new@example.com', $user->email);
    }

    public function test_update_profile_clears_email_verified_at_on_email_change(): void
    {
        $user = User::factory()->create(['email' => 'verified@example.com']);

        $this->assertNotNull($user->email_verified_at);

        $this->service->updateProfile($user, [
            'name'  => $user->name,
            'email' => 'changed@example.com',
        ]);

        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_update_profile_preserves_email_verified_at_when_email_unchanged(): void
    {
        $user = User::factory()->create();

        $originalVerifiedAt = $user->email_verified_at;

        $this->service->updateProfile($user, [
            'name'  => 'Updated Name',
            'email' => $user->email,
        ]);

        $this->assertEquals($originalVerifiedAt, $user->refresh()->email_verified_at);
    }

    public function test_update_profile_persists_avatar_color(): void
    {
        $user = User::factory()->create();

        $this->service->updateProfile($user, [
            'name'         => $user->name,
            'email'        => $user->email,
            'avatar_color' => '#dc2626',
        ]);

        $this->assertSame('#dc2626', $user->refresh()->avatar_color);
    }

    public function test_update_theme_persists_theme(): void
    {
        $user = User::factory()->create();

        $this->service->updateTheme($user, 'dark');

        $this->assertSame('dark', $user->refresh()->theme);
    }

    public function test_update_theme_can_be_set_to_all_valid_values(): void
    {
        $user = User::factory()->create();

        foreach (['light', 'dark', 'system'] as $theme) {
            $this->service->updateTheme($user, $theme);
            $this->assertSame($theme, $user->refresh()->theme);
        }
    }

    public function test_delete_account_removes_user(): void
    {
        $user = User::factory()->create();
        $userId = $user->id;

        $this->service->deleteAccount($user);

        $this->assertNull(User::find($userId));
    }
}
