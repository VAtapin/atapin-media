<?php
namespace Tests\Feature;
use App\Models\{SourceRecord,User,Role};
use App\Services\{Access,Settings,ContentShortDescriptions,PublicContent};
use App\Jobs\{GenerateShortDescriptions,ModeratePublicContent};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Queue,Storage};
use Tests\TestCase;

class ContentEnhancementsTest extends TestCase
{
    use RefreshDatabase;
    private function record(array $meta=[]): SourceRecord {return SourceRecord::create(['source'=>'youtube','source_id'=>uniqid(),'kind'=>'video','title'=>'Vertrauen auf Gott in schwierigen Zeiten','body'=>"Vertrauen und Hoffnung durch Gottes Wort.\nMein Buch Band 3 https://example.com\nPaypal Spenden\n#Werbung",'status'=>'ready','metadata'=>$meta]);}
    private function owner(): User {app(Access::class)->seed();$user=User::factory()->create();$user->roles()->attach(Role::where('name','Owner')->firstOrFail());return $user;}
    private function ai(): void {app(Settings::class)->update(['ai_enabled'=>true,'ai_provider'=>'openai','ai_model'=>'test-model']);app(Settings::class)->updateSecrets(['ai_api_key'=>'test-only']);}
    private function response(array $results): array{return ['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode(['results'=>$results])]]]]];}
    public function test_batch_uses_one_request_maps_ids_and_keeps_originals(): void
    {
        Queue::fake();$this->ai();$a=$this->record();$b=$this->record();$service=app(ContentShortDescriptions::class);
        $text='Gottes Wort schenkt Hoffnung und Vertrauen in schwierigen Zeiten';
        Http::fake(['api.openai.com/*'=>Http::response($this->response([
            ['id'=>(string)$b->id,'short_description'=>$text,'insufficient_information'=>false],['id'=>(string)$a->id,'short_description'=>$text,'insufficient_information'=>false]]))]);
        $body=$a->body;$this->actingAs($this->owner())->postJson('/desktop/content/short-descriptions',['ids'=>[$a->id,$b->id]])->assertOk()->assertJsonPath('requests',1)->assertJsonPath('count',2);
        $job=Queue::pushed(GenerateShortDescriptions::class)->first();$job->handle($service);
        Http::assertSentCount(1);Http::assertSent(fn($r)=>$r['store']===false&&!str_contains($r['input'],'Paypal')&&!str_contains($r['input'],'https://'));
        $this->assertSame($text,$a->fresh()->metadata['short_description']);$this->assertSame($text,$b->fresh()->metadata['short_description']);$this->assertSame($body,$a->fresh()->body);
    }
    public function test_groups_limit_count_and_utf8_byte_budget(): void
    {
        $records=[];for($i=0;$i<205;$i++){$r=new SourceRecord(['id'=>$i+1,'title'=>'Topic','body'=>'','metadata'=>[]]);$records[]=$r;}
        $service=app(ContentShortDescriptions::class);$this->assertSame([100,100,5],array_map('count',$service->groups($records)));
        foreach($records as $r)$r->body=str_repeat('Thema ',700);$groups=$service->groups($records);$this->assertGreaterThan(3,count($groups));
    }
    public function test_manual_text_and_edits_during_provider_request_are_not_overwritten(): void
    {
        Queue::fake();$this->ai();$manual=$this->record(['short_description'=>'Mein eigener Text','short_description_origin'=>'manual']);$a=$this->record();
        $this->actingAs($this->owner())->postJson('/desktop/content/short-descriptions',['ids'=>[$manual->id,$a->id]])->assertOk()->assertJsonPath('count',1);
        Http::fake(function()use($a){$a->update(['title'=>'Neu bearbeitet']);return Http::response($this->response([['id'=>(string)$a->id,'short_description'=>'Gottes Wort schenkt Hoffnung und Vertrauen in schwierigen Zeiten','insufficient_information'=>false]]));});
        Queue::pushed(GenerateShortDescriptions::class)->first()->handle(app(ContentShortDescriptions::class));
        $this->assertArrayNotHasKey('short_description',$a->fresh()->metadata);$this->assertSame('Mein eigener Text',$manual->fresh()->metadata['short_description']);
    }
    public function test_invalid_provider_ids_words_and_missing_results_are_rejected(): void
    {
        Queue::fake();$this->ai();$a=$this->record();$service=app(ContentShortDescriptions::class);
        $sequence=Http::fakeSequence();
        $sequence->push($this->response([['id'=>'unknown','short_description'=>'bad','insufficient_information'=>false]]));
        $sequence->push($this->response([['id'=>(string)$a->id,'short_description'=>'too short','insufficient_information'=>false]]));
        $sequence->push($this->response([]));
        foreach([[['id'=>'unknown','short_description'=>'bad','insufficient_information'=>false]],[['id'=>(string)$a->id,'short_description'=>'too short','insufficient_information'=>false]],[]] as $rows){
            $thrown=false;try{$service->generate([$a]);}catch(\RuntimeException){$thrown=true;}$this->assertTrue($thrown);
        }
    }
    public function test_insufficient_topic_is_reviewable_and_permissions_are_required(): void
    {
        Queue::fake();$this->ai();$a=$this->record();
        $this->actingAs(User::factory()->create())->postJson('/desktop/content/short-descriptions',['ids'=>[$a->id]])->assertForbidden();
        $this->actingAs($this->owner())->postJson('/desktop/content/short-descriptions',['ids'=>[$a->id]])->assertOk();
        Http::fake(['*'=>Http::response($this->response([['id'=>(string)$a->id,'short_description'=>'','insufficient_information'=>true]]))]);
        Queue::pushed(GenerateShortDescriptions::class)->first()->handle(app(ContentShortDescriptions::class));$this->assertSame('insufficient',$a->fresh()->metadata['short_description_job']['state']);
    }
    public function test_project_author_sayings_and_cover_prompt_are_editable(): void
    {
        Storage::fake('media-canonical');config(['platform.media_upload_reserve_free_bytes'=>0]);
        $this->actingAs($this->owner())->putJson('/desktop/settings',['section'=>'media_appearance','public_author_name'=>'Oleg','cover_style_prompt'=>'Shared golden style','ai_image_model'=>'test-image','hero_sayings'=>['de'=>['videos'=>'Vertraue auf Gott.']]])->assertOk();
        $a=$this->record(['public_published'=>true]);$this->assertSame('Oleg',app(PublicContent::class)->card($a)['author']);
        $this->get('/videos')->assertOk()->assertSee('Vertraue auf Gott.');
        $this->putJson('/desktop/settings',['section'=>'media_appearance','cover_style_prompt'=>'x','ai_image_model'=>'x','hero_sayings'=>['de'=>['unknown'=>'x']]])->assertUnprocessable();
    }
    public function test_youtube_comments_are_owned_pending_then_public_without_navigation(): void
    {
        Queue::fake();$a=$this->record(['public_published'=>true]);$user=User::factory()->create(['name'=>'Kommentator']);
        $this->actingAs($user)->postJson(route('public.message-submit',$a),['body'=>'Mein Kommentar zum Thema'])->assertOk();
        $comment=SourceRecord::where('kind','comment')->firstOrFail();$this->assertSame($a->id,$comment->metadata['parent_record_id']);
        Queue::assertPushed(ModeratePublicContent::class);
        $this->getJson(route('public.comments',$a))->assertOk()->assertSee('Mein Kommentar zum Thema');
        $this->actingAs(User::factory()->create())->getJson(route('public.comments',$a))->assertOk()->assertDontSee('Mein Kommentar zum Thema');
        $comment->update(['status'=>'ready','metadata'=>[...$comment->metadata,'public_published'=>true,'moderation'=>['state'=>'allowed']]]);
        $this->getJson(route('public.comments',$a))->assertOk()->assertSee('Mein Kommentar zum Thema');
    }
    public function test_short_text_is_separate_and_full_description_is_collapsed(): void
    {
        $a=$this->record(['public_published'=>true,'short_description'=>'Gottes Wort schenkt Hoffnung und Vertrauen in schwierigen Zeiten']);
        $this->assertSame($a->metadata['short_description'],app(PublicContent::class)->card($a)['excerpt']);
        $this->get(app(PublicContent::class)->card($a)['url'])->assertOk()->assertSee('public-full-description')->assertSee('Mehr anzeigen')->assertDontSee('<details class="public-full-description" open',false);
    }
    public function test_community_authors_and_message_text_are_not_replaced_by_project_defaults(): void
    {
        app(Settings::class)->update(['public_author_name'=>'Project author']);
        $record=SourceRecord::create(['source'=>'website','source_id'=>'community-test','kind'=>'post','title'=>'Frage','body'=>'Eine echte Frage aus der Community.','status'=>'ready','metadata'=>['public_section'=>'community','author'=>'Besucher']]);
        $card=app(PublicContent::class)->card($record);$this->assertSame('Besucher',$card['author']);$this->assertSame($record->body,$card['excerpt']);
    }
}
