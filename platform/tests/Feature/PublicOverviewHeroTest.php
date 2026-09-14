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

    public function test_home_and_books_decorative_links_have_no_arrows(): void
    {
        foreach (['/', '/buecher'] as $url) {
            $response = $this->get($url)->assertOk();
            $document = new \DOMDocument;
            @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
            $xpath = new \DOMXPath($document);
            foreach (['public-section-cards', 'public-panel-heading', 'public-empty-slot'] as $class) {
                $nodes = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " '.$class.' ")]');
                foreach ($nodes as $node) {
                    $this->assertStringNotContainsString('→', $node->textContent, $url.' '.$class);
                }
            }
            $this->assertGreaterThan(0, $xpath->query('//a[contains(@class, "public-empty-slot") and @href]')->length);
        }
    }
}
