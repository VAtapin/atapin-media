<?php

namespace Tests\Feature;

use App\Models\{Media, Publication, SourceRecord};
use App\Services\Settings;
use App\Services\Publishing\{ConnectionStore, PublishingService, TelegramWebsite};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http, Queue, Storage};
use Tests\TestCase;

class TelegramWebsiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('telegram-test');
        Http::preventStrayRequests();
        app(Settings::class)->update(['social_connections' => ['telegram' => ['external_id' => '@channel', 'bot_username' => 'ExampleBot', 'mini_app_enabled' => true]]]);
        app(ConnectionStore::class)->saveCredentials('telegram', ['api_key' => 'bot-token']);
    }

    private function record(bool $cover = false): SourceRecord
    {
        $metadata = ['public_published' => true, 'publishing_targets' => ['website'], 'short_description' => 'A short description'];
        if ($cover) {
            Storage::disk('telegram-test')->put('cover.jpg', 'image-bytes');
            $image = Media::create(['source' => 'upload', 'source_id' => 'cover', 'title' => 'Cover', 'kind' => 'image', 'disk' => 'telegram-test', 'path' => 'cover.jpg', 'original_name' => 'cover.jpg', 'mime' => 'image/jpeg', 'bytes' => 11]);
            $metadata['media_ids'] = [$image->id];
        }
        return SourceRecord::create(['source' => 'website', 'source_id' => uniqid(), 'kind' => 'video', 'title' => 'Video', 'body' => 'Full description not sent', 'status' => 'ready', 'metadata' => $metadata]);
    }

    public function test_video_announcement_sends_a_photo_or_text_but_never_video_bytes(): void
    {
        foreach ([false, true] as $cover) {
            $record = $this->record($cover);
            $publication = Publication::create(['source_record_id' => $record->id, 'provider' => 'telegram', 'status' => 'queued']);
            Http::fake([
                'https://api.telegram.org/botbot-token/getChat' => Http::response(['ok' => true, 'result' => ['type' => 'channel', 'username' => 'channel']]),
                'https://api.telegram.org/botbot-token/'.($cover ? 'sendPhoto' : 'sendMessage') => Http::response(['ok' => true, 'result' => ['message_id' => $record->id]]),
            ]);
            app(PublishingService::class)->execute($publication->id);
            $this->assertSame('published', $publication->fresh()->status);
            $this->assertTrue($publication->fresh()->payload['telegram_website_link']);
            $this->assertSame($cover, $publication->fresh()->payload['telegram_media']);
            Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/sendVideo'));
            Http::assertSentCount(2);
        }
    }

    public function test_announcement_has_a_material_mini_app_link_and_normal_website_fallback(): void
    {
        $record = $this->record();
        $data = app(TelegramWebsite::class)->announcement($record);
        $this->assertSame('Video'."\n\n".'A short description', $data['text']);
        $this->assertSame('https://t.me/ExampleBot?startapp=record_'.$record->id, $data['reply_markup']['inline_keyboard'][0][0]['url']);
        $url = app(TelegramWebsite::class)->materialUrl('record_'.$record->id);
        $this->assertSame($url, $data['reply_markup']['inline_keyboard'][0][1]['url']);
        $this->getJson('/telegram/material/record_'.$record->id)->assertOk()->assertJsonPath('url', $url);
        $record->update(['metadata' => [...$record->metadata, 'public_published' => false]]);
        $this->getJson('/telegram/material/record_'.$record->id)->assertNotFound();
        $this->getJson('/telegram/material/https%3A%2F%2Fevil.example')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_video_link_edits_keep_the_sent_message_type_and_use_short_text(): void
    {
        $record = $this->record(true);
        $publication = Publication::create(['source_record_id' => $record->id, 'provider' => 'telegram', 'status' => 'queued', 'external_id' => '42',
            'payload' => ['action' => 'update', 'telegram_media' => false, 'telegram_website_link' => true]]);
        Http::fake(['https://api.telegram.org/botbot-token/editMessageText' => Http::response(['ok' => true])]);
        app(PublishingService::class)->execute($publication->id);
        $this->assertSame('published', $publication->fresh()->status);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/editMessageText') && $request['text'] === 'Video'."\n\n".'A short description' && isset($request['reply_markup']));
        Http::assertSentCount(1);
    }
}
