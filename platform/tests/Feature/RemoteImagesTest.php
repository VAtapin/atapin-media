<?php
namespace Tests\Feature;
use App\Models\{ImportRun,SourceRecord,ImportItem,Media};
use App\Services\{PublicImageDownload,MediaDerivatives};
use App\Services\Importing\RemoteImageImport;
use App\Jobs\DownloadImportedImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Queue,Storage};
use Tests\TestCase;
class RemoteImagesTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void{parent::setUp();Storage::fake('local');Storage::fake('media-canonical');config(['platform.media_upload_reserve_free_bytes'=>0]);Queue::fake();Http::preventStrayRequests();}
    private function downloader(): PublicImageDownload{return new class extends PublicImageDownload{public function addresses(string $host): array{return $host==='unsafe.example'?['127.0.0.1']:['93.184.216.34'];}};}
    public function test_private_targets_and_unsafe_url_forms_are_rejected_before_request(): void
    {
        foreach(['http://unsafe.example/a','file:///tmp/a','http://user:pass@safe.example/a','http://safe.example:8080/a'] as $url){$rejected=false;try{$this->downloader()->target($url);}catch(\RuntimeException){$rejected=true;}$this->assertTrue($rejected);}
        $this->assertSame('93.184.216.34',$this->downloader()->target('https://safe.example/a')['address']);Http::assertNothingSent();
    }
    public function test_remote_images_are_local_linked_deduplicated_and_never_missing_video(): void
    {
        $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jvyoAAAAASUVORK5CYII=');Http::fake(['*'=>Http::response($png,200,['Content-Type'=>'image/png'])]);
        $run=ImportRun::create(['source'=>'youtube-takeout','source_kind'=>'youtube-takeout','status'=>'complete']);$record=SourceRecord::create(['source'=>'youtube','source_id'=>'channel:test','kind'=>'channel','title'=>'Channel','status'=>'unsorted','metadata'=>['archive_data'=>true]]);
        $service=app(RemoteImageImport::class);$service->queue($run,$record,'https://safe.example/avatar','avatar');$service->queue($run,$record,'https://safe.example/avatar','avatar');Queue::assertPushed(DownloadImportedImage::class,1);
        $item=ImportItem::firstOrFail();(new DownloadImportedImage($item->id))->handle($this->downloader(),app(MediaDerivatives::class));
        $item->refresh();$this->assertSame('connected',$item->outcome);$this->assertSame('remote-image',$item->type);$image=Media::findOrFail($item->subject_id);$this->assertSame('media-canonical',$image->disk);$this->assertSame($png,Storage::disk($image->disk)->get($image->path));$this->assertSame($image->id,$record->fresh()->metadata['remote_images']['avatar']);
    }
    public function test_redirect_to_private_address_is_rejected_and_temporary_file_removed(): void
    {
        Http::fake(['https://safe.example/*'=>Http::response('',302,['Location'=>'http://unsafe.example/image'])]);
        $rejected=false;try{$this->downloader()->download('https://safe.example/image');}catch(\RuntimeException){$rejected=true;}$this->assertTrue($rejected);Http::assertSentCount(1);$this->assertSame([],Storage::disk('local')->allFiles('media-work'));
    }
    public function test_html_is_not_an_image_and_failure_can_be_retried_individually(): void
    {
        Http::fake(['*'=>Http::response('<html>Not image</html>')]);
        $run=ImportRun::create(['source'=>'youtube-takeout','source_kind'=>'youtube-takeout','status'=>'complete']);$record=SourceRecord::create(['source'=>'youtube','source_id'=>'channel:test','kind'=>'channel','title'=>'Channel','status'=>'ready','metadata'=>[]]);
        app(RemoteImageImport::class)->queue($run,$record,'https://safe.example/image','avatar');$item=ImportItem::firstOrFail();(new DownloadImportedImage($item->id))->handle($this->downloader(),app(MediaDerivatives::class));
        $this->assertSame('failed',$item->fresh()->outcome);$this->assertSame('partial',$run->fresh()->status);$this->assertStringContainsString('Bild',$item->fresh()->metadata['reason']);$this->assertDatabaseCount('media',0);$this->assertSame([],Storage::disk('local')->allFiles('media-work'));
        app(\App\Services\Access::class)->seed();$user=\App\Models\User::factory()->create();$user->roles()->attach(\App\Models\Role::where('name','Owner')->firstOrFail());
        // Manual handle() above bypasses the real worker, which releases unique locks.
        app(\Illuminate\Bus\UniqueLock::class)->release(new DownloadImportedImage($item->id));
        $this->actingAs($user)->postJson(route('imports.item.retry',[$run,$item]))->assertOk()->assertJsonPath('status','queued');
        $this->assertSame('pending',$item->fresh()->outcome);Queue::assertPushed(DownloadImportedImage::class,2);
    }
}
