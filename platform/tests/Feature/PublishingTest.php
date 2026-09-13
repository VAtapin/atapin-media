<?php

namespace Tests\Feature;

use App\Jobs\PublishToPlatform;
use App\Models\{Role, SourceRecord, User};
use App\Services\{Access, Settings};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PublishingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(Access::class)->seed();
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $this->actingAs($user);
        Queue::fake();
    }

    public function test_website_publication_queues_each_connected_destination(): void
    {
        $settings = app(Settings::class);
        $settings->update(['social_connections' => ['youtube' => ['external_id' => 'channel-id', 'public_url' => 'https://youtube.com/@manna']]]);
        $settings->updateSecrets(['social_youtube' => json_encode(['access_token' => 'encrypted-token'])]);
        $record = SourceRecord::create(['source' => 'upload', 'source_id' => 'video-1', 'kind' => 'video', 'title' => 'Video', 'body' => '', 'status' => 'ready', 'metadata' => ['public_published' => false]]);

        $metadata = $record->metadata;
        $metadata['public_published'] = true;
        $metadata['publishing_targets'] = ['website', 'youtube'];
        $record->update(['metadata' => $metadata]);

        $this->assertDatabaseHas('publications', ['source_record_id' => $record->id, 'provider' => 'website', 'status' => 'published']);
        $this->assertDatabaseHas('publications', ['source_record_id' => $record->id, 'provider' => 'youtube', 'status' => 'queued']);
        Queue::assertPushed(PublishToPlatform::class, fn (PublishToPlatform $job) => $job->publicationId > 0);
    }

    public function test_publishing_endpoint_requires_ready_content_and_returns_status(): void
    {
        $record = SourceRecord::create(['source' => 'upload', 'source_id' => 'draft-1', 'kind' => 'video', 'title' => 'Draft', 'body' => '', 'status' => 'unsorted', 'metadata' => []]);
        $this->postJson(route('desktop.publishing.publish'), ['record_id' => $record->id, 'destinations' => ['website']])->assertUnprocessable();
        $this->getJson(route('desktop.publishing.index'))->assertOk()->assertJsonStructure(['records', 'destinations', 'publications', 'youtube']);
    }
}
