<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use App\Models\Media;
use App\Jobs\ProbeMedia;
use App\Services\MediaTechnicalProbe;
class MediaTechnicalTest extends TestCase
{
    use RefreshDatabase;
    public function test_probe_reads_image_headers_and_preserves_manual_metadata(): void
    {
        Queue::fake();Storage::fake('private');Storage::disk('private')->put('cover.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLbtAAAAABJRU5ErkJggg=='));
        $media=Media::create(['source'=>'upload','title'=>'Owner title','original_name'=>'cover.png','kind'=>'image','mime'=>'image/png','disk'=>'private','path'=>'cover.png','bytes'=>70,'status'=>'ready','metadata'=>['summary'=>'Owner summary']]);
        (new ProbeMedia($media->id))->handle(app(MediaTechnicalProbe::class));$media->refresh();
        $this->assertSame(1,$media->metadata['technical']['width']);$this->assertSame(1,$media->metadata['technical']['height']);
        $this->assertSame('ready',$media->metadata['technical_status']);$this->assertSame('Owner summary',$media->metadata['summary']);$this->assertSame('Owner title',$media->title);
        Storage::disk('private')->delete('cover.png');(new ProbeMedia($media->id))->handle(app(MediaTechnicalProbe::class));
        $this->assertSame('failed',$media->fresh()->metadata['technical_status']);$this->assertSame(1,$media->fresh()->metadata['technical']['width']);
    }
    public function test_probe_normalizes_video_and_checks_permission(): void
    {
        $result=app(MediaTechnicalProbe::class)->normalize(['format'=>['duration'=>'123.5','format_name'=>'mov,mp4'],'streams'=>[['codec_type'=>'audio','codec_name'=>'aac'],['codec_type'=>'video','codec_name'=>'h264','width'=>1920,'height'=>1080]]]);
        $this->assertSame(['format'=>'mov,mp4','codec'=>'h264','duration'=>123.5,'width'=>1920,'height'=>1080],$result);
        Queue::fake();app(\App\Services\Access::class)->seed();$user=\App\Models\User::factory()->create();$this->actingAs($user);
        $media=Media::create(['source'=>'upload','title'=>'Video','original_name'=>'video.mp4','kind'=>'video','mime'=>'video/mp4','disk'=>'private','path'=>'video.mp4','bytes'=>5,'status'=>'unsorted']);
        $this->postJson('/desktop/media/'.$media->id.'/technical')->assertForbidden();$user->roles()->attach(\App\Models\Role::where('name','Owner')->firstOrFail());
        $this->postJson('/desktop/media/'.$media->id.'/technical')->assertOk();Queue::assertPushed(ProbeMedia::class);
    }
}
