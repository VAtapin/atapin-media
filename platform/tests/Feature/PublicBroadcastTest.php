<?php
namespace Tests\Feature;
use App\Models\{SourceRecord,Media,User};
use App\Services\{PublicBroadcast,Settings};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
class PublicBroadcastTest extends TestCase
{
    use RefreshDatabase;
    public function test_owner_can_create_event_without_exposing_ingest_key_publicly(): void
    {
        app(\App\Services\Access::class)->seed();
        $owner=User::factory()->create();$owner->roles()->attach(\App\Models\Role::where('name','Owner')->firstOrFail());
        $this->actingAs($owner)->post('/desktop/live',['title'=>'New stream','enabled'=>true,'published'=>true])->assertRedirect();
        $event=SourceRecord::firstOrFail();$key=app(Settings::class)->secret('live_publish_'.$event->id);
        $this->assertNotEmpty($key);
        $this->get('/desktop/live/'.$event->id)->assertOk()->assertSee($key);
        $this->get('/live?event='.$event->id)->assertOk()->assertDontSee($key);
    }
    private function event(): SourceRecord {return SourceRecord::create(['source'=>'website','source_id'=>'live','kind'=>'video','title'=>'Live','status'=>'ready','metadata'=>['public_section'=>'live','public_published'=>true,'live_stream_enabled'=>true]]);}
    public function test_ingest_auth_requires_key_and_read_requires_publication(): void
    {
        $event=$this->event();app(Settings::class)->updateSecrets(['live_publish_'.$event->id=>'secret']);
        $data=['path'=>'live-'.$event->id,'action'=>'publish','protocol'=>'rtmp','user'=>'publisher','password'=>'wrong'];
        $this->postJson('/live/server-auth',$data)->assertUnauthorized();$this->postJson('/live/server-auth',[...$data,'password'=>'secret'])->assertNoContent();
        $read=['path'=>'live-'.$event->id,'action'=>'read','protocol'=>'hls'];$this->postJson('/live/server-auth',$read)->assertNoContent();
        $event->update(['metadata'=>[...$event->metadata,'public_published'=>false]]);$this->postJson('/live/server-auth',$read)->assertUnauthorized();
        $this->get('/desktop/live/'.$event->id)->assertRedirect('/login');$this->actingAs(User::factory()->create())->get('/desktop/live/'.$event->id)->assertForbidden();
    }
    public function test_completed_recordings_are_registered_once_and_linked_without_copying(): void
    {
        Storage::fake('live-recordings');$event=$this->event();$path='live-'.$event->id;
        Storage::disk('live-recordings')->put($path.'/segment.mp4','completed recording');$file=Storage::disk('live-recordings')->path($path.'/segment.mp4');
        $broadcast=app(PublicBroadcast::class);$broadcast->recording($path,$file);$broadcast->recording($path,$file);
        $this->assertSame(1,Media::count());$this->assertSame([Media::first()->id],$event->fresh()->metadata['media_ids']);$this->assertFileExists($file);
        $broadcast->signal($path,true);$this->assertSame('live',$event->fresh()->metadata['live_status']);
        $broadcast->signal($path,false);$this->assertSame('ended',$event->fresh()->metadata['live_status']);
    }
    public function test_configuration_keeps_ingest_and_hls_local_and_preserves_recordings(): void
    {
        Storage::fake('live-recordings');
        $config=json_decode(app(PublicBroadcast::class)->configuration(),true,512,JSON_THROW_ON_ERROR);
        $this->assertSame('127.0.0.1:1935',$config['rtmpAddress']);$this->assertSame('127.0.0.1:8888',$config['hlsAddress']);
        $this->assertSame('0s',$config['pathDefaults']['recordDeleteAfter']);$this->assertFalse($config['api']);$this->assertSame('http',$config['authMethod']);
        if($binary=getenv('MEDIAMTX_VALIDATE_BIN')){
            Storage::disk('live-recordings')->put('validation.yml',app(PublicBroadcast::class)->configuration());
            $process=new \Symfony\Component\Process\Process([$binary,'--validate-conf',Storage::disk('live-recordings')->path('validation.yml')]);
            $process->run();$this->assertTrue($process->isSuccessful(),$process->getErrorOutput());
        }
    }
}
