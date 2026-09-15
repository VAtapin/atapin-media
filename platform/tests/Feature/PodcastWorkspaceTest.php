<?php

namespace Tests\Feature;

use App\Models\{Media, Permission, Role, SourceRecord, User};
use App\Services\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PodcastWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        app(Access::class)->seed();
        $owner = User::factory()->create();
        $owner->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $this->actingAs($owner);
    }

    private function media(string $name, string $kind, string $mime): Media
    {
        Storage::disk('local')->put($name, $name);

        return Media::create([
            'source' => 'upload', 'source_id' => $name, 'title' => $name,
            'original_name' => $name, 'disk' => 'local', 'path' => $name,
            'kind' => $kind, 'mime' => $mime, 'bytes' => strlen($name),
            'sha256' => hash('sha256', $name), 'status' => 'ready', 'metadata' => [],
        ]);
    }

    public function test_audio_episode_is_created_with_editor_fields_and_local_assets(): void
    {
        $audio = $this->media('episode.mp3', 'audio', 'audio/mpeg');
        $cover = $this->media('cover.webp', 'image', 'image/webp');

        $response = $this->postJson('/desktop/content', [
            'title' => 'Hoffnung im Alltag', 'body' => 'Beschreibung der Episode',
            'kind' => 'video', 'public_section' => 'podcast', 'status' => 'review',
            'podcast_format' => 'audio', 'podcast_media_id' => $audio->id,
            'cover_media_id' => $cover->id, 'public_published_at' => today()->toDateString(),
            'tags' => ['Hoffnung', 'Alltag'], 'episode_number' => 12, 'season' => 2,
            'author' => 'Redaktion', 'guest' => 'Gast', 'seo_title' => 'Podcast Hoffnung',
            'seo_description' => 'Eine Folge über Hoffnung.', 'transcript' => 'Gesprochener Text',
            'external_podcast_url' => 'https://example.test/podcast/12',
        ])->assertCreated()->assertJsonPath('status', 'saved');

        $record = SourceRecord::findOrFail($response->json('id'));
        $this->assertSame('podcast', $record->metadata['public_section']);
        $this->assertSame('audio', $record->metadata['podcast_format']);
        $this->assertSame($audio->id, $record->metadata['primary_audio_id']);
        $this->assertSame($cover->id, $record->metadata['cover_media_id']);
        $this->assertSame(['Hoffnung', 'Alltag'], $record->metadata['tags']);
        $this->assertSame(12, $record->metadata['episode_number']);

        $this->getJson('/desktop/content/'.$record->id)
            ->assertOk()
            ->assertJsonPath('target_profile', 'podcast')
            ->assertJsonPath('podcast_format', 'audio')
            ->assertJsonPath('primary_audio_id', $audio->id)
            ->assertJsonPath('assets.0.available', true)
            ->assertJsonCount(2, 'assets');
        $this->getJson('/desktop/content?section=podcast')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.title', 'Hoffnung im Alltag');
    }

    public function test_wrong_podcast_file_format_rolls_back_the_episode(): void
    {
        $video = $this->media('episode.mp4', 'video', 'video/mp4');

        $this->postJson('/desktop/content', [
            'title' => 'Ungültige Episode', 'body' => '', 'kind' => 'video',
            'public_section' => 'podcast', 'status' => 'unsorted',
            'podcast_format' => 'audio', 'podcast_media_id' => $video->id,
        ])->assertUnprocessable()->assertJsonPath('message', __('imports.podcast_media_format_mismatch'));

        $this->assertDatabaseMissing('source_records', ['title' => 'Ungültige Episode']);
        $this->assertDatabaseMissing('media_usages', ['media_id' => $video->id]);
    }

    public function test_audio_asset_role_is_limited_to_podcast_records(): void
    {
        $audio = $this->media('voice.wav', 'audio', 'audio/wav');
        $regular = SourceRecord::create([
            'source' => 'manual', 'source_id' => 'regular-video', 'kind' => 'video',
            'title' => 'Normales Video', 'body' => '', 'status' => 'ready', 'metadata' => [],
        ]);
        $podcast = SourceRecord::create([
            'source' => 'manual', 'source_id' => 'podcast-video', 'kind' => 'video',
            'title' => 'Podcast', 'body' => '', 'status' => 'ready',
            'metadata' => ['public_section' => 'podcast', 'podcast_format' => 'audio'],
        ]);

        $this->postJson('/desktop/content/'.$regular->id.'/assets', [
            'action' => 'replace', 'role' => 'audio', 'media_id' => $audio->id,
        ])->assertUnprocessable();
        $this->postJson('/desktop/content/'.$podcast->id.'/assets', [
            'action' => 'replace', 'role' => 'audio', 'media_id' => $audio->id,
        ])->assertOk();

        $this->assertSame($audio->id, $podcast->fresh()->metadata['primary_audio_id']);
        $this->assertArrayNotHasKey('primary_audio_id', $regular->fresh()->metadata);
    }

    public function test_failed_episode_file_update_rolls_back_editor_changes(): void
    {
        $video = $this->media('wrong-new-file.mp4', 'video', 'video/mp4');
        $podcast = SourceRecord::create([
            'source' => 'manual', 'source_id' => 'atomic-podcast', 'kind' => 'video',
            'title' => 'Unveränderter Titel', 'body' => 'Beschreibung', 'status' => 'review',
            'metadata' => ['public_section' => 'podcast', 'podcast_format' => 'audio'],
        ]);

        $this->patchJson('/desktop/content/'.$podcast->id, [
            'title' => 'Darf nicht gespeichert werden', 'body' => 'Neue Beschreibung',
            'kind' => 'video', 'status' => 'ready', 'target_profile' => 'podcast',
            'podcast_format' => 'audio', 'podcast_media_id' => $video->id,
        ])->assertUnprocessable()->assertJsonPath('message', __('imports.podcast_media_format_mismatch'));

        $podcast->refresh();
        $this->assertSame('Unveränderter Titel', $podcast->title);
        $this->assertSame('review', $podcast->status);
        $this->assertArrayNotHasKey('primary_audio_id', $podcast->metadata);
    }

    public function test_editor_without_media_edit_can_create_assetless_episode_but_cannot_attach_existing_media(): void
    {
        $audio = $this->media('restricted.mp3', 'audio', 'audio/mpeg');
        $editorRole = Role::where('name', 'Editor')->firstOrFail();
        $editorRole->permissions()->detach(Permission::where('name', 'media.edit')->firstOrFail());
        $editor = User::factory()->create();
        $editor->roles()->attach($editorRole);
        $this->actingAs($editor);
        $base = [
            'body' => '', 'kind' => 'video', 'public_section' => 'podcast',
            'status' => 'unsorted', 'podcast_format' => 'audio',
        ];

        $this->postJson('/desktop/content', ['title' => 'Episode ohne Datei', ...$base])
            ->assertCreated();
        $this->postJson('/desktop/content', [
            'title' => 'Episode mit fremder Datei', ...$base, 'podcast_media_id' => $audio->id,
        ])->assertForbidden();

        $this->assertDatabaseHas('source_records', ['title' => 'Episode ohne Datei']);
        $this->assertDatabaseMissing('source_records', ['title' => 'Episode mit fremder Datei']);
        $this->assertDatabaseMissing('media_usages', ['media_id' => $audio->id]);
    }
}
