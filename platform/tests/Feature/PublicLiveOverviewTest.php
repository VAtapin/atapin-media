<?php
namespace Tests\Feature;

use App\Models\{Media,SourceRecord};
use App\Services\PublicBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicLiveOverviewTest extends TestCase
{
    use RefreshDatabase;

    private function event(string $status, bool $published=true): SourceRecord
    {
        return SourceRecord::create(['source'=>'website','source_id'=>uniqid('live-'),'kind'=>'video','title'=>'Current broadcast','status'=>'ready',
            'metadata'=>['public_section'=>'live','public_published'=>$published,'live_stream_enabled'=>true,'live_status'=>$status,'starts_at'=>now()->addHour()->toIso8601String()]]);
    }

    public function test_live_overview_embeds_current_stream_and_homepage_labels_it(): void
    {
        $scheduled=$this->event('scheduled');$live=$this->event('live');
        $this->get('/live')->assertOk()->assertViewHas('record',fn($record)=>$record->id===$live->id)
            ->assertSee('data-live-player',false)->assertSee('/_live/live/?cookieCheck=1',false)->assertSee('public-live-overview-caption',false);
        $this->get('/')->assertOk()->assertViewHas('live',fn($card)=>$card['id']===$live->id&&$card['live_status']==='live')->assertSee(__('public.live_now'));
        $this->get('/live?event='.$scheduled->id)->assertOk()->assertDontSee('data-live-player',false);
    }
    public function test_live_draft_status_uses_translated_label_on_detail_poster_and_summary(): void
    {
        $draft=$this->event('draft');
        $this->get('/live?event='.$draft->id)->assertOk()->assertSee(__('public.live_draft'))
            ->assertDontSee('public.live_draft');
    }

    public function test_current_status_is_public_only_and_tracks_ingest_hooks_without_presence_writes(): void
    {
        $private=$this->event('live',false);$event=$this->event('scheduled');
        $this->getJson('/live/current')->assertOk()->assertJson(['live'=>false])->assertHeader('Cache-Control','no-store, private');
        app(PublicBroadcast::class)->signal('live-'.$event->id,true);
        $this->getJson('/live/current')->assertOk()->assertExactJson(['live'=>true]);
        app(PublicBroadcast::class)->signal('live-'.$event->id,false);
        $this->getJson('/live/current')->assertOk()->assertExactJson(['live'=>false]);
        $this->assertDatabaseCount('public_live_presence',0);
        $this->get('/live')->assertOk()->assertDontSee('data-live-player',false);
    }

    public function test_recorded_live_uses_direct_original_url_and_does_not_launch_any_processing(): void
    {
        Storage::fake('media-canonical');Storage::disk('media-canonical')->put(str_repeat('a',64).'.mp4','recording');
        $media=Media::create(['source'=>'live','source_id'=>'segment','title'=>'Recording','original_name'=>'recording.mp4','disk'=>'media-canonical',
            'path'=>str_repeat('a',64).'.mp4','kind'=>'video','mime'=>'video/mp4','bytes'=>9,'status'=>'unsorted']);
        $record=$this->event('ended');$record->update(['metadata'=>[...$record->metadata,'media_ids'=>[$media->id]]]);
        $this->get('/live?event='.$record->id)->assertOk()->assertSee('src="'.$media->publicUrl().'"',false)
            ->assertSee('preload="none"',false)->assertDontSee('data-live-player',false);
        $this->assertDatabaseCount('jobs',0);
    }
}
