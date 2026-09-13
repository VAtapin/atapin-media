<?php
namespace Tests\Feature;

use App\Models\{Media, SourceRecord, User};
use App\Services\{CanonicalMediaStorage, MediaLibrary, PublicContent, ResumableMediaUploadService};
use App\Services\Importing\ImportedMediaRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CanonicalMediaStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('media-canonical', ['url' => config('filesystems.disks.media-canonical.url')]);
        config(['platform.media_upload_reserve_free_bytes' => 0]);
    }

    private function video(): string { return pack('N',24).'ftypisom'.str_repeat("\0",12); }

    public function test_video_uploads_use_one_physical_hash_file_and_keep_each_upload_identity(): void
    {
        $user = User::factory()->create();
        $bytes = $this->video();
        $a = app(MediaLibrary::class)->upload(UploadedFile::fake()->createWithContent('Ugly 💥 [YouTube].mp4', $bytes)->mimeType('video/mp4'), $user->id);
        $b = app(MediaLibrary::class)->upload(UploadedFile::fake()->createWithContent('Different name.mp4', $bytes)->mimeType('video/mp4'), $user->id);
        $this->assertSame($a->id, $b->id);
        $this->assertDatabaseCount('media', 1);
        $this->assertDatabaseCount('media_originals', 2);
        $this->assertSame('media-canonical', $a->disk);
        $this->assertSame(hash('sha256',$bytes).'.mp4', $a->path);
        $this->assertSame($a->path, $a->original_name);
        $this->assertSame($bytes, Storage::disk($a->disk)->get($a->path));
        $this->assertCount(1, Storage::disk($a->disk)->allFiles());
        $this->assertFalse(is_link(Storage::disk($a->disk)->path($a->path)));
    }

    public function test_public_player_and_desktop_assets_use_the_static_file_url(): void
    {
        $user = User::factory()->create();
        $media = app(MediaLibrary::class)->upload(UploadedFile::fake()->createWithContent('video.mp4',$this->video())->mimeType('video/mp4'),$user->id);
        $record = SourceRecord::create(['source'=>'upload','source_id'=>'video','kind'=>'video','title'=>'Static video','body'=>'Text','status'=>'ready','metadata'=>['public_published'=>true,'media_ids'=>[$media->id]]]);
        $url = $media->publicUrl();
        $this->assertStringEndsWith('/media/'.$media->path,$url);
        $this->get(app(PublicContent::class)->card($record)['url'])->assertOk()->assertSee('src="'.$url.'"',false)->assertDontSee(route('public.media',[$record,$media]),false);
        $assets = app(\App\Services\Importing\ImportedContentPresentation::class)->assets($record);
        $this->assertSame($url,$assets[0]['preview_url']);
        $this->assertSame($url,$assets[0]['download_url']);
    }

    public function test_failed_upload_database_commit_retains_staging_and_can_be_retried(): void
    {
        $user = User::factory()->create();
        $bytes = $this->video();
        $service = app(ResumableMediaUploadService::class);
        $upload = $service->start(['request_key'=>(string) Str::uuid(),'name'=>'video.mp4','size'=>strlen($bytes),'profile'=>'video'],$user->id);
        $upload = $service->append($upload,0,$bytes,hash('sha256',$bytes));
        app()->instance(ImportedMediaRegistry::class,new class extends ImportedMediaRegistry {
            public function register(array $attributes): Media { throw new \RuntimeException('Database failed'); }
        });
        try { $service->finish($upload); $this->fail('Expected database failure'); }
        catch (\RuntimeException $e) { $this->assertSame('Database failed',$e->getMessage()); }
        $this->assertSame('uploading',$upload->fresh()->status);
        $this->assertSame($bytes,Storage::disk('local')->get($upload->staging_path));
        $this->assertDatabaseCount('media',0);
        app()->forgetInstance(ImportedMediaRegistry::class);
        $media = $service->finish($upload->fresh());
        $this->assertSame($media->id,$service->finish($upload->fresh())->id);
        $this->assertFalse(Storage::disk('local')->exists($upload->staging_path));
        $this->assertCount(1,Storage::disk('media-canonical')->allFiles());
    }

    public function test_podcast_audio_and_cover_use_the_same_public_hash_storage_and_direct_urls(): void
    {
        $user=User::factory()->create();
        $audio=app(MediaLibrary::class)->upload(UploadedFile::fake()->createWithContent('Original podcast.mp3','ID3 podcast fixture')->mimeType('audio/mpeg'),$user->id);
        $cover=app(MediaLibrary::class)->upload(UploadedFile::fake()->createWithContent('Original cover.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jvyoAAAAASUVORK5CYII='))->mimeType('image/png'),$user->id);
        $record=SourceRecord::create(['source'=>'upload','source_id'=>'podcast','kind'=>'post','title'=>'Static podcast','body'=>'Podcast text','status'=>'ready','metadata'=>['public_published'=>true,'public_section'=>'podcast','cover_media_id'=>$cover->id,'media_ids'=>[$audio->id,$cover->id]]]);
        foreach([$audio,$cover] as $media) {
            $this->assertSame('media-canonical',$media->disk);
            $this->assertSame($media->path,$media->original_name);
            $this->assertTrue(Storage::disk($media->disk)->exists($media->path));
            $this->assertStringEndsWith('/media/'.$media->path,$media->publicUrl());
        }
        $this->get('/podcast?episode='.$record->id)->assertOk()->assertSee('src="'.$audio->publicUrl().'"',false)->assertSee($cover->publicUrl(),false);
        $this->assertCount(2,Storage::disk('media-canonical')->allFiles());
    }

    public function test_archives_documents_and_html_keep_private_storage(): void
    {
        foreach (['application/zip','application/pdf','text/html'] as $mime) {
            $file = UploadedFile::fake()->createWithContent('fixture','Private '.$mime);
            $stored = app(CanonicalMediaStorage::class)->storePath($file->getRealPath(),$mime);
            $this->assertSame('local',$stored['disk']);
            $this->assertFileExists($file->getRealPath());
        }
        $this->assertSame([],Storage::disk('media-canonical')->allFiles());
    }

    public function test_interrupted_copy_removes_the_partial_public_file_and_keeps_source(): void
    {
        $file = UploadedFile::fake()->createWithContent('video.mp4',$this->video());
        try {
            app(CanonicalMediaStorage::class)->storePath($file->getRealPath(),'video/mp4',null,null,fn()=>throw new \RuntimeException('Stopped'));
            $this->fail('Expected interrupted copy');
        } catch (\RuntimeException $e) { $this->assertSame('Stopped',$e->getMessage()); }
        $this->assertFileExists($file->getRealPath());
        $this->assertSame([],Storage::disk('media-canonical')->allFiles());
    }
}
