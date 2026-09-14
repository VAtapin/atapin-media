<?php
namespace Tests\Feature;
use App\Models\{User,Role,Project,Task,SourceRecord,Product,DesktopAiRequest,NewsletterSubscription};
use App\Services\{Access,Settings,DesktopAi,PublicParticipation,Polls};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Queue,Http};
use Tests\TestCase;
class AdminCompletionTest extends TestCase
{
    use RefreshDatabase;
    private User $owner;
    protected function setUp(): void {parent::setUp();app(Access::class)->seed();Queue::fake();Http::preventStrayRequests();$this->owner=$this->staff('Owner');$this->actingAs($this->owner);}
    private function staff(string $role): User {$user=User::factory()->create();$user->roles()->attach(Role::where('name',$role)->firstOrFail());return $user;}
    private function record(array $attributes=[]): SourceRecord {return SourceRecord::create([...['source'=>'manual','source_id'=>(string)\Illuminate\Support\Str::uuid(),'title'=>'Draft title','body'=>'Draft original text','kind'=>'post','status'=>'ready','metadata'=>['public_section'=>'beitraege','public_published'=>false]],...$attributes]);}
    private function pollData(array $data=[]): array {return [...['title'=>'A question','body'=>'','options'=>['Yes','No'],'starts_at'=>null,'ends_at'=>now()->addDay()->toIso8601String(),'active'=>true,'multiple'=>false,'audience'=>'registered','results'=>'after_vote','placements'=>['community'],'public_published'=>true],...$data];}
    public function test_projects_and_tasks_keep_details_and_filter_without_cross_project_links(): void
    {
        $id=$this->postJson('/desktop/projects',['title'=>'Series','status'=>'production','type'=>'video','user_id'=>$this->owner->id,'team_ids'=>[$this->owner->id],'start_date'=>'2026-09-01','due_date'=>'2026-10-01','tags'=>['Bible'],'next_action'=>'Record'])->assertOk()->json('project_id');
        $this->getJson('/desktop/projects/'.$id)->assertJsonPath('project.team_ids.0',$this->owner->id)->assertJsonPath('project.next_action','Record');
        $record=$this->record(['project_id'=>$id]);$task=$this->postJson('/desktop/tasks',['title'=>'Edit','status'=>'working','priority'=>'high','project_id'=>$id,'source_record_id'=>$record->id,'assigned_to'=>$this->owner->id,'due_date'=>'2026-09-20','checklist'=>[['text'=>'Subtitles','done'=>false]],'tags'=>['video']])->assertOk()->json('task_id');
        $this->patchJson('/desktop/tasks/'.$task,['checklist'=>[['text'=>'Subtitles','done'=>true]]])->assertOk();
        $this->getJson('/desktop/tasks?priority=high&mine=1&content_type=post&due_before=2026-09-21')->assertJsonPath('total',1)->assertJsonPath('data.0.checklist.0.done',true);
        $this->getJson('/desktop/tasks?priority=low')->assertJsonPath('total',0);
        $other=Project::create(['title'=>'Other','status'=>'idea']);$this->patchJson('/desktop/tasks/'.$task,['project_id'=>$other->id,'source_record_id'=>$record->id])->assertUnprocessable();
        $this->assertSame($id,Task::findOrFail($task)->project_id);
    }
    public function test_poll_lifecycle_multiple_votes_and_locked_options(): void
    {
        $id=$this->postJson('/desktop/polls',$this->pollData(['multiple'=>true]))->assertOk()->json('id');$poll=SourceRecord::findOrFail($id);
        $reader=User::factory()->create(['email_verified_at'=>now()]);$this->actingAs($reader);
        $this->get('/community')->assertOk()->assertDontSee('<meter',false);
        $this->postJson('/public/records/'.$id.'/state',['action'=>'vote','options'=>[0,1]])->assertOk();
        $this->postJson('/public/records/'.$id.'/state',['action'=>'vote','options'=>[1]])->assertOk();
        $this->assertSame(1,app(PublicParticipation::class)->states($poll)->where('action','vote')->count());
        $this->get('/community')->assertOk()->assertSee('<meter',false);
        $this->postJson('/public/records/'.$id.'/state',['action'=>'vote','options'=>[99]])->assertUnprocessable();
        $this->actingAs($this->owner);$this->patchJson('/desktop/polls/'.$id,$this->pollData(['options'=>['Different','No']]))->assertUnprocessable();
        $this->patchJson('/desktop/polls/'.$id,$this->pollData(['active'=>false]))->assertOk();$this->actingAs($reader);$this->postJson('/public/records/'.$id.'/state',['action'=>'vote','option'=>0])->assertUnprocessable();
        $this->assertNull(app(Polls::class)->current('community'));
    }
    public function test_poll_audience_and_future_start_are_enforced(): void
    {
        $id=$this->postJson('/desktop/polls',$this->pollData(['audience'=>'subscriber']))->assertOk()->json('id');$reader=User::factory()->create(['email_verified_at'=>now()]);$this->actingAs($reader);
        $this->postJson('/public/records/'.$id.'/state',['action'=>'vote','option'=>0])->assertForbidden();
        NewsletterSubscription::create(['email'=>$reader->email,'locale'=>'de','status'=>'active','token_hash'=>hash('sha256','test'),'consented_at'=>now(),'confirmed_at'=>now()]);
        $this->postJson('/public/records/'.$id.'/state',['action'=>'vote','option'=>0])->assertOk();
        $this->actingAs($this->owner);$this->patchJson('/desktop/polls/'.$id,$this->pollData(['starts_at'=>now()->addHour()->toIso8601String()]))->assertOk();$this->actingAs($reader);$this->postJson('/public/records/'.$id.'/state',['action'=>'vote','option'=>0])->assertUnprocessable();
    }
    public function test_actual_draft_preview_is_private_and_does_not_publish(): void
    {
        $record=$this->record();$url='/desktop/content/'.$record->id.'/preview';
        $this->get($url)->assertOk()->assertSee('Draft original text')->assertHeader('X-Robots-Tag','noindex, nofollow')->assertDontSee('data-comments-url=',false)->assertDontSee('data-view-url=',false);
        $this->assertFalse($record->fresh()->metadata['public_published']);$this->get('/beitraege/draft-title-'.$record->id)->assertNotFound();
        $this->actingAs(User::factory()->create());$this->get($url)->assertForbidden();$this->getJson('/desktop/polls')->assertForbidden();
    }
    public function test_overview_respects_each_widget_permission(): void
    {
        $this->getJson('/desktop/overview')->assertOk()->assertJsonStructure(['widgets'=>['projects','tasks','media','settings','newsletter']]);
        $role=Role::create(['name'=>'Desktop only']);$role->permissions()->attach(\App\Models\Permission::where('name','desktop.view')->firstOrFail());$user=User::factory()->create();$user->roles()->attach($role);
        $this->actingAs($user);$this->getJson('/desktop/overview')->assertExactJson(['widgets'=>[]]);
    }
    public function test_book_ai_uses_book_context_and_rejects_stale_application(): void
    {
        app(Settings::class)->update(['ai_enabled'=>true,'ai_provider'=>'openai','ai_model'=>'test-model']);app(Settings::class)->updateSecrets(['ai_api_key'=>'test-not-real']);
        $book=Product::create(['title'=>'Book context','description'=>'Synopsis','author'=>'Author','contents'=>'Chapter one','status'=>'draft','currency'=>'EUR','price_cents'=>0]);
        $id=$this->postJson('/desktop/assistant',['question'=>'Improve title','purpose'=>'title','product_id'=>$book->id])->assertAccepted()->json('id');
        $proposal=['answer'=>'Suggestion','title'=>'Improved book','short_description'=>'','seo_title'=>'','seo_description'=>'','social_text'=>''];
        Http::fake(['api.openai.com/*'=>Http::response(['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode($proposal)]]]]])]);
        (new \App\Jobs\AnswerDesktopAi($id))->handle(app(DesktopAi::class));Http::assertSent(fn($r)=>$r['store']===false&&str_contains($r['input'],'Book context')&&str_contains($r['input'],'Chapter one'));
        $book->update(['description'=>'Changed synopsis']);$this->postJson('/desktop/assistant/'.$id.'/apply')->assertConflict();$this->assertSame('Book context',$book->fresh()->title);
    }
    public function test_document_import_preserves_original_and_manual_edits(): void
    {
        \Illuminate\Support\Facades\Storage::fake('private');\Illuminate\Support\Facades\Storage::disk('private')->put('article.txt','A document body');
        $media=\App\Models\Media::create(['title'=>'Imported document','original_name'=>'article.txt','kind'=>'document','mime'=>'text/plain','disk'=>'private','path'=>'article.txt','sha256'=>hash('sha256','doc'),'bytes'=>15,'status'=>'ready']);
        $this->postJson('/desktop/media/'.$media->id.'/article')->assertAccepted();$this->postJson('/desktop/media/'.$media->id.'/article')->assertConflict();
        (new \App\Jobs\ImportDocumentArticle($media->id,$this->owner->id))->handle(app(\App\Services\DocumentArticleImport::class));
        $record=SourceRecord::where('source_id','document:'.$media->id)->firstOrFail();$this->assertSame('A document body',$record->body);$this->assertFalse($record->metadata['public_published']);
        $record->update(['body'=>'Manual edit']);app(\App\Services\DocumentArticleImport::class)->import($media);$this->assertSame('Manual edit',$record->fresh()->body);$this->assertSame(1,SourceRecord::where('source_id','document:'.$media->id)->count());\Illuminate\Support\Facades\Storage::disk('private')->assertExists('article.txt');
    }
    public function test_private_draft_media_is_available_only_to_authorized_editor(): void
    {
        \Illuminate\Support\Facades\Storage::fake('private');\Illuminate\Support\Facades\Storage::disk('private')->put('cover.png','test-image');
        $media=\App\Models\Media::create(['title'=>'Cover','original_name'=>'cover.png','kind'=>'image','mime'=>'image/png','disk'=>'private','path'=>'cover.png','sha256'=>hash('sha256','image'),'bytes'=>10,'status'=>'ready']);
        $record=$this->record(['metadata'=>['media_ids'=>[$media->id],'cover_media_id'=>$media->id,'public_published'=>false,'public_section'=>'beitraege']]);
        $this->get('/desktop/content/'.$record->id.'/preview')->assertOk()->assertSee('/preview/media/'.$media->id);
        $this->get('/desktop/content/'.$record->id.'/preview/media/'.$media->id)->assertOk();
        $this->actingAs(User::factory()->create());$this->get('/desktop/content/'.$record->id.'/preview/media/'.$media->id)->assertForbidden();
    }
    public function test_docx_extracts_paragraphs_and_html_import_removes_scripts(): void
    {
        \Illuminate\Support\Facades\Storage::fake('private');$disk=\Illuminate\Support\Facades\Storage::disk('private');$zip=new \ZipArchive;$zip->open($disk->path('doc.docx'),\ZipArchive::CREATE);$zip->addFromString('word/document.xml','<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>First paragraph</w:t></w:r></w:p><w:p><w:r><w:t>Second paragraph</w:t></w:r></w:p></w:body></w:document>');$zip->close();
        $media=\App\Models\Media::create(['title'=>'DOCX','original_name'=>'doc.docx','kind'=>'document','mime'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document','disk'=>'private','path'=>'doc.docx','sha256'=>hash('sha256','docx'),'bytes'=>$disk->size('doc.docx'),'status'=>'ready']);$record=app(\App\Services\DocumentArticleImport::class)->import($media);$this->assertSame("First paragraph\n\nSecond paragraph",$record->body);
        $disk->put('doc.html','<h2>Heading</h2><script>alert(1)</script><p>Body</p>');$media->update(['path'=>'doc.html','mime'=>'text/html']);$new=\App\Models\Media::create([...$media->getAttributes(),'id'=>(string)\Illuminate\Support\Str::uuid(),'sha256'=>hash('sha256','html')]);$html=app(\App\Services\DocumentArticleImport::class)->import($new);$this->assertStringNotContainsString('script',$html->body);$this->assertStringContainsString('<h2>Heading</h2>',$html->body);
    }
    public function test_closed_poll_results_remain_visible_but_future_poll_does_not_expose_results(): void
    {
        $id=$this->postJson('/desktop/polls',$this->pollData(['results'=>'after_close','starts_at'=>now()->subDay()->toIso8601String(),'ends_at'=>now()->subHour()->toIso8601String()]))->assertOk()->json('id');
        $poll=SourceRecord::findOrFail($id);$this->assertTrue(app(Polls::class)->results($poll,null));$this->assertSame($id,app(Polls::class)->current('community')->id);
        $this->patchJson('/desktop/polls/'.$id,$this->pollData(['results'=>'after_close','starts_at'=>now()->addHour()->toIso8601String(),'ends_at'=>now()->addDay()->toIso8601String()]))->assertOk();
        $this->assertFalse(app(Polls::class)->results($poll->fresh(),null));$this->assertNull(app(Polls::class)->current('community'));
    }
    public function test_live_monitor_is_private_and_counts_only_current_presence(): void
    {
        $record=$this->record(['kind'=>'video','metadata'=>['public_section'=>'live','live_status'=>'live','live_signal_at'=>now()->toIso8601String(),'public_published'=>true]]);
        \Illuminate\Support\Facades\DB::table('public_live_presence')->insert([['record_id'=>$record->id,'session_hash'=>hash('sha256','current'),'seen_at'=>now()],['record_id'=>$record->id,'session_hash'=>hash('sha256','old'),'seen_at'=>now()->subMinutes(10)]]);
        $this->getJson('/desktop/live/'.$record->id.'/monitor')->assertOk()->assertJsonPath('online',1)->assertJsonPath('status','live')->assertJsonMissingPath('session_hash');
        $this->actingAs(User::factory()->create());$this->getJson('/desktop/live/'.$record->id.'/monitor')->assertForbidden();
    }
    public function test_publishing_preview_uses_provider_override_without_creating_publication(): void
    {
        $record=$this->record(['metadata'=>['public_section'=>'beitraege','public_published'=>false,'platform_metadata'=>['youtube'=>['title'=>'YouTube title','body'=>'YouTube body']],'tags'=>['Bible']]]);
        $this->getJson('/desktop/publishing/preview?record_id='.$record->id)->assertOk()->assertJsonPath('previews.youtube.title','YouTube title')->assertJsonPath('previews.youtube.body','YouTube body')->assertJsonPath('previews.youtube.visibility','public');
        $this->assertDatabaseCount('publications',0);$this->assertFalse($record->fresh()->metadata['public_published']);
        $this->actingAs(User::factory()->create());$this->getJson('/desktop/publishing/preview?record_id='.$record->id)->assertForbidden();
    }
    public function test_podcast_preview_uses_private_audio_route_without_analytics_or_progress(): void
    {
        \Illuminate\Support\Facades\Storage::fake('private');\Illuminate\Support\Facades\Storage::disk('private')->put('audio.mp3','test audio');
        $media=\App\Models\Media::create(['title'=>'Private audio','original_name'=>'audio.mp3','kind'=>'audio','mime'=>'audio/mpeg','disk'=>'private','path'=>'audio.mp3','sha256'=>hash('sha256','audio'),'bytes'=>10,'status'=>'ready']);
        $record=$this->record(['kind'=>'video','metadata'=>['public_section'=>'podcast','media_ids'=>[$media->id],'public_published'=>false]]);
        $this->get('/desktop/content/'.$record->id.'/preview')->assertOk()->assertSee('/preview/media/'.$media->id)->assertDontSee('data-audio-view-url=',false)->assertDontSee('data-progress-url=',false);
    }
    public function test_book_seo_metadata_reaches_public_layout_and_does_not_leak_edition(): void
    {
        $id=$this->postJson('/desktop/books',['title'=>'Book','status'=>'active','price_cents'=>0,'currency'=>'EUR','subtitle'=>'Subtitle','publication_date'=>'2026-09-14','tags'=>['Bible'],'seo_title'=>'Book search title','seo_description'=>'Book search description','edition_text'=>'Private edition'])->assertCreated()->json('id');
        $book=Product::findOrFail($id);$this->assertSame('Subtitle',$book->metadata['subtitle']);
        $this->get(app(\App\Services\PublicBooks::class)->card($book)['url'])->assertOk()->assertSee('Book search title')->assertSee('Book search description')->assertDontSee('Private edition');
    }
}
