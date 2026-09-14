<?php

namespace Tests\Feature;

use App\Models\{Role, User};
use App\Services\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallationHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_is_private_and_contains_only_operational_facts(): void
    {
        app(Access::class)->seed();
        $owner = User::factory()->create();
        $owner->roles()->attach(Role::where('name', 'Owner')->firstOrFail());

        $this->actingAs($owner)->getJson('/desktop/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.runtime.status', 'ok')
            ->assertJsonPath('checks.database.status', 'ok')
            ->assertJsonPath('checks.storage.status', 'ok')
            ->assertJsonPath('checks.cache.status', 'ok')
            ->assertJsonPath('checks.search.status', 'ok')
            ->assertJsonPath('checks.scheduler.status', 'unknown')
            ->assertJsonMissingPath('checks.database.password')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_health_requires_settings_permission(): void
    {
        app(Access::class)->seed();
        $this->actingAs(User::factory()->create())->getJson('/desktop/health')->assertForbidden();
    }
}
