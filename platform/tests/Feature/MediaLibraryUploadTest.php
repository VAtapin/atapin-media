<?php
namespace Tests\Feature;

use App\Models\Media;
use App\Models\ResumableMediaUpload;
use App\Models\Role;
use App\Models\User;
use App\Services\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MediaLibraryUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.locale' => 'de', 'platform.brand' => 'Manna Vom Himmel']);
        app()->setLocale('de');
        app(Access::class)->seed();
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['password' => 'kurz5']);
        $user->roles()->attach(Role::where('name', $role)->firstOrFail());
        return $user;
    }

    public function test_resumable_media_upload_flow(): void
    {
        Storage::fake('local');
        $this->actingAs($this->user('Owner'));

        $fileContents = 'audio-and-images-upload-test';
        $size = strlen($fileContents);

        $start = $this->postJson('/desktop/media/uploads', [
            'request_key' => Str::uuid()->toString(),
            'name' => 'example.txt',
            'size' => $size,
        ])->assertOk();
        $uploadId = $start->json('id');
        $chunk = substr($fileContents, 0, 4);
        $hash = hash('sha256', $chunk);
        $this->call('POST', "/desktop/media/uploads/$uploadId/chunk", [], [], [], [
            'CONTENT_TYPE' => 'application/octet-stream',
            'HTTP_X_UPLOAD_OFFSET' => '0',
            'HTTP_X_Chunk_SHA256' => $hash,
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ], $chunk)->assertOk()->assertJson(['id' => $uploadId, 'offset' => 4]);

        $chunk2 = substr($fileContents, 4);
        $hash2 = hash('sha256', $chunk2);
        $this->call('POST', "/desktop/media/uploads/$uploadId/chunk", [], [], [], [
            'CONTENT_TYPE' => 'application/octet-stream',
            'HTTP_X_UPLOAD_OFFSET' => '4',
            'HTTP_X_Chunk_SHA256' => $hash2,
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ], $chunk2)->assertOk()->assertJson(['id' => $uploadId, 'offset' => $size]);

        $this->post("/desktop/media/uploads/$uploadId/finish")->assertOk()->assertJson(['status' => 'saved']);

        $this->assertDatabaseHas('media', [
            'source' => 'upload',
            'source_id' => $uploadId,
            'status' => 'unsorted',
            'mime' => 'text/plain',
        ]);
        $media = Media::where('source', 'upload')->where('source_id', $uploadId)->firstOrFail();
        $this->assertTrue(Storage::disk($media->disk)->exists($media->path));
        $this->assertDatabaseHas('resumable_media_uploads', [
            'id' => $uploadId,
            'status' => ResumableMediaUpload::STATUS_COMPLETE,
        ]);
    }

    public function test_resumable_upload_chunk_can_be_retried_without_corrupting_offset(): void
    {
        Storage::fake('local');
        $this->actingAs($this->user('Editor'));

        $fileContents = str_repeat('x', 10);
        $start = $this->postJson('/desktop/media/uploads', [
            'request_key' => (string) Str::uuid(),
            'name' => 'retry.bin',
            'size' => strlen($fileContents),
        ])->assertOk();
        $uploadId = $start->json('id');

        $chunk = substr($fileContents, 0, 5);
        $hash = hash('sha256', $chunk);
        $first = $this->call('POST', "/desktop/media/uploads/$uploadId/chunk", [], [], [], [
            'CONTENT_TYPE' => 'application/octet-stream',
            'HTTP_X_UPLOAD_OFFSET' => '0',
            'HTTP_X_Chunk_SHA256' => $hash,
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ], $chunk)->assertOk()->json();
        $this->assertSame(5, $first['offset']);

        $retry = $this->call('POST', "/desktop/media/uploads/$uploadId/chunk", [], [], [], [
            'CONTENT_TYPE' => 'application/octet-stream',
            'HTTP_X_UPLOAD_OFFSET' => '0',
            'HTTP_X_Chunk_SHA256' => $hash,
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ], $chunk)->assertOk()->json();
        $this->assertSame(5, $retry['offset']);
    }
}
