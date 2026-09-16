<?php
namespace Tests\Feature;
use App\Models\{User,Role,SourceRecord,LiveBrowserSession};
use App\Services\{Access,Settings,PublicBroadcast,BrowserBroadcast,BrowserLiveRelay,LiveServer};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Storage};
use Tests\TestCase;
class BrowserLiveTest extends TestCase
{
    use RefreshDatabase;
    private User $owner;
    private array $pathItems=[];
    private $whipHandler=null;
    protected function setUp(): void
    {
        parent::setUp();app(Access::class)->seed();Http::preventStrayRequests();Storage::fake('live-recordings');
        $this->owner=User::factory()->create();$this->owner->roles()->attach(Role::where('name','Owner')->firstOrFail());$this->actingAs($this->owner);
        app(Settings::class)->updateSecrets(['live_control'=>str_repeat('s',64)]);
        app(Settings::class)->update(['live_browser_enabled'=>true,'live_browser_host'=>'stream.example.test']);
        Http::fake(['http://127.0.0.1:9997/v3/paths/list'=>fn()=>Http::response(['items'=>$this->pathItems]),
            'http://127.0.0.1:9997/v3/*/conns/kick/*'=>Http::response([],200),
            'http://127.0.0.1:8889/*'=>fn($request)=>$this->whipHandler?($this->whipHandler)($request):Http::response('',503)]);
    }
    private function event(array $metadata=[]): SourceRecord
    {
        return SourceRecord::create(['source'=>'website','source_id'=>\Illuminate\Support\Str::uuid(),'kind'=>'video','title'=>'Browser Live',
            'status'=>'ready','metadata'=>[...['public_section'=>'live','public_published'=>true,'live_stream_enabled'=>true,'live_browser_enabled'=>true],...$metadata]]);
    }
    private function browserSession(SourceRecord $record): LiveBrowserSession
    {
        return LiveBrowserSession::create(['id'=>(string)\Illuminate\Support\Str::uuid(),'user_id'=>$this->owner->id,'source_record_id'=>$record->id,'status'=>'connected','expires_at'=>now()->addMinutes(2)]);
    }
    public function test_relay_keeps_process_output_pipes_and_logs_safe_failure_without_secret(): void
    {
        $session=$this->browserSession($this->event());
        $process=(new \ReflectionMethod(BrowserLiveRelay::class,'process'))->invoke(new BrowserLiveRelay(),$session);
        $this->assertFalse($process->isOutputDisabled(),'Plesk open_basedir may exclude /dev/null');
        $this->assertStringContainsString('rtsp',$process->getCommandLine());
        $this->assertStringContainsString('127.0.0.1:8554/live-'.$session->source_record_id,$process->getCommandLine());
        $this->assertStringContainsString('0:a:0?',$process->getCommandLine());
        $this->assertStringNotContainsString('libx264',$process->getCommandLine());
        $errorCode=new \ReflectionMethod(BrowserLiveRelay::class,'errorCode');
        $this->assertSame('encoder_unavailable',$errorCode->invoke(new BrowserLiveRelay(),"Unknown encoder 'aac'"));
        $this->assertSame('media_track_missing',$errorCode->invoke(new BrowserLiveRelay(),"Stream map '0:a:0' matches no streams"));
        $this->assertSame('codec_parameters_unavailable',$errorCode->invoke(new BrowserLiveRelay(),'Could not find codec parameters: dimensions not set'));
        $file=tempnam(sys_get_temp_dir(),'live-relay-');config(['logging.channels.live_browser_transport.path'=>$file]);
        $relay=new class extends BrowserLiveRelay {
            protected function process(LiveBrowserSession $session): \Symfony\Component\Process\Process
            {
                return new \Symfony\Component\Process\Process([PHP_BINARY,'-r',
                    'fwrite(STDERR,"Authorization failed for rtsp://control:private-secret@127.0.0.1\\n");exit(42);']);
            }
        };
        try {
            $relay->run('browser-'.$session->id);
            $this->assertSame('failed',$session->fresh()->status);
            $log=file_get_contents($file);
            $this->assertStringContainsString('encoder_exited',$log);
            $this->assertStringContainsString('auth_rejected',$log);
            $this->assertStringContainsString('"exit_code":42',$log);
            $this->assertStringNotContainsString('private-secret',$log);
            $this->postJson('/desktop/live-studio/sessions/'.$session->id.'/heartbeat')->assertStatus(503)
                ->assertJsonPath('message',__('live-browser.browser_relay_failed'));
        } finally { @unlink($file); }
    }
    public function test_relay_failure_restores_the_event_state_that_existed_before_browser_start(): void
    {
        $record=$this->event(['public_published'=>false,'live_stream_enabled'=>false,'live_browser_enabled'=>false,'live_status'=>'draft']);
        $secret=(string)\Illuminate\Support\Str::uuid();
        $this->whipHandler=fn($request)=>Http::response('v=0',201,['Location'=>parse_url($request->url(),PHP_URL_PATH).'/'.$secret]);
        $started=$this->postJson('/desktop/live-studio/events/'.$record->id.'/browser',[
            'sdp'=>"v=0\nm=video\nm=audio",'confirm'=>true,
        ])->assertCreated()->json();
        $session=LiveBrowserSession::findOrFail($started['id']);
        $relay=new class extends BrowserLiveRelay {
            public int $attempts=0;
            protected function process(LiveBrowserSession $session): \Symfony\Component\Process\Process
            {
                $this->attempts++;
                return new \Symfony\Component\Process\Process([PHP_BINARY,'-r','exit(1);']);
            }
            protected function pauseBeforeRetry(int $attempt): void {}
        };
        $relay->run('browser-'.$session->id);
        $metadata=$record->fresh()->metadata;
        $this->assertSame('failed',$session->fresh()->status);
        $this->assertSame(3,$relay->attempts);
        $this->assertFalse($metadata['public_published']);
        $this->assertFalse($metadata['live_browser_enabled']);
        $this->assertFalse($metadata['live_stream_enabled']);
        $this->assertSame('draft',$metadata['live_status']);
        $this->assertArrayNotHasKey('live_browser_start_session_id',$metadata);
        $this->assertArrayNotHasKey('live_browser_start_previous',$metadata);
    }
    public function test_config_is_loopback_authenticated_and_preserves_obs_recording(): void
    {
        $config=json_decode(app(PublicBroadcast::class)->configuration(),true);
        $this->assertTrue($config['api']);$this->assertSame('127.0.0.1:9997',$config['apiAddress']);$this->assertSame([],$config['authHTTPExclude']);
        $this->assertSame('127.0.0.1:8889',$config['webrtcAddress']);$this->assertSame('127.0.0.1:8554',$config['rtspAddress']);
        $this->assertSame(['tcp'],$config['rtspTransports']);$this->assertSame(['stream.example.test'],$config['webrtcAdditionalHosts']);
        $this->assertTrue($config['pathDefaults']['record']);$this->assertFalse($config['paths']['~^browser-[a-f0-9-]{36}$']['record']);
        $this->assertFalse($config['paths']['~^browser-[a-f0-9-]{36}$']['runOnAvailable']==='');
        $this->assertStringNotContainsString(str_repeat('s',64),json_encode($config));
        if($binary=getenv('MEDIAMTX_VALIDATE_BIN')){
            Storage::disk('live-recordings')->put('browser-validation.yml',app(PublicBroadcast::class)->configuration());
            $process=new \Symfony\Component\Process\Process([$binary,'--validate-conf',Storage::disk('live-recordings')->path('browser-validation.yml')]);$process->run();
            $this->assertTrue($process->isSuccessful(),$process->getErrorOutput());
        }
    }
    public function test_control_auth_requires_secret_and_loopback_and_does_not_allow_publication(): void
    {
        $auth=['path'=>'','action'=>'api','user'=>'control','password'=>str_repeat('s',64),'ip'=>'127.0.0.1'];
        $this->postJson('/live/server-auth',$auth)->assertNoContent();
        $this->postJson('/live/server-auth',[...$auth,'ip'=>'8.8.8.8'])->assertUnauthorized();
        $this->postJson('/live/server-auth',[...$auth,'password'=>'wrong'])->assertUnauthorized();
        $this->postJson('/live/server-auth',[...$auth,'user'=>'publisher'])->assertUnauthorized();
        $this->postJson('/live/server-auth',[...$auth,'action'=>'publish','protocol'=>'rtmp','path'=>'live'])->assertUnauthorized();
    }
    public function test_browser_publish_token_is_scoped_to_owner_record_protocol_and_expiry(): void
    {
        $record=$this->event();$session=$this->browserSession($record);$token=app(BrowserBroadcast::class)->token($session);
        $auth=['path'=>'browser-'.$session->id,'action'=>'publish','protocol'=>'webrtc','user'=>'browser','password'=>$token];
        $this->postJson('/live/server-auth',$auth)->assertNoContent();
        $this->postJson('/live/server-auth',[...$auth,'path'=>'live-'.$record->id,'protocol'=>'rtsp'])->assertNoContent();
        $this->postJson('/live/server-auth',[...$auth,'path'=>'live-'.$record->id,'protocol'=>'rtmp'])->assertNoContent();
        $this->postJson('/live/server-auth',[...$auth,'path'=>'live-'.$this->event()->id,'protocol'=>'rtmp'])->assertUnauthorized();
        $this->postJson('/live/server-auth',[...$auth,'password'=>'wrong'])->assertUnauthorized();
        $this->postJson('/live/server-auth',[...$auth,'action'=>'read'])->assertUnauthorized();
        $this->owner->roles()->detach();$this->postJson('/live/server-auth',$auth)->assertUnauthorized();
        $this->owner->roles()->attach(Role::where('name','Owner')->firstOrFail());$session->update(['expires_at'=>now()->subSecond()]);
        $this->postJson('/live/server-auth',$auth)->assertUnauthorized();
    }
    public function test_whip_start_heartbeat_stop_and_no_secret_or_session_resource_leak(): void
    {
        $record=$this->event();$secret=(string)\Illuminate\Support\Str::uuid();
        $this->whipHandler=function($request)use($secret){
            if($request->method()==='DELETE')return Http::response('',200);
            return Http::response('v=0\r\nm=video\r\nm=audio',201,['Location'=>parse_url($request->url(),PHP_URL_PATH).'/'.$secret]);
        };
        $payload=['sdp'=>"v=0\r\nm=video 9 UDP/TLS/RTP/SAVPF 96\r\nm=audio 9 UDP/TLS/RTP/SAVPF 111\r\n",'confirm'=>true];
        $start=$this->postJson('/desktop/live-studio/events/'.$record->id.'/browser',$payload)->assertCreated()->json();
        $session=LiveBrowserSession::findOrFail($start['id']);$this->assertSame('connected',$session->status);
        $this->assertStringContainsString($secret,$session->upstream_location);
        $this->assertStringNotContainsString($secret,\Illuminate\Support\Facades\DB::table('live_browser_sessions')->where('id',$session->id)->value('upstream_location'));
        $this->assertArrayNotHasKey('upstream_location',$start);$this->assertStringNotContainsString(str_repeat('s',64),json_encode($start));
        $this->postJson('/desktop/live-studio/events/'.$record->id.'/browser',$payload)->assertConflict();
        $this->postJson('/desktop/live-studio/sessions/'.$session->id.'/heartbeat')->assertOk()->assertJsonPath('status','starting');
        $this->getJson('/desktop/live-studio/server')->assertOk()->assertJsonPath('session.id',$session->id)->assertJsonMissing(['upstream_location'=>$session->upstream_location]);
        $this->deleteJson('/desktop/live-studio/sessions/'.$session->id)->assertNoContent();$this->assertSame('ended',$session->fresh()->status);$this->assertNull($session->fresh()->upstream_location);
        $this->postJson('/desktop/live-studio/sessions/'.$session->id.'/heartbeat')->assertConflict();
        $this->deleteJson('/desktop/live-studio/sessions/'.$session->id)->assertNoContent();
    }
    public function test_rbac_confirmation_owner_and_invalid_event_are_enforced(): void
    {
        $record=$this->event();$record->update(['status'=>'needs_attention']);$payload=['sdp'=>"v=0\nm=video\nm=audio",'confirm'=>true];
        $this->postJson('/desktop/live-studio/events/'.$record->id.'/browser',$payload)->assertUnprocessable();
        $this->postJson('/desktop/live-studio/events/'.$record->id.'/browser',['sdp'=>$payload['sdp']])->assertUnprocessable();
        $session=$this->browserSession($this->event());$other=User::factory()->create();$other->roles()->attach(Role::where('name','Editor')->firstOrFail());$this->actingAs($other);
        $this->deleteJson('/desktop/live-studio/sessions/'.$session->id)->assertForbidden();$this->postJson('/desktop/live-studio/sessions/'.$session->id.'/heartbeat')->assertForbidden();
        $this->postJson('/desktop/live-studio/server',['confirm'=>true,'live_browser_enabled'=>true,'live_browser_host'=>'example.test'])->assertForbidden();
        $this->actingAs(User::factory()->create())->getJson('/desktop/live-studio/server')->assertForbidden();
    }
    public function test_browser_start_publishes_event_without_enabling_obs_or_a_calendar_entry(): void
    {
        $record=$this->event(['public_published'=>false,'live_stream_enabled'=>false,'live_browser_enabled'=>false]);
        $this->assertFalse(app(BrowserBroadcast::class)->eligible($record));
        $secret=(string)\Illuminate\Support\Str::uuid();
        $this->whipHandler=fn($request)=>Http::response('v=0',201,['Location'=>parse_url($request->url(),PHP_URL_PATH).'/'.$secret]);
        $start=$this->postJson('/desktop/live-studio/events/'.$record->id.'/browser',[
            'sdp'=>"v=0\nm=video\nm=audio",'confirm'=>true,
        ])->assertCreated();
        $start->assertJsonPath('status','starting');
        $this->assertNotEmpty($start->json('starts_at'));
        $metadata=$record->fresh()->metadata;
        $this->assertTrue($metadata['public_published']);$this->assertTrue($metadata['live_browser_enabled']);
        $this->assertTrue($metadata['live_stream_enabled']);$this->assertFalse($metadata['live_obs_enabled']);
        $this->assertSame('starting',$metadata['live_status']);
        $this->assertTrue(app(BrowserBroadcast::class)->eligible($record->fresh()));
        $this->assertSame($record->id,app(PublicBroadcast::class)->record('live-'.$record->id)?->id);
        $this->assertNull(app(PublicBroadcast::class)->record('live'));
        $this->assertNotEmpty($metadata['starts_at']);
        app(PublicBroadcast::class)->signal('live-'.$record->id,true);
        $metadata=$record->fresh()->metadata;
        $this->assertSame('live',$metadata['live_status']);
        $this->assertArrayNotHasKey('live_browser_start_session_id',$metadata);
        $this->assertArrayNotHasKey('live_browser_start_previous',$metadata);
        $this->getJson('/api/desktop/live')->assertOk()->assertJsonPath('data.0.id',$record->id);
        $this->patchJson('/api/desktop/live/'.$record->id,[
            'title'=>$record->title,'published'=>true,'enabled'=>false,
        ])->assertOk()->assertJsonPath('data.enabled',false)->assertJsonPath('data.browser_enabled',true);
        $this->assertTrue($record->fresh()->metadata['live_stream_enabled']);
    }
    public function test_active_obs_prevents_browser_start_and_unsafe_whip_location_is_rejected(): void
    {
        $record=$this->event();$payload=['sdp'=>"v=0\nm=video\nm=audio",'confirm'=>true];
        $this->pathItems=[['name'=>'live','ready'=>true]];
        $this->postJson('/desktop/live-studio/events/'.$record->id.'/browser',$payload)->assertConflict();
        $this->pathItems=[];$this->whipHandler=fn()=>Http::response('v=0',201,['Location'=>'https://external.example.test/secret']);
        $this->postJson('/desktop/live-studio/events/'.$record->id.'/browser',$payload)->assertStatus(503);
        $this->assertSame('failed',LiveBrowserSession::firstOrFail()->status);
    }
    public function test_failed_browser_start_restores_event_publication_and_mode_flags(): void
    {
        $record=$this->event(['public_published'=>false,'live_stream_enabled'=>false,'live_browser_enabled'=>false]);
        $this->postJson('/desktop/live-studio/events/'.$record->id.'/browser',[
            'sdp'=>"v=0\nm=video\nm=audio",'confirm'=>true,
        ])->assertStatus(503);
        $metadata=$record->fresh()->metadata;
        $this->assertFalse($metadata['public_published']);
        $this->assertFalse($metadata['live_browser_enabled']);
        $this->assertFalse($metadata['live_stream_enabled']);
        $this->assertNull($metadata['starts_at']??null);
        $this->assertArrayNotHasKey('live_status',$metadata);
        $this->assertArrayNotHasKey('live_browser_start_session_id',$metadata);
        $this->assertArrayNotHasKey('live_browser_start_previous',$metadata);
    }
    public function test_browser_start_keeps_a_bounded_ten_attempt_per_minute_limit(): void
    {
        $record=$this->event();$url='/desktop/live-studio/events/'.$record->id.'/browser';
        for($attempt=0;$attempt<10;$attempt++)$this->postJson($url,['sdp'=>'invalid','confirm'=>true])->assertUnprocessable();
        $this->postJson($url,['sdp'=>'invalid','confirm'=>true])->assertStatus(429);
    }
    public function test_obs_disconnect_uses_actual_tls_endpoint_and_disables_reconnect(): void
    {
        $record=$this->event();$record->update(['metadata'=>[...$record->metadata,'live_ingest_active'=>true]]);$id=(string)\Illuminate\Support\Str::uuid();
        $this->pathItems=[['name'=>'live','ready'=>true,'source'=>['type'=>'rtmpsConn','id'=>$id]]];
        $this->postJson('/desktop/live-studio/events/'.$record->id.'/disconnect',['confirm'=>true])->assertNoContent();
        $this->assertFalse($record->fresh()->metadata['live_obs_enabled']);
        $this->assertTrue($record->fresh()->metadata['live_browser_enabled']);
        $this->assertTrue($record->fresh()->metadata['live_stream_enabled']);
        Http::assertSent(fn($request)=>$request->url()==='http://127.0.0.1:9997/v3/rtmps/conns/kick/'.$id&&$request->method()==='POST');
    }
    public function test_server_settings_reject_command_injection_and_do_not_mutate_on_unsupported_host(): void
    {
        $this->postJson('/desktop/live-studio/server',['confirm'=>true,'live_browser_enabled'=>true,'live_browser_host'=>'example.test; touch /tmp/bad'])->assertUnprocessable();
        if(PHP_OS_FAMILY!=='Linux')$this->postJson('/desktop/live-studio/server',['confirm'=>true,'live_browser_enabled'=>false,'live_browser_host'=>'example.test'])->assertStatus(503);
        $this->assertTrue(app(Settings::class)->get('live_browser_enabled'));
        Http::assertNotSent(fn($request)=>str_contains($request->url(),'config'));
    }
    public function test_recorded_audio_creates_one_unpublished_podcast_with_original_usage(): void
    {
        Storage::fake('local');Storage::disk('local')->put('recorded.wav','RIFF test original');
        $audio=\App\Models\Media::create(['title'=>'Recording','original_name'=>'recorded.wav','kind'=>'audio','mime'=>'audio/wav','disk'=>'local','path'=>'recorded.wav','bytes'=>18,'sha256'=>hash('sha256','original'),'user_id'=>$this->owner->id,'status'=>'unsorted']);
        $record=$this->event();$url='/desktop/live-studio/events/'.$record->id.'/podcast';
        $draft=$this->postJson($url,['confirm'=>true,'media_id'=>$audio->id])->assertCreated()->json();
        $episode=SourceRecord::findOrFail($draft['id']);$this->assertSame('podcast',$episode->metadata['public_section']);$this->assertFalse($episode->metadata['public_published']);
        $this->assertTrue($audio->usages()->where('subject_id',(string)$episode->id)->exists());
        $episode->update(['body'=>'Owner edited text']);$this->postJson($url,['confirm'=>true,'media_id'=>$audio->id])->assertCreated()->assertJsonPath('id',$episode->id);$this->assertSame('Owner edited text',$episode->fresh()->body);
        Storage::disk('local')->assertExists('recorded.wav');$audio->update(['user_id'=>null]);$this->postJson($url,['confirm'=>true,'media_id'=>$audio->id])->assertUnprocessable();
    }
}
