<?php
namespace Tests\Feature;
use App\Models\{SourceRecord,Media,User};
use App\Services\{PublicBroadcast,Settings};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Carbon;
use Tests\TestCase;
class PublicBroadcastTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media-canonical');
        config(['platform.media_upload_reserve_free_bytes' => 0]);
    }
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
        $this->actingAs($owner)->getJson('/api/desktop/live/'.$created['id'])->assertOk()->assertJsonPath('data.body','Updated body')->assertJsonPath('data.ingest.path','live')->assertJsonStructure(['data'=>['ingest']]);
        $second=$this->actingAs($owner)->postJson('/api/desktop/live',['title'=>'Second stream','enabled'=>true,'published'=>true])->assertCreated()->json('data');
        $this->assertSame($created['ingest']['url'],$second['ingest']['url']);
    }
    public function test_live_studio_marks_expired_scheduled_events_as_ended(): void
    {
        app(\App\Services\Access::class)->seed();
        $owner=User::factory()->create();$owner->roles()->attach(\App\Models\Role::where('name','Owner')->firstOrFail());
        Carbon::setTestNow(Carbon::parse('2026-09-13 22:07:00',config('app.timezone')));
        try {
            $missed=SourceRecord::create(['source'=>'website','source_id'=>'missed-live','kind'=>'video','title'=>'Missed','status'=>'ready','metadata'=>['public_section'=>'live','public_published'=>true,'live_status'=>'scheduled','starts_at'=>'2026-09-13T21:30:00']]);
            $future=SourceRecord::create(['source'=>'website','source_id'=>'future-live','kind'=>'video','title'=>'Future','status'=>'ready','metadata'=>['public_section'=>'live','public_published'=>true,'live_status'=>'scheduled','starts_at'=>'2026-09-14T20:00:00']]);
            $events=$this->actingAs($owner)->getJson('/api/desktop/live?date=2026-09-13')->assertOk()->json('data');
            $this->assertSame('ended',collect($events)->firstWhere('id',$missed->id)['status']);
            $futureEvents=$this->actingAs($owner)->getJson('/api/desktop/live?date=2026-09-14')->assertOk()->json('data');
            $this->assertSame('scheduled',collect($futureEvents)->firstWhere('id',$future->id)['status']);
            $this->assertSame('missed_schedule',$missed->fresh()->metadata['ended_reason']);
        } finally { Carbon::setTestNow(); }
    }
    public function test_browser_studio_lists_only_future_scheduled_livestreams_for_today(): void
    {
        app(\App\Services\Access::class)->seed();
        $owner=User::factory()->create();$owner->roles()->attach(\App\Models\Role::where('name','Owner')->firstOrFail());
        Carbon::setTestNow(Carbon::parse('2026-09-16 14:00:00',config('app.timezone')));
        try {
            $today=$this->actingAs($owner)->postJson('/api/desktop/live',['title'=>'Today evening','starts_at'=>'2026-09-16T20:00'])->assertCreated()->json('data');
            $this->postJson('/api/desktop/live',['title'=>'Already missed','starts_at'=>'2026-09-16T12:00'])->assertCreated();
            $this->postJson('/api/desktop/live',['title'=>'Tomorrow','starts_at'=>'2026-09-17T12:00'])->assertCreated();
            $response=$this->getJson('/api/desktop/live?filter=browser_today&date=2026-09-17')->assertOk()
                ->assertJsonPath('filter.mode','browser_today')->assertJsonPath('filter.date','2026-09-16')
                ->assertJsonPath('pagination.total',1);
            $this->assertSame($today['id'],$response->json('data.0.id'));
        } finally { Carbon::setTestNow(); }
    }
    public function test_live_studio_defaults_to_today_with_pagination_and_keeps_live_events_separate(): void
    {
        app(\App\Services\Access::class)->seed();
        $owner=User::factory()->create();$owner->roles()->attach(\App\Models\Role::where('name','Owner')->firstOrFail());
        Carbon::setTestNow(Carbon::parse('2026-09-14 10:00:00',config('app.timezone')));
        try {
            for($i=1;$i<=21;$i++) SourceRecord::create(['source'=>'website','source_id'=>"today-$i",'kind'=>'video','title'=>"Today $i",'status'=>'ready','metadata'=>['public_section'=>'live','public_published'=>true,'live_stream_enabled'=>true,'live_status'=>$i===1?'ended':'scheduled','starts_at'=>sprintf('2026-09-14T%02d:00',$i%10)]]);
            $live=SourceRecord::create(['source'=>'website','source_id'=>'now','kind'=>'video','title'=>'Current live','status'=>'ready','metadata'=>['public_section'=>'live','public_published'=>true,'live_stream_enabled'=>true,'live_status'=>'live','starts_at'=>'2026-09-15T10:00']]);
            SourceRecord::create(['source'=>'website','source_id'=>'tomorrow','kind'=>'video','title'=>'Tomorrow','status'=>'ready','metadata'=>['public_section'=>'live','public_published'=>true,'live_stream_enabled'=>true,'live_status'=>'scheduled','starts_at'=>'2026-09-15T12:00']]);

            $response=$this->actingAs($owner)->getJson('/api/desktop/live')->assertOk();
            $response->assertJsonPath('filter.mode','day')->assertJsonPath('filter.date','2026-09-14')->assertJsonPath('pagination.total',21)->assertJsonPath('pagination.last_page',2);
            $this->assertCount(20,$response->json('data'));$this->assertSame($live->id,$response->json('live.0.id'));
            $response=$this->actingAs($owner)->getJson('/api/desktop/live?page=2')->assertOk();
            $this->assertCount(1,$response->json('data'));
            $response=$this->actingAs($owner)->getJson('/api/desktop/live?filter=scheduled')->assertOk();
            $this->assertCount(1,$response->json('data'));$this->assertSame('Tomorrow',$response->json('data.0.title'));
        } finally { Carbon::setTestNow(); }
    }
    public function test_live_studio_cover_is_linked_to_the_event_and_returned_for_the_editor(): void
    {
        app(\App\Services\Access::class)->seed();
        Storage::fake('local');Storage::disk('local')->put('posters/live.png','poster');
        $owner=User::factory()->create();$owner->roles()->attach(\App\Models\Role::where('name','Owner')->firstOrFail());
        $poster=Media::create(['source'=>'upload','source_id'=>'poster-upload','title'=>'Live poster','original_name'=>'live.png','disk'=>'local','path'=>'posters/live.png','kind'=>'image','mime'=>'image/png','bytes'=>6,'status'=>'unsorted']);
        $created=$this->actingAs($owner)->postJson('/api/desktop/live',['title'=>'Poster stream','published'=>true,'cover_media_id'=>$poster->id])->assertCreated()->json('data');
        $event=SourceRecord::findOrFail($created['id']);
        $this->assertSame($poster->id,$event->metadata['cover_media_id']);
        $this->assertContains($poster->id,$event->metadata['media_ids']);
        $this->assertSame($poster->id,$created['cover_media_id']);
        $this->assertSame(route('media.preview',$poster),$created['cover_preview_url']);
    }
    public function test_live_studio_can_upload_and_attach_a_poster_through_the_resumable_flow(): void
    {
        app(\App\Services\Access::class)->seed();Storage::fake('local');
        $owner=User::factory()->create();$owner->roles()->attach(\App\Models\Role::where('name','Owner')->firstOrFail());
        $bytes=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jvyoAAAAASUVORK5CYII=');
        $start=$this->actingAs($owner)->postJson('/desktop/media/uploads',['request_key'=>(string)\Illuminate\Support\Str::uuid(),'name'=>'livestream-poster.png','size'=>strlen($bytes),'profile'=>'poster'])->assertOk();
        $uploadId=$start->json('id');
        $this->call('POST',"/desktop/media/uploads/$uploadId/chunk",[],[],[],['CONTENT_TYPE'=>'application/octet-stream','HTTP_X_UPLOAD_OFFSET'=>'0','HTTP_X_CHUNK_SHA256'=>hash('sha256',$bytes)],$bytes)->assertOk();
        $mediaId=$this->postJson("/desktop/media/uploads/$uploadId/finish")->assertOk()->json('media_id');
        $created=$this->postJson('/api/desktop/live',['title'=>'Poster upload','published'=>true])->assertCreated()->json('data');
        $updated=$this->patchJson('/api/desktop/live/'.$created['id'],['title'=>$created['title'],'body'=>$created['body'],'starts_at'=>$created['starts_at'],'published'=>true,'enabled'=>false,'cover_media_id'=>$mediaId])->assertOk()->json('data');
        $this->assertSame($mediaId,$updated['cover_media_id']);$this->assertSame($mediaId,\App\Models\SourceRecord::findOrFail($created['id'])->metadata['cover_media_id']);
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
    public function test_shared_ingest_selects_an_enabled_event_without_creating_event_specific_keys(): void
    {
        $first=$this->event();$first->update(['metadata'=>[...$first->metadata,'live_status'=>'live']]);$second=SourceRecord::create(['source'=>'website','source_id'=>'live-second','kind'=>'video','title'=>'Second','status'=>'ready','metadata'=>['public_section'=>'live','public_published'=>true,'live_stream_enabled'=>true,'live_status'=>'scheduled','starts_at'=>'2027-01-01T12:00:00']]);
        app(Settings::class)->updateSecrets(['live_publish_shared'=>'shared-secret']);
        $publish=['path'=>'live','action'=>'publish','protocol'=>'rtmp','user'=>'publisher','password'=>'shared-secret'];
        $this->postJson('/live/server-auth',$publish)->assertNoContent();
        $this->assertTrue((bool)($first->fresh()->metadata['live_ingest_active']??false));
        $this->postJson('/live/server-auth',['path'=>'live','action'=>'read','protocol'=>'hls'])->assertNoContent();
        app(PublicBroadcast::class)->signal('live',false);
        $this->assertSame('ended',$first->fresh()->metadata['live_status']);
        $this->assertArrayNotHasKey('live_ingest_active',$first->fresh()->metadata);
        $this->postJson('/live/server-auth',['path'=>'live','action'=>'read','protocol'=>'hls'])->assertNoContent();
        $first->update(['metadata'=>[...$first->fresh()->metadata,'public_published'=>false]]);
        $this->postJson('/live/server-auth',['path'=>'live','action'=>'read','protocol'=>'hls'])->assertUnauthorized();
        $this->assertFalse(app(Settings::class)->hasSecret('live_publish_'.$first->id));
        $this->assertFalse(app(Settings::class)->hasSecret('live_publish_'.$second->id));
    }
    public function test_shared_recording_is_linked_after_the_end_signal(): void
    {
        Storage::fake('live-recordings');$event=$this->event();$event->update(['metadata'=>[...$event->metadata,'live_status'=>'live','live_ingest_active'=>true]]);
        $broadcast=app(PublicBroadcast::class);$broadcast->signal('live',false);
        $path='live/segment.mp4';Storage::disk('live-recordings')->put($path,'completed recording');$file=Storage::disk('live-recordings')->path($path);
        $broadcast->recording('live',$file);
        $this->assertContains(Media::firstOrFail()->id,$event->fresh()->metadata['media_ids']);
    }
    public function test_restarting_a_ready_hook_does_not_reopen_an_ended_event(): void
    {
        $event = $this->event();
        $broadcast = app(PublicBroadcast::class);
        $broadcast->signal('live-'.$event->id, false);
        $broadcast->signal('live-'.$event->id, true);
        $this->assertSame('ended', $event->fresh()->metadata['live_status']);
    }
    public function test_completed_recordings_are_moved_to_public_media_once_and_linked(): void
    {
        Storage::fake('live-recordings');Queue::fake();$event=$this->event();$path='live-'.$event->id;
        Storage::disk('live-recordings')->put($path.'/segment.mp4','completed recording');$file=Storage::disk('live-recordings')->path($path.'/segment.mp4');
        $broadcast=app(PublicBroadcast::class);$broadcast->recording($path,$file);$broadcast->recording($path,$file);Queue::assertPushed(\App\Jobs\ProbeMedia::class);
        $this->assertSame(1,Media::count());$this->assertSame([Media::first()->id],$event->fresh()->metadata['media_ids']);$this->assertFileDoesNotExist($file);
        $this->assertSame('media-canonical',Media::first()->disk);
        $this->assertFileExists(Storage::disk('media-canonical')->path(Media::first()->path));
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
        $this->assertArrayHasKey('~^live$',$config['paths']);
        $this->assertStringEndsWith(' ready', $config['pathDefaults']['runOnAvailable']);
        $this->assertTrue($config['pathDefaults']['runOnAvailableRestart']);
        $this->assertStringEndsWith(' ended', $config['pathDefaults']['runOnUnavailable']);
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
            $event=$this->event();app(Settings::class)->updateSecrets(['live_publish_shared'=>'secret']);$ingest=$broadcast->ingest($event,app(Settings::class));
            $this->assertTrue($ingest['configured']);
            $this->assertSame('rtmps://mannavomhimmel.de:1936/live?user=publisher&pass=secret',$ingest['url']);
        } finally {
            @unlink($cert);@unlink($key);
        }
    }
}
