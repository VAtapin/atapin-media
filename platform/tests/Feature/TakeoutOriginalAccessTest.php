<?php
namespace Tests\Feature;
use App\Models\ImportRun;
use App\Models\Media;
use App\Models\Role;
use App\Models\User;
use App\Services\Access;
use App\Services\Importing\LocalVideoAuditAdapter;
use App\Services\MediaOriginalLocator;
use App\Services\MediaTechnicalProbe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TakeoutOriginalAccessTest extends TestCase
{
    use RefreshDatabase;
    private string $root;
    protected function setUp(): void
    {
        parent::setUp();Queue::fake();$this->root=sys_get_temp_dir().'/takeout-access-'.bin2hex(random_bytes(8));mkdir($this->root,0700);
        config(['filesystems.disks.takeout.root'=>$this->root]);Storage::forgetDisk('takeout');
    }
    protected function tearDown(): void {File::deleteDirectory($this->root);parent::tearDown();}
    private function media(string $path): Media
    {
        return Media::create(['source'=>'youtube-takeout','title'=>'Original video','original_name'=>basename($path),'disk'=>'takeout','path'=>$path,'kind'=>'video','mime'=>'video/mp4','bytes'=>5,'status'=>'unsorted']);
    }
    public function test_unicode_original_is_locally_previewable_and_downloadable_without_path_renaming(): void
    {
        $name="Video\u{200D}.mp4";file_put_contents($this->root.'/'.$name,'video');$media=$this->media($name);
        $this->assertFileExists(app(MediaOriginalLocator::class)->path(['disk'=>'takeout','path'=>$name]));
        $this->assertSame(['disk'=>'takeout','path'=>$name],app(MediaOriginalLocator::class)->find($media));
        $this->get(route('media.preview',$media))->assertRedirect(route('login'));
        app(Access::class)->seed();$user=User::factory()->create();$user->roles()->attach(Role::where('name','Owner')->firstOrFail());$this->actingAs($user);
        $this->get(route('media.preview',$media),['Range'=>'bytes=0-1'])->assertStatus(206)->assertHeader('Content-Range','bytes 0-1/5');
        $this->get(route('media.download',$media))->assertOk()->assertDownload();
        $this->assertFileExists($this->root.'/'.$name);
    }
    public function test_native_lookup_still_rejects_traversal_and_nul_paths(): void
    {
        file_put_contents($this->root.'/valid.mp4','video');
        $this->assertNull(app(MediaOriginalLocator::class)->find($this->media('../'.basename($this->root).'/valid.mp4')));
        $this->assertNull(app(MediaOriginalLocator::class)->find($this->media("bad\0.mp4")));
    }
    public function test_one_lookup_failure_is_reported_without_aborting_remaining_video_checks(): void
    {
        file_put_contents($this->root.'/valid.mp4','video');$bad=$this->media('bad.mp4');$good=$this->media('valid.mp4');
        app()->instance(MediaOriginalLocator::class,new class($bad->id) extends MediaOriginalLocator {
            public function __construct(private string $bad) {}
            public function find(Media $media): ?array {if($media->id===$this->bad)throw new \RuntimeException('Corrupted path detected');return parent::find($media);}
        });
        $probe=\Mockery::mock(MediaTechnicalProbe::class);$probe->shouldReceive('inspect')->once()->withArgs(fn($m)=>$m->id===$good->id)->andReturn(['width'=>160,'height'=>90]);app()->instance(MediaTechnicalProbe::class,$probe);
        $run=ImportRun::create(['source'=>'local-video-check','status'=>'running']);app(LocalVideoAuditAdapter::class)->import($run);
        $this->assertSame(2,$run->fresh()->discovered);$this->assertSame(1,$run->fresh()->imported);
        $this->assertDatabaseHas('import_items',['import_run_id'=>$run->id,'subject_id'=>$bad->id,'outcome'=>'failed']);
        $this->assertDatabaseHas('import_items',['import_run_id'=>$run->id,'subject_id'=>$good->id,'outcome'=>'available']);
    }
}
