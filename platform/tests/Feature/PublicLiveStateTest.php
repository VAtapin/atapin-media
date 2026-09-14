<?php
namespace Tests\Feature;
use App\Models\SourceRecord;
use App\Services\PublicLiveState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class PublicLiveStateTest extends TestCase
{
    use RefreshDatabase;
    private function event(): SourceRecord
    {
        return SourceRecord::create(['source'=>'website','source_id'=>'live','kind'=>'video','title'=>'Live','status'=>'ready','metadata'=>['public_published'=>true,'public_section'=>'live','live_status'=>'live']]);
    }
    public function test_presence_counts_distinct_recent_sessions_without_exposing_identifiers(): void
    {
        $event=$this->event();$service=app(PublicLiveState::class);
        $this->assertSame(1,$service->heartbeat($event,'one')['online']);$this->assertSame(1,$service->heartbeat($event,'one')['online']);
        $this->assertSame(2,$service->heartbeat($event,'two')['online']);$this->assertDatabaseMissing('public_live_presence',['session_hash'=>'one']);
        $this->travel(121)->seconds();$this->assertSame(1,$service->heartbeat($event,'two')['online']);
        $this->postJson(route('public.live-heartbeat',$event))->assertOk()->assertJsonStructure(['status','online','chat'])->assertJsonPath('status','live')->assertDontSee('session_hash');
        $event->update(['metadata'=>[...$event->metadata,'live_status'=>'ended']]);
        $this->assertSame('ended',$service->heartbeat($event,'two')['status']);
        $event->update(['metadata'=>['public_published'=>false,'public_section'=>'live']]);$this->postJson(route('public.live-heartbeat',$event))->assertNotFound();
    }
    public function test_chat_snapshot_updates_when_reviewed_messages_are_published_or_revoked(): void
    {
        $event=$this->event();$service=app(PublicLiveState::class);
        $chat=SourceRecord::create(['source'=>'website','source_id'=>'chat','kind'=>'live_chat','title'=>'Chat','body'=>'Reviewed message','status'=>'ready','metadata'=>['parent_source_id'=>'live','public_published'=>false,'author'=>'Author']]);
        $this->assertSame([],$service->heartbeat($event,'one')['chat']);
        $chat->update(['metadata'=>[...$chat->metadata,'public_published'=>true]]);$this->assertSame('Reviewed message',$service->heartbeat($event,'one')['chat'][0]['body']);
        $chat->update(['status'=>'needs_attention']);$this->assertSame([],$service->heartbeat($event,'one')['chat']);
        $this->assertSame(1,DB::table('public_live_presence')->count());
    }

    public function test_pending_message_is_returned_only_to_its_sender(): void
    {
        $event=$this->event();$sessionId='sender-session';
        SourceRecord::create(['source'=>'website','source_id'=>'pending-chat','kind'=>'live_chat','title'=>'Chat','body'=>'My pending message','status'=>'needs_attention','metadata'=>['parent_source_id'=>'live','website_comment'=>true,'public_published'=>false,'author_session_hash'=>hash('sha256',$sessionId),'moderation'=>['state'=>'pending_ai']]]);
        $this->assertSame('My pending message',app(PublicLiveState::class)->heartbeat($event,$sessionId)['chat'][0]['body']);
        $this->assertSame([] ,app(PublicLiveState::class)->heartbeat($event,'other-session')['chat']);
    }
}
