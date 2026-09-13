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
    public function test_legacy_live_html_interface_is_not_exposed(): void
    {
        $this->get('/desktop/live')->assertNotFound();
        $this->get('/desktop/live/7340')->assertNotFound();
    }
    public function test_desktop_live_studio_reads_and_writes_events_through_json_api(): void
    {
        app(\App\Services\Access::class)->seed();
        $owner=User::factory()->create();$owner->roles()->attach(\App\Models\Role::where('name','Owner')->firstOrFail());
        $this->actingAs($owner)->getJson('/api/desktop/live')->assertOk()->assertJsonPath('data',[]);
        $created=$this->actingAs($owner)->postJson('/api/desktop/live',['title'=>'API stream','body'=>'Studio body','enabled'=>true,'published'=>true])->assertCreated()->assertJsonPath('data.title','API stream')->json('data');
        $this->assertNotEmpty($created['id']);
        $this->assertTrue($created['enabled']);$this->assertTrue($created['published']);
        $this->actingAs($owner)->patchJson('/api/desktop/live/'.$created['id'],['title'=>'Updated API stream','body'=>'Updated body','starts_at'=>'2027-01-01T12:00','enabled'=>true,'published'=>true])->assertOk()->assertJsonPath('data.title','Updated API stream')->assertJsonPath('data.status','scheduled');
        $this->actingAs($owner)->getJson('/api/desktop/live/'.$created['id'])->assertOk()->assertJsonPath('data.body','Updated body')->assertJsonStructure(['data'=>['ingest']]);
    }
    private function event(): SourceRecord {return SourceRecord::create(['source'=>'website','source_id'=>'live','kind'=>'video','title'=>'Live','status'=>'ready','metadata'=>['public_section'=>'live','public_published'=>true,'live_stream_enabled'=>true]]);}
    public function test_ingest_auth_requires_key_and_read_requires_publication(): void
    {
        $event=$this->event();app(Settings::class)->updateSecrets(['live_publish_'.$event->id=>'secret']);
        $data=['path'=>'live-'.$event->id,'action'=>'publish','protocol'=>'rtmp','user'=>'publisher','password'=>'wrong'];
        $this->postJson('/live/server-auth',$data)->assertUnauthorized();$this->postJson('/live/server-auth',[...$data,'password'=>'secret'])->assertNoContent();
        $read=['path'=>'live-'.$event->id,'action'=>'read','protocol'=>'hls'];$this->postJson('/live/server-auth',$read)->assertNoContent();
        $event->update(['metadata'=>[...$event->metadata,'public_published'=>false]]);$this->postJson('/live/server-auth',$read)->assertUnauthorized();
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
        $this->assertSame('no',$config['rtmpEncryption']);$this->assertArrayNotHasKey('rtmpsAddress',$config);
        $this->assertSame('0s',$config['pathDefaults']['recordDeleteAfter']);$this->assertFalse($config['api']);$this->assertSame('http',$config['authMethod']);
        if($binary=getenv('MEDIAMTX_VALIDATE_BIN')){
            Storage::disk('live-recordings')->put('validation.yml',app(PublicBroadcast::class)->configuration());
            $process=new \Symfony\Component\Process\Process([$binary,'--validate-conf',Storage::disk('live-recordings')->path('validation.yml')]);
            $process->run();$this->assertTrue($process->isSuccessful(),$process->getErrorOutput());
        }
    }

    public function test_configuration_enables_public_rtmps_only_with_readable_certificate_files(): void
    {
        Storage::fake('live-recordings');
        $cert=tempnam(sys_get_temp_dir(),'live-cert-');$key=tempnam(sys_get_temp_dir(),'live-key-');
        file_put_contents($cert,'certificate');file_put_contents($key,'private key');
        try {
            config(['platform.live_rtmp_host'=>'mannavomhimmel.de','platform.live_rtmp_port'=>1936,'platform.live_rtmp_cert'=>$cert,'platform.live_rtmp_key'=>$key]);
            $broadcast=app(PublicBroadcast::class);
            $config=json_decode($broadcast->configuration(),true,512,JSON_THROW_ON_ERROR);
            $this->assertTrue($broadcast->secureIngestReady());
            $this->assertSame('optional',$config['rtmpEncryption']);$this->assertSame(':1936',$config['rtmpsAddress']);
            $this->assertSame($cert,$config['rtmpServerCert']);$this->assertSame($key,$config['rtmpServerKey']);
            $event=$this->event();$ingest=$broadcast->ingest($event,'secret');
            $this->assertTrue($ingest['configured']);
            $this->assertSame('rtmps://mannavomhimmel.de:1936/live-'.$event->id.'?user=publisher&pass=secret',$ingest['url']);
        } finally {
            @unlink($cert);@unlink($key);
        }
    }
}
