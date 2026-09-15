<?php
namespace Tests\Feature;

use App\Models\{SourceRecord,Product,Media,PublicContentState,PublicContentView,User};
use App\Services\{PublicContent,PublicBooks,PublicParticipation};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;
    private function record(string $kind='video',array $metadata=[]): SourceRecord
    {
        return SourceRecord::create(['source'=>'website','source_id'=>(string)\Illuminate\Support\Str::uuid(),'kind'=>$kind,'title'=>'Database '.$kind,'body'=>'Real database body','status'=>'ready','metadata'=>['public_published'=>true,...$metadata]]);
    }
    public function test_all_ten_screens_render_with_explicit_empty_states(): void
    {
        foreach(['/','/videos','/videos/vorschau','/beitraege','/beitraege/vorschau','/buecher','/buecher/vorschau','/live','/podcast','/community'] as $url){
            $this->get($url)->assertOk()->assertSee(__('public.no_data'))->assertDontSee('public.heading_')->assertDontSee('public.filters_');
        }
    }
    public function test_public_header_shell_is_shared_without_empty_book_announcement(): void
    {
        $this->get('/')->assertOk()->assertSee('public-section-start',false);
        $this->get('/live')->assertOk()->assertSee('public-section-live',false);
        $this->get('/buecher')->assertOk()->assertDontSee('public-announcement',false);
    }
    public function test_overview_and_detail_pages_use_the_two_shared_header_modes(): void
    {
        $this->get('/')->assertOk()->assertSee('public-header-overview',false)->assertSee('public-overview-hero',false);
        $this->get('/live')->assertOk()->assertSee('public-header-overview',false)->assertSee('public-overview-hero-live',false)->assertDontSee('public-live-top',false)->assertDontSee('public-live-chat',false);
        $this->get('/ueber-uns')->assertOk()->assertSee('public-header-overview',false)->assertSee('public-overview-hero-ueber-uns',false)->assertSee('Über uns')->assertDontSee('public.heading_',false);
        $this->get('/videos/vorschau')->assertOk()->assertSee('public-header-detail',false)->assertDontSee('public-overview-hero',false);

        $live=$this->record('video',['public_section'=>'live','live_status'=>'ended']);
        $this->get('/live?event='.$live->id)->assertOk()->assertSee('public-header-detail',false)->assertDontSee('public-overview-hero',false);
    }
    public function test_live_success_feedback_is_placed_in_chat_and_marked_for_auto_dismissal(): void
    {
        $live=$this->record('video',['public_section'=>'live','live_status'=>'ended']);
        $response=$this->withSession(['public_status'=>'Nachricht gesendet.'])->get('/live?event='.$live->id);
        $response->assertOk()->assertSee('public-chat-feedback',false)->assertSee('data-dismiss-feedback',false);
        $this->assertSame(1,substr_count($response->getContent(),'data-auto-dismiss'));
    }
    public function test_section_assignment_search_and_tags_use_published_database_records(): void
    {
        $podcast=$this->record('post',['public_section'=>'podcast','tags'=>['Gebet'],'author'=>'Public speaker']);
        $live=$this->record('video',['public_section'=>'live','live_status'=>'scheduled','starts_at'=>'2027-01-01T12:00:00']);
        $post=$this->record('post',['public_section'=>'community']);
        $this->get('/podcast')->assertOk()->assertSee('Public speaker');
        $this->get('/podcast?tag=unmatched')->assertOk()->assertViewHas('items',fn($items)=>$items->total()===0);
        $this->get('/videos')->assertOk()->assertViewHas('items',fn($items)=>$items->total()===0);
        $this->get('/beitraege')->assertOk()->assertDontSee('Real database body');
        $this->get('/live?event='.$live->id)->assertOk()->assertSee('Database video');
        $this->get('/community?discussion='.$post->id)->assertOk()->assertSee('Real database body');
        $this->get('/podcast?episode='.$live->id)->assertNotFound();
        $this->get('/suche?q=Database')->assertOk()->assertSee('Database post');
        $this->get('/podcast?sort=bad')->assertRedirect();
    }
    public function test_homepage_feature_prefers_the_newest_selected_video(): void
    {
        $selected=[$this->record('video',['public_homepage'=>true]),$this->record('short',['public_homepage'=>true])];
        $this->record('video',['public_homepage'=>false]);
        $this->record('video',['public_homepage'=>true,'public_published'=>false]);
        $this->get('/')->assertViewHas('featured',fn($card)=>$card['id']===$selected[1]->id);
    }
    public function test_homepage_feature_falls_back_to_the_newest_published_video_and_ignores_livestreams(): void
    {
        $video=$this->record('video');
        $live=$this->record('video',['public_section'=>'live','live_status'=>'ended']);
        $this->get('/')->assertViewHas('featured',fn($card)=>$card['id']===$video->id&&$card['id']!==$live->id);
    }
    public function test_homepage_feature_is_empty_when_only_a_livestream_exists(): void
    {
        $this->record('video',['public_section'=>'live','live_status'=>'ended']);
        $this->get('/')->assertViewHas('featured',null);
    }
    public function test_live_player_starts_mediamtx_cookie_check_inside_proxy_prefix(): void
    {
        $live=$this->record('video',['public_section'=>'live','live_stream_enabled'=>true,'live_status'=>'live']);
        $response=$this->get('/live?event='.$live->id)->assertOk()->assertSee('data-src="/_live/live/?cookieCheck=1"',false)->assertSee('/_live/live/?cookieCheck=1',false)->assertSee('data-live-status-line',false)->assertSee('Real database body')->assertSee('name="body"',false)->assertSee('data-public-ajax="message"',false);
        $this->assertStringNotContainsString('<iframe class="public-main-player" src="/_live/live/?cookieCheck=1"',$response->getContent());
        $dom=new \DOMDocument();@$dom->loadHTML($response->getContent());$this->assertStringNotContainsString('Der Livestream wird vorbereitet',$dom->getElementsByTagName('main')->item(0)->textContent);
        $response->assertDontSee('data-community-action',false);
    }
    public function test_scheduled_live_player_shows_event_details_and_cover_instead_of_technical_fallback(): void
    {
        Storage::fake('local');Storage::disk('local')->put('posters/next.jpg','poster');
        $poster=Media::create(['source'=>'upload','source_id'=>'next-poster','title'=>'Next poster','original_name'=>'next.jpg','disk'=>'local','path'=>'posters/next.jpg','kind'=>'image','mime'=>'image/jpeg','bytes'=>6,'status'=>'ready']);
        $live=$this->record('video',['public_section'=>'live','live_stream_enabled'=>true,'live_status'=>'scheduled','starts_at'=>'2026-09-20T18:30:00','cover_media_id'=>$poster->id,'media_ids'=>[$poster->id]]);
        $response=$this->get('/live?event='.$live->id)->assertOk()->assertSee('Database video')->assertSee('20.09.2026 18:30')->assertSee(route('public.media',[$live,$poster]),false)->assertSee('public-live-reminder-button',false)->assertSee('public-live-push-button',false);
        $dom=new \DOMDocument();@$dom->loadHTML($response->getContent());$visible=$dom->getElementsByTagName('main')->item(0)->textContent;
        foreach([__('public.reminder_unavailable'),__('public.push_hint'),'Bitte später erneut versuchen'] as $label)$this->assertStringNotContainsString($label,$visible);
    }
    public function test_ended_live_uses_the_branded_fallback_instead_of_an_empty_video_player(): void
    {
        $live=$this->record('video',['public_section'=>'live','live_stream_enabled'=>true,'live_status'=>'ended']);
        $this->get('/live?event='.$live->id)->assertOk()->assertSee('public-live-player-fallback-static',false)->assertSee('/assets/brand/owner/desktop/wallpapers/manna-mountains.png',false)->assertDontSee('<video',false);
    }
    public function test_scheduled_live_events_are_not_listed_as_recordings(): void
    {
        $scheduled=$this->record('video',['public_section'=>'live','live_status'=>'scheduled','starts_at'=>'2027-01-01T12:00:00']);
        $ended=$this->record('video',['public_section'=>'live','live_status'=>'ended']);
        $this->get('/live?event='.$scheduled->id)->assertOk()->assertViewHas('popular',fn($items)=>!$items->contains('id',$scheduled->id)&&$items->contains('id',$ended->id));
    }
    public function test_expired_scheduled_lives_are_marked_ended_and_removed_from_upcoming(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-13 22:07:00',config('app.timezone')));
        try {
            $missed=$this->record('video',['public_section'=>'live','live_status'=>'scheduled','starts_at'=>'2026-09-13T21:30:00']);
            $future=$this->record('video',['public_section'=>'live','live_status'=>'scheduled','starts_at'=>'2026-09-14T20:00:00']);
            $this->get('/live')->assertOk()->assertViewHas('upcoming',fn($items)=>!$items->contains('id',$missed->id)&&$items->contains('id',$future->id));
            $this->assertSame('ended',$missed->fresh()->metadata['live_status']);
            $this->assertSame('missed_schedule',$missed->fresh()->metadata['ended_reason']);
            $this->assertSame('scheduled',$future->fresh()->metadata['live_status']);
        } finally { Carbon::setTestNow(); }
    }
    public function test_guest_views_deduplicate_a_persistent_session_and_count_other_sessions(): void
    {
        $record=$this->record();$counter=app(\App\Services\PublicViewCounter::class);
        $request=\Illuminate\Http\Request::create('/','POST');
        $session=new \Illuminate\Session\Store('test',new \Illuminate\Session\ArraySessionHandler(120));
        $session->start();$request->setLaravelSession($session);
        $this->assertSame(1,$counter->record($record,$request));
        $this->assertSame(1,$counter->record($record,$request));
        $session->migrate();$this->assertSame(2,$counter->record($record,$request));
    }

    public function test_video_views_are_counted_once_and_popularity_uses_real_views(): void
    {
        $oldMetadata=$this->record('video',['views'=>999]);
        Storage::fake('local');Storage::disk('local')->put('originals/views.mp4',pack('N',24).'ftypisom'.str_repeat("\0",12));
        $media=Media::create(['source'=>'upload','source_id'=>'views-file','title'=>'Video','original_name'=>'views.mp4','kind'=>'video','mime'=>'video/mp4','bytes'=>24,'disk'=>'local','path'=>'originals/views.mp4','status'=>'ready']);
        $oldMetadata->update(['metadata'=>[...$oldMetadata->metadata,'media_ids'=>[$media->id]]]);
        $popular=$this->record('video',['views'=>1]);
        foreach(['visitor-a','visitor-b','visitor-c'] as $hash)PublicContentView::create(['record_id'=>$popular->id,'visitor_hash'=>hash('sha256',$hash),'viewed_on'=>now()->toDateString()]);
        $this->get('/videos?sort=popular')->assertOk()->assertViewHas('popular',fn($items)=>$items->first()['id']===$popular->id&&$items->first()['views']===3);
        $url=route('public.record-view',$oldMetadata);
        // Laravel's array session driver does not persist browser cookies between test requests.
        $this->actingAs(User::factory()->create());
        $this->postJson($url)->assertOk()->assertJsonPath('views',1);
        $this->postJson($url)->assertOk()->assertJsonPath('views',1);
        $this->assertDatabaseCount('public_content_views',4);
        $this->get(route('public.video',['slug'=>'database-video-'.$oldMetadata->id]))->assertOk()->assertSee('data-view-url="'.e($url).'"',false);
    }
    public function test_active_books_render_and_drafts_do_not(): void
    {
        $book=Product::create(['title'=>'Database book','description'=>'Book description','price_cents'=>990,'currency'=>'EUR','status'=>'active']);
        Product::create(['title'=>'Secret draft','status'=>'draft']);
        $this->get('/buecher')->assertOk()->assertSee('Database book')->assertDontSee('Secret draft');
        $this->get(app(PublicBooks::class)->card($book)['url'])->assertOk()->assertSee('Book description')->assertSee('9,90 EUR');
        $this->get('/kontakt?book='.$book->id)->assertOk();
        $book->update(['status'=>'archived']);$this->get('/buecher/database-book-'.$book->id)->assertNotFound();
    }
    public function test_paid_book_downloads_are_not_exposed_but_explicit_samples_are(): void
    {
        Storage::fake('local');Storage::disk('local')->put('originals/sample.pdf','%PDF-1.4');
        $book=Product::create(['title'=>'Paid book','price_cents'=>990,'currency'=>'EUR','status'=>'active']);
        $media=Media::create(['title'=>'Sample','original_name'=>'sample.pdf','kind'=>'document','mime'=>'application/pdf','bytes'=>8,'disk'=>'local','path'=>'originals/sample.pdf','source'=>'upload','source_id'=>'pdf']);
        $usage=$media->usages()->create(['subject_type'=>Product::class,'subject_id'=>(string)$book->id,'used_as'=>'public_download']);
        $url=route('public.book-media',[$book,$media]);$this->get($url)->assertNotFound();
        $usage->update(['used_as'=>'sample']);$this->get($url)->assertOk();
        $book->update(['status'=>'draft']);$this->get($url)->assertNotFound();
    }
    public function test_account_actions_are_authenticated_and_persist_without_duplicate_votes(): void
    {
        $record=$this->record();$url=route('public.record-state',$record);
        $this->postJson($url,['action'=>'bookmark'])->assertUnauthorized();
        $user=User::factory()->create();$this->actingAs($user)->postJson($url,['action'=>'bookmark','enabled'=>true])->assertOk()->assertJson(['kind'=>'state','enabled'=>true]);
        $this->assertDatabaseHas('public_content_states',['user_id'=>$user->id,'subject_id'=>$record->id,'action'=>'bookmark']);
        $this->postJson($url,['action'=>'reminder'])->assertStatus(422);
        $poll=$this->record('poll',['poll'=>['options'=>[['text'=>'One'],['text'=>'Two']]]]);
        $voteUrl=route('public.record-state',$poll);
        $this->postJson($voteUrl,['action'=>'vote','option'=>0])->assertOk();
        $this->postJson($voteUrl,['action'=>'vote','option'=>1])->assertOk();
        $this->assertSame(1,PublicContentState::where('action','vote')->count());
        $this->postJson($voteUrl,['action'=>'vote','option'=>2])->assertStatus(422);
        $record->update(['metadata'=>['public_published'=>false]]);$this->postJson($url,['action'=>'bookmark'])->assertNotFound();
    }
    public function test_poll_card_has_readable_options_and_inline_feedback(): void
    {
        $poll=$this->record('poll',['public_section'=>'community','poll'=>['active'=>true,'audience'=>'registered','options'=>[['text'=>'Erste ausführliche Antwort'],['text'=>'Zweite Antwort']]]]);
        $response=$this->actingAs(User::factory()->create())->get('/community')->assertOk();
        $response->assertSee('public-poll-options',false)->assertSee('public-poll-option-copy',false)->assertSee('data-public-poll',false)->assertSee('data-public-form-message',false)->assertSee($poll->title);
    }
    public function test_comments_and_chat_are_saved_for_review_and_private_children_stay_hidden(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $record=$this->record('video',['public_section'=>'live']);$user=User::factory()->create();
        $this->actingAs($user)->postJson(route('public.record-state',$record),['action'=>'chat','body'=>'New chat message'])->assertOk();
        $chat=SourceRecord::where('kind','live_chat')->firstOrFail();$this->assertSame('needs_attention',$chat->status);
        $this->assertFalse($chat->metadata['public_published']);
        $this->get('/live?event='.$record->id)->assertOk()->assertSee('New chat message')->assertSee(__('public.chat_moderation_pending'));
        $chat->update(['status'=>'ready','metadata'=>[...$chat->metadata,'public_published'=>true]]);
        $this->get('/live?event='.$record->id)->assertSee('New chat message');
        $this->assertStringContainsString('#chat',app(PublicContent::class)->card($chat)['url']);
        $record->update(['metadata'=>['public_published'=>false]]);$this->assertFalse(app(PublicContent::class)->visible($chat));
    }
    public function test_book_progress_is_shown_from_the_database_for_the_signed_in_reader(): void
    {
        $book=Product::create(['title'=>'Read database book','currency'=>'EUR','status'=>'active']);$user=User::factory()->create();
        $this->actingAs($user)->postJson(route('public.book-state',$book),['action'=>'progress','position'=>75])->assertOk();
        $this->postJson(route('public.book-state',$book),['action'=>'progress','position'=>101])->assertStatus(422);
        $this->get('/buecher')->assertOk()->assertSee('75%');
        $this->get('/buecher/read-database-book-'.$book->id)->assertOk()->assertSee('75%');
    }
}
