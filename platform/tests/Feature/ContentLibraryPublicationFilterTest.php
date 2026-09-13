<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SourceRecord;
use App\Models\User;
use App\Services\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentLibraryPublicationFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_library_can_find_published_content_across_content_kinds(): void
    {
        app(Access::class)->seed();
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $this->actingAs($user);

        SourceRecord::create([
            'source' => 'upload', 'source_id' => 'published-video', 'kind' => 'video',
            'title' => 'Published video', 'body' => 'Video', 'status' => 'ready',
            'metadata' => ['public_published' => true],
        ]);
        SourceRecord::create([
            'source' => 'upload', 'source_id' => 'published-post', 'kind' => 'post',
            'title' => 'Published post', 'body' => 'Article', 'status' => 'ready',
            'metadata' => ['public_published' => true],
        ]);
        SourceRecord::create([
            'source' => 'upload', 'source_id' => 'private-video', 'kind' => 'video',
            'title' => 'Private video', 'body' => 'Not published', 'status' => 'ready',
            'metadata' => [],
        ]);

        $response = $this->getJson('/desktop/content?publication=published');

        $response->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.public_published', true)
            ->assertJsonPath('data.1.public_published', true);
        $this->getJson('/desktop/content?publication=unpublished')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Private video');
    }
}
