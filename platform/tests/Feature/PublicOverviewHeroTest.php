<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicOverviewHeroTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_pages_use_one_shared_hero_shell(): void
    {
        foreach (['/', '/videos', '/beitraege', '/buecher', '/live', '/podcast', '/community', '/ueber-uns', '/unsere-mission'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('public-overview-hero', false)
                ->assertSee('public-overview-hero-inner', false)
                ->assertSee('public-overview-feature', false)
                ->assertDontSee('public-category-hero', false)
                ->assertDontSee('public-hero-inner', false)
                ->assertDontSee('@include(', false);
        }
    }
}
