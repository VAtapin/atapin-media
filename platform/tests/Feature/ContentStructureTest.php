<?php

namespace Tests\Feature;

use App\Jobs\AnswerDesktopAi;
use App\Models\{DesktopAiRequest, Role, SourceRecord, User};
use App\Services\{Access, ContentStructureReview, DesktopAi, Settings};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http, Queue};
use Tests\TestCase;

class ContentStructureTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        app(Access::class)->seed();
        Queue::fake();
        Http::preventStrayRequests();
        app(Settings::class)->update(['ai_enabled' => true, 'ai_provider' => 'openai', 'ai_model' => 'test-model']);
        app(Settings::class)->updateSecrets(['ai_api_key' => 'test-only']);
        $this->owner = User::factory()->create();
        $this->owner->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $this->actingAs($this->owner);
    }

    private function record(string $body): SourceRecord
    {
        return SourceRecord::create([
            'source' => 'youtube',
            'source_id' => uniqid(),
            'title' => 'Imported text',
            'body' => $body,
            'kind' => 'post',
            'status' => 'ready',
            'metadata' => ['public_section' => 'beitraege'],
        ]);
    }

    public function test_structure_review_changes_only_html_structure_and_is_not_queued_twice(): void
    {
        $record = $this->record("First paragraph.\n\n\nSecond paragraph.");
        Http::fake(['api.openai.com/*' => Http::response([
            'status' => 'completed',
            'output' => [['content' => [['type' => 'output_text', 'text' => json_encode([
                'answer' => '',
                'title' => '',
                'short_description' => '',
                'seo_title' => '',
                'seo_description' => '',
                'social_text' => '',
                'structured_body' => '<p>First paragraph.</p><p>Second paragraph.</p>',
            ])]]]],
        ])]);

        $this->postJson('/desktop/content/'.$record->id.'/structure')->assertAccepted()->assertJsonPath('status', 'queued');
        $this->postJson('/desktop/content/'.$record->id.'/structure')->assertAccepted()->assertJsonPath('status', 'skipped');
        Queue::assertPushed(AnswerDesktopAi::class, 1);

        Queue::pushed(AnswerDesktopAi::class)->first()->handle(app(DesktopAi::class));

        $fresh = $record->fresh();
        $this->assertSame('<p>First paragraph.</p><p>Second paragraph.</p>', $fresh->body);
        $this->assertSame('html', $fresh->metadata['body_format']);
        $this->assertSame('applied', $fresh->metadata['structure_review']['status']);
        $this->getJson('/desktop/content/'.$record->id)->assertJsonPath('structure_review.reviewed', true);
    }

    public function test_batch_skips_currently_reviewed_texts(): void
    {
        $first = $this->record('First text.');
        $second = $this->record('Second text.');
        $first->update(['metadata' => [
            'public_section' => 'beitraege',
            'structure_review' => ['status' => 'no_change', 'source_version' => app(ContentStructureReview::class)->version($first)],
        ]]);

        $this->postJson('/desktop/content/structure/batch', ['filters' => ['kind' => 'post']])
            ->assertAccepted()
            ->assertJsonPath('queued', 1)
            ->assertJsonPath('skipped', 1);
        Queue::assertPushed(AnswerDesktopAi::class, 1);
        $this->assertNotNull(DesktopAiRequest::where('source_record_id', $second->id)->first());
    }
}
