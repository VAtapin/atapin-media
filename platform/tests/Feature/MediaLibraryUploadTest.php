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
        $this->assertFalse(Storage::disk('local')->exists(\App\Services\MediaUploadCutover::MARKER));
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
        $this->assertSame('atapin-library-cutover/v1', json_decode(Storage::disk('local')->get(\App\Services\MediaUploadCutover::MARKER), true)['schema']);

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

    public function test_uploaded_zip_is_imported_through_center_with_readable_originals(): void
    {
        Storage::fake('local'); $this->actingAs($this->user('Owner'));
        $root = sys_get_temp_dir().'/atapin-upload-import-'.bin2hex(random_bytes(8)); mkdir($root,0700);
        config(['platform.import_inbox_root'=>$root,'filesystems.disks.import-inbox.root'=>$root,'platform.media_upload_reserve_free_bytes'=>0]);
        Storage::forgetDisk('import-inbox');
        try {
            $zip = new \ZipArchive; $zip->open($root.'/fixture.zip', \ZipArchive::CREATE);
            $zip->addFromString('original.txt','Original archive file');
            $zip->addFromString('content.json',json_encode(['schema'=>'atapin-content/v1','records'=>[
                ['source'=>'youtube','id'=>'post-123','kind'=>'post','title'=>'Archiv Beitrag','body'=>'Original text'],
            ]])); $zip->close(); $bytes = file_get_contents($root.'/fixture.zip');
            $start = $this->postJson('/desktop/media/uploads',['request_key'=>(string) Str::uuid(),'name'=>'archive.zip','size'=>strlen($bytes)])->assertOk();
            $id = $start->json('id');
            $this->call('POST', '/desktop/media/uploads/'.$id.'/chunk',[],[],[],['CONTENT_TYPE'=>'application/octet-stream',
                'HTTP_X_UPLOAD_OFFSET'=>'0','HTTP_X_CHUNK_SHA256'=>hash('sha256',$bytes)],$bytes)->assertOk();
            $finish = $this->postJson('/desktop/media/uploads/'.$id.'/finish')->assertOk();
            \Illuminate\Support\Facades\Queue::fake();
            $queued = $this->postJson('/desktop/imports',['source'=>'local-archive','media_id'=>$finish->json('media_id')])->assertOk();
            $run = \App\Models\ImportRun::findOrFail($queued->json('import_id'));
            app(\App\Services\Importing\ImportCenter::class)->run($run);
            $this->assertSame('complete',$run->fresh()->status);
            $original = Media::where('source','local-archive')->where('original_name','original.txt')->firstOrFail();
            $this->assertSame('Original archive file',Storage::disk($original->disk)->get($original->path));
            $this->assertDatabaseHas('source_records',['source'=>'youtube','source_id'=>'post-123','kind'=>'post','status'=>'unsorted']);
            $this->getJson('/desktop/content?section=posts')->assertOk()->assertJsonPath('data.0.title','Archiv Beitrag');
        } finally { \Illuminate\Support\Facades\File::deleteDirectory($root); }
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
