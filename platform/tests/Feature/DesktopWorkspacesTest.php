<?php
namespace Tests\Feature;
use App\Models\{User,Role,Project,Task,Product,Sale,Media,SourceRecord,TaxonomyTerm,Collection,DesktopAiRequest,NewsletterCampaign,NewsletterSubscription,NewsletterDelivery,PublicationSchedule};
use App\Services\{Access,Settings,Taxonomy,PdfEditions,NewsletterCampaigns,DesktopAi,EditorialPlanning,StripePayments,PublicBooks};
use App\Jobs\{GeneratePdfEdition,DeliverNewsletter,AnswerDesktopAi,PublishScheduledContent};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Queue,Http,DB,Storage,Mail,URL};
use Illuminate\Support\{Str,Carbon};
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DesktopWorkspacesTest extends TestCase
{
    use RefreshDatabase;
    private User $owner;
    protected function setUp(): void
    {
        parent::setUp();app(Access::class)->seed();$this->owner=$this->user('Owner');$this->actingAs($this->owner);Queue::fake();Http::preventStrayRequests();
    }
    private function user(?string $role=null): User{$user=User::factory()->create();if($role)$user->roles()->attach(Role::where('name',$role)->firstOrFail());return $user;}
    private function record(array $data=[]): SourceRecord{return SourceRecord::create([...['source'=>'manual','source_id'=>(string)Str::uuid(),'kind'=>'post','title'=>'Material','body'=>'Original body','status'=>'ready','metadata'=>['public_section'=>'beitraege','public_published'=>false]],...$data]);}
    private function product(array $data=[]): Product{return Product::create([...['title'=>'Buch','description'=>'Text','contents'=>'PDF text','price_cents'=>500,'currency'=>'EUR','status'=>'active'],...$data]);}
    private function pdf(): Media{Storage::fake('local');Storage::disk('local')->put('edition.pdf','%PDF-1.4 test');return Media::create(['title'=>'Edition','original_name'=>'edition.pdf','mime'=>'application/pdf','kind'=>'pdf','bytes'=>14,'disk'=>'local','path'=>'edition.pdf','sha256'=>hash('sha256','%PDF-1.4 test'),'status'=>'ready']);}
    private function subscriber(string $status='active',bool $confirmed=true,array $tags=[]): NewsletterSubscription{return NewsletterSubscription::create(['email'=>Str::uuid().'@example.test','locale'=>'de','token_hash'=>hash('sha256',(string)Str::uuid()),'status'=>$status,'confirmed_at'=>$confirmed?now():null,'consented_at'=>now(),'tags'=>$tags]);}
    private function stripe(): void{app(Settings::class)->updateSecrets(['integrations_stripe'=>json_encode(['api_key'=>'sk_test_fixture_only','webhook_secret'=>'whsec_fixture_only'])]);}
    private function event(string $type,array $object,bool $valid=true)
    {
        $payload=json_encode(['id'=>'evt_fixture','object'=>'event','type'=>$type,'data'=>['object'=>$object]]);$time=time();$signature=hash_hmac('sha256',$time.'.'.$payload,$valid?'whsec_fixture_only':'wrong');
        return $this->call('POST','/payments/stripe/webhook',[],[],[],['CONTENT_TYPE'=>'application/json','HTTP_ACCEPT'=>'application/json','HTTP_STRIPE_SIGNATURE'=>'t='.$time.',v1='.$signature],$payload);
    }
    public function test_all_workspaces_have_native_views_and_permission_checks(): void
    {
        foreach(\App\Http\Controllers\DesktopWorkspaceController::APPS as $app=>$permission)$this->get('/desktop/workspaces/'.$app)->assertOk()->assertSee('data-workspace="'.$app.'"',false);
        $this->get('/desktop/workspaces/unknown')->assertNotFound();$this->actingAs($this->user());
        foreach(['/desktop/projects','/desktop/tasks','/desktop/books','/desktop/taxonomy','/desktop/assistant','/desktop/subscribers','/desktop/sales','/desktop/integrations/data','/desktop/community/inbox','/desktop/analytics/data?start=2026-01-01&end=2026-01-02'] as $url)$this->getJson($url)->assertForbidden();
        $this->actingAs($this->user('Editor'));$this->get('/desktop/workspaces/projects')->assertOk();$this->get('/desktop/workspaces/books-pdf')->assertForbidden();$this->getJson('/desktop/lookups?kind=users')->assertOk()->assertDontSee('password');
    }
    public function test_projects_tasks_and_content_are_linked_and_paginated(): void
    {
        $id=$this->postJson('/desktop/projects',['title'=>'Serie','status'=>'idea','due_date'=>'2026-10-10'])->assertOk()->json('project_id');
        $this->postJson('/desktop/tasks',['title'=>'Skript','status'=>'open','project_id'=>$id,'assigned_to'=>$this->owner->id,'due_date'=>'2026-10-01'])->assertOk();
        $record=$this->record();app(\App\Services\Importing\ContentAssignment::class)->record($record,['project_id'=>$id]);
        $this->getJson('/desktop/projects/'.$id)->assertOk()->assertJsonPath('tasks.data.0.title','Skript')->assertJsonPath('records.data.0.id',$record->id);
        $this->getJson('/desktop/tasks?mine=1&project_id='.$id)->assertOk()->assertJsonPath('total',1);
        $this->getJson('/desktop/projects?q=Serie')->assertJsonPath('data.0.records_count',1);
    }
    public function test_catalog_cards_can_have_images_and_pdf_intake_is_queued(): void
    {
        config(['platform.media_upload_reserve_free_bytes'=>0]);\Illuminate\Support\Facades\Storage::fake('local');
        $project=$this->postJson('/desktop/projects',['title'=>'Projektbild','status'=>'idea'])->assertOk()->json('project_id');
        $this->post('/desktop/projects/'.$project.'/cover',['file'=>UploadedFile::fake()->image('project.jpg')],['Accept'=>'application/json'])->assertOk();
        $this->assertNotNull(\App\Models\Project::findOrFail($project)->cover_media_id);
        $term=$this->postJson('/desktop/taxonomy',['name'=>'Bildthema','kind'=>'topic','active'=>true])->assertOk()->json('id');
        $this->post('/desktop/taxonomy/'.$term.'/cover',['file'=>UploadedFile::fake()->image('topic.jpg')],['Accept'=>'application/json'])->assertOk();
        $this->assertNotNull(TaxonomyTerm::findOrFail($term)->cover_media_id);
        $book=$this->post('/desktop/books/intake',['file'=>UploadedFile::fake()->create('book.pdf',10,'application/pdf')],['Accept'=>'application/json'])->assertAccepted()->json('id');
        $this->assertSame('queued',Product::findOrFail($book)->metadata['book_pdf_ai']['status']);
        Queue::assertPushed(\App\Jobs\AnalyzeBookPdf::class);
    }
    public function test_catalog_entries_can_be_deleted_without_deleting_related_files_or_materials(): void
    {
        $project=$this->postJson('/desktop/projects',['title'=>'Löschen','status'=>'idea'])->assertOk()->json('project_id');
        $task=Task::create(['project_id'=>$project,'title'=>'Aufgabe','status'=>'open','priority'=>'normal']);
        $record=$this->record(['project_id'=>$project]);
        $this->deleteJson('/desktop/projects/'.$project,['confirmation'=>'DELETE'])->assertOk();
        $this->assertDatabaseMissing('projects',['id'=>$project]);$this->assertNull($task->fresh()->project_id);$this->assertNull($record->fresh()->project_id);

        $term=$this->postJson('/desktop/taxonomy',['name'=>'Löschen','kind'=>'topic','active'=>true])->assertOk()->json('id');
        app(Taxonomy::class)->sync($record->fresh(),[$term]);
        $this->deleteJson('/desktop/taxonomy/'.$term,['confirmation'=>'DELETE'])->assertOk();
        $this->assertDatabaseMissing('taxonomy_terms',['id'=>$term]);$this->assertSame([], $record->fresh()->metadata['taxonomy_term_ids'] ?? []);$this->assertDatabaseCount('taxonomy_assignments',0);

        $book=$this->product();$this->deleteJson('/desktop/books/'.$book->id,['confirmation'=>'DELETE'])->assertOk();
        $this->assertDatabaseMissing('products',['id'=>$book->id]);
    }
    public function test_taxonomy_rename_deactivation_and_cycles(): void
    {
        $id=$this->postJson('/desktop/taxonomy',['name'=>'Glaube','kind'=>'topic','active'=>true])->assertOk()->json('id');$record=$this->record(['metadata'=>['tags'=>['manual']]]);app(Taxonomy::class)->sync($record,[$id]);
        $this->patchJson('/desktop/taxonomy/'.$id,['name'=>'Hoffnung','kind'=>'topic','active'=>true])->assertOk();$this->assertSame(['manual','Hoffnung'],$record->fresh()->metadata['tags']);
        $this->patchJson('/desktop/taxonomy/'.$id,['name'=>'Hoffnung','kind'=>'topic','active'=>true,'parent_id'=>$id])->assertUnprocessable();
        $this->postJson('/desktop/taxonomy',['name'=>'Hoffnung','kind'=>'topic','active'=>true])->assertUnprocessable();
        $this->patchJson('/desktop/taxonomy/'.$id,['name'=>'Hoffnung','kind'=>'topic','active'=>false])->assertOk();$this->assertSame(['manual'],$record->fresh()->metadata['tags']);$this->assertDatabaseCount('taxonomy_assignments',0);
    }
    public function test_manual_editor_sanitizes_html_and_preserves_metadata(): void
    {
        $id=$this->postJson('/desktop/content',['title'=>'Artikel','body'=>'Text','kind'=>'post','status'=>'unsorted','public_section'=>'beitraege'])->assertCreated()->json('id');
        $this->patchJson('/desktop/content/'.$id,['title'=>'Artikel','body'=>'<h2 onclick="bad()">Titel</h2><script>alert(1)</script><a href="javascript:bad()">Link</a>','body_format'=>'html','kind'=>'post','status'=>'ready','guest'=>'Gast','transcript'=>'Transkript','seo_title'=>'SEO'])->assertOk();
        $data=$this->getJson('/desktop/content/'.$id)->assertOk();$data->assertJsonPath('guest','Gast');$this->assertStringContainsString('<h2>Titel</h2>',$data->json('body'));$this->assertStringNotContainsString('script',$data->json('body'));$this->assertStringNotContainsString('javascript:',$data->json('body'));
        $record=SourceRecord::findOrFail($id);app(\App\Services\Importing\ContentAssignment::class)->record($record,['title'=>'Changed']);$this->assertSame('Gast',$record->fresh()->metadata['guest']);
        $this->actingAs($this->user('Mediengestalter'));$this->patchJson('/desktop/content/'.$id,['title'=>'X','kind'=>'post','status'=>'ready','platform_metadata'=>['x'=>['body'=>'Hello']]])->assertForbidden();
    }
    public function test_scheduled_publication_is_due_once_and_cancellable(): void
    {
        $record=$this->record();$schedule=app(EditorialPlanning::class)->schedule($record,$this->owner,['website'],now()->addHour()->toIso8601String());
        $this->assertSame(0,app(EditorialPlanning::class)->dispatchDue());$schedule->update(['publish_at'=>now()->subMinute()]);$this->assertSame(1,app(EditorialPlanning::class)->dispatchDue());$this->assertSame(0,app(EditorialPlanning::class)->dispatchDue());Queue::assertPushed(PublishScheduledContent::class,1);
        app(EditorialPlanning::class)->publish($schedule->fresh());$this->assertTrue($record->fresh()->metadata['public_published']);$this->assertSame('completed',$schedule->fresh()->status);
        $other=app(EditorialPlanning::class)->schedule($this->record(),$this->owner,['website'],now()->addHour()->toIso8601String());$other->update(['status'=>'queued']);$this->deleteJson('/desktop/planning/'.$other->id)->assertOk();app(EditorialPlanning::class)->publish($other->fresh());$this->assertSame('cancelled',$other->fresh()->status);
    }
    public function test_calendar_uses_local_time_and_respects_access(): void
    {
        app(Settings::class)->update(['system_timezone'=>'Europe/Berlin']);$record=$this->record();
        $this->postJson('/desktop/planning',['record_id'=>$record->id,'publish_at'=>now()->addDays(10)->format('Y-m-d').'T10:30','providers'=>['website']])->assertOk();
        $schedule=PublicationSchedule::firstOrFail();$date=now()->addDays(10)->toDateString();$this->getJson('/desktop/planning?start='.$date.'&end='.$date)->assertOk()->assertJsonPath('data.0.time','10:30')->assertJsonPath('data.0.type','publication')->assertJsonPath('timezone','Europe/Berlin')->assertJsonPath('limited',false);
        $this->actingAs($this->user());$this->getJson('/desktop/planning?start='.$date.'&end='.$date)->assertForbidden();
    }
    public function test_pdf_editions_are_real_private_files_and_paid_assets_stay_hidden(): void
    {
        Storage::fake('local');config(['platform.media_upload_reserve_free_bytes'=>0]);$product=$this->product();
        $this->assertStringStartsWith('%PDF-',app(PdfEditions::class)->render($product));$this->postJson('/desktop/books/'.$product->id.'/pdf')->assertAccepted();
        $job=null;Queue::assertPushed(GeneratePdfEdition::class,function($j)use(&$job){$job=$j;return true;});$job->handle(app(PdfEditions::class));
        $this->assertSame('completed',$product->fresh()->metadata['pdf_job']['status']);$media=Media::findOrFail($product->fresh()->metadata['pdf_media_id']);$this->assertSame('local',$media->disk);
        $this->assertEmpty(app(PublicBooks::class)->assets($product));$this->get('/media/public/books/'.$product->id.'/'.$media->id)->assertNotFound();
        $this->assertDatabaseHas('media_usages',['subject_type'=>Product::class,'subject_id'=>(string)$product->id,'used_as'=>'paid_download']);
        app(\App\Services\BookCatalog::class)->save(['title'=>$product->title,'currency'=>'EUR','status'=>'active','price_cents'=>0],$product);$this->assertCount(1,app(PublicBooks::class)->assets($product->fresh()));
    }
    public function test_pdf_job_rejects_stale_content(): void
    {
        Storage::fake('local');config(['platform.media_upload_reserve_free_bytes'=>0]);$product=$this->product();$version=app(PdfEditions::class)->version($product);$product->update(['contents'=>'Changed']);
        (new GeneratePdfEdition('product',$product->id,$this->owner->id,$version))->handle(app(PdfEditions::class));$this->assertSame('failed',$product->fresh()->metadata['pdf_job']['status']);$this->assertDatabaseCount('media_usages',0);
    }
    public function test_series_supports_mixed_source_episode_membership(): void
    {
        $record=$this->record(['source'=>'youtube','kind'=>'video','metadata'=>['public_section'=>'podcast','public_published'=>true]]);$series=$this->postJson('/desktop/series',['title'=>'Serie','section'=>'podcast','public_published'=>true])->assertCreated()->json('id');
        $this->postJson('/desktop/content/playlists/'.$series.'/members',['record_id'=>$record->id])->assertOk();$this->get('/podcast?series='.$series)->assertOk()->assertSee('Material');$this->getJson('/desktop/series/'.$series)->assertJsonPath('items.total',1);
    }
    public function test_ai_is_queued_owner_scoped_and_stale_proposals_are_not_applied(): void
    {
        app(Settings::class)->update(['ai_enabled'=>true,'ai_provider'=>'openai','ai_model'=>'test-model']);app(Settings::class)->updateSecrets(['ai_api_key'=>'test-not-real']);$record=$this->record();
        $id=$this->postJson('/desktop/assistant',['question'=>'Titel verbessern','purpose'=>'title','source_record_id'=>$record->id])->assertAccepted()->json('id');Queue::assertPushed(AnswerDesktopAi::class);$entry=DesktopAiRequest::findOrFail($id);
        $proposal=['answer'=>'Vorschlag','title'=>'Neuer Titel','short_description'=>'','seo_title'=>'','seo_description'=>'','social_text'=>''];
        Http::fake(['api.openai.com/*'=>Http::response(['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode($proposal)]]]]])]);
        (new AnswerDesktopAi($id))->handle(app(DesktopAi::class));$this->assertSame('completed',$entry->fresh()->status);
        Http::assertSent(fn($request)=>$request['store']===false&&$request['text']['format']['strict']===true&&!isset($request['tools']));
        $record->update(['body'=>'Edited']);$this->postJson('/desktop/assistant/'.$id.'/apply')->assertConflict();$this->assertSame('Material',$record->fresh()->title);
        $this->actingAs($this->user('Editor'));$this->getJson('/desktop/assistant')->assertJsonPath('requests.total',0);$this->postJson('/desktop/assistant/'.$id.'/apply')->assertNotFound();
    }
    public function test_ai_disabled_does_not_call_provider(): void{$this->get('/desktop')->assertOk()->assertSee('data-ai-available="false"',false);$this->postJson('/desktop/assistant',['purpose'=>'chat','question'=>'Hallo'])->assertUnprocessable();Http::assertNothingSent();}
    public function test_newsletter_requires_confirmation_and_only_delivers_confirmed_segment(): void
    {
        config(['mail.default'=>'smtp','mail.mailers.smtp.host'=>'test.local']);Mail::fake();$active=$this->subscriber('active',true,['weekly']);$pending=$this->subscriber('pending',false,['weekly']);$unconfirmed=$this->subscriber('active',false,['weekly']);$other=$this->subscriber('active',true,['other']);
        $campaign=$this->postJson('/desktop/campaigns',['subject'=>'Brief','body'=>'Text','locale'=>'de','segment'=>'weekly'])->assertCreated()->json('id');
        $this->postJson('/desktop/campaigns/'.$campaign.'/send',[])->assertUnprocessable();$this->postJson('/desktop/campaigns/'.$campaign.'/send',['confirm_send'=>true])->assertAccepted();Queue::assertPushed(\App\Jobs\PrepareNewsletterCampaign::class,1);$this->assertDatabaseCount('newsletter_deliveries',0);(new \App\Jobs\PrepareNewsletterCampaign($campaign))->handle(app(NewsletterCampaigns::class));$this->assertDatabaseCount('newsletter_deliveries',1);Queue::assertPushed(DeliverNewsletter::class,1);
        $delivery=NewsletterDelivery::firstOrFail();$active->update(['status'=>'unsubscribed']);(new DeliverNewsletter($delivery->id))->handle();$this->assertSame('skipped',$delivery->fresh()->status);(new DeliverNewsletter($delivery->id))->handle();$this->assertSame('skipped',$delivery->fresh()->status);
        $this->postJson('/desktop/campaigns/'.$campaign.'/send',['confirm_send'=>true])->assertConflict();$this->get('/desktop/campaigns/'.$campaign.'/preview')->assertOk()->assertSee('Brief');
    }
    public function test_admin_cannot_manufacture_double_opt_in_and_optout_is_signed(): void
    {
        $subscriber=$this->subscriber('pending',false);$this->patchJson('/desktop/subscribers/'.$subscriber->id,['status'=>'active','tags'=>['weekly']])->assertOk();$this->assertSame('pending',$subscriber->fresh()->status);
        $this->get('/newsletter/'.$subscriber->id.'/optout')->assertForbidden();$url=URL::signedRoute('public.newsletter-optout',['subscription'=>$subscriber->id]);$this->get($url)->assertOk();$this->assertSame('pending',$subscriber->fresh()->status);$this->post($url)->assertRedirect('/');$this->assertSame('unsubscribed',$subscriber->fresh()->status);
    }
    public function test_stripe_verified_payment_is_idempotent_and_refund_revokes_access(): void
    {
        $this->stripe();$product=$this->product();$media=$this->pdf();app(\App\Services\BookCatalog::class)->attach($product,$media,'full');$sale=Sale::create(['product_id'=>$product->id,'user_id'=>$this->owner->id,'product_title'=>'Buch','amount_cents'=>500,'currency'=>'EUR','status'=>'pending','provider'=>'stripe','provider_reference'=>'cs_fixture']);
        $this->get('/konto/books/'.$product->id.'/pdf')->assertForbidden();$object=['id'=>'cs_fixture','object'=>'checkout.session','payment_status'=>'paid','amount_total'=>500,'currency'=>'eur','payment_intent'=>'pi_fixture','metadata'=>['sale_id'=>(string)$sale->id]];
        $this->event('checkout.session.completed',$object,false)->assertBadRequest();$this->assertSame('pending',$sale->fresh()->status);
        $this->event('checkout.session.completed',[...$object,'amount_total'=>1])->assertBadRequest();$this->event('checkout.session.completed',$object)->assertOk();$this->event('checkout.session.completed',$object)->assertOk();$this->assertDatabaseCount('product_entitlements',1);
        $this->get('/konto/books/'.$product->id.'/pdf')->assertOk();$this->event('charge.refunded',['object'=>'charge','payment_intent'=>'pi_fixture','amount'=>500,'amount_refunded'=>500])->assertOk();$this->get('/konto/books/'.$product->id.'/pdf')->assertForbidden();$this->event('checkout.session.completed',$object)->assertOk();$this->assertSame('refunded',$sale->fresh()->status);
    }
    public function test_checkout_does_not_grant_access_when_unconfigured(): void{$product=$this->product();$this->postJson('/buecher/'.$product->id.'/checkout')->assertUnprocessable();$this->assertDatabaseCount('product_entitlements',0);}
    public function test_checkout_uses_real_sdk_contract_and_reuses_pending_session(): void
    {
        $this->stripe();$product=$this->product();$media=$this->pdf();app(\App\Services\BookCatalog::class)->attach($product,$media,'full');
        $transport=new class implements \Stripe\HttpClient\ClientInterface {
            public array $calls=[];
            public function request($method,$url,$headers,$params,$file,$mode='v1',$retries=null){$this->calls[]=[$method,$url,$headers,$params];return [json_encode(['id'=>'cs_sdk_fixture','object'=>'checkout.session','url'=>'https://checkout.stripe.com/c/pay/cs_sdk_fixture']),200,[]];}
        };
        \Stripe\ApiRequestor::setHttpClient($transport);
        try{
            $this->post('/buecher/'.$product->id.'/checkout')->assertRedirect('https://checkout.stripe.com/c/pay/cs_sdk_fixture');$this->post('/buecher/'.$product->id.'/checkout')->assertRedirect('https://checkout.stripe.com/c/pay/cs_sdk_fixture');
            $this->assertCount(1,$transport->calls);$this->assertSame('payment',$transport->calls[0][3]['mode']);$this->assertSame(500,$transport->calls[0][3]['line_items'][0]['price_data']['unit_amount']);$this->assertDatabaseCount('sales',1);$this->assertDatabaseCount('product_entitlements',0);
        }finally{\Stripe\ApiRequestor::setHttpClient(\Stripe\HttpClient\CurlClient::instance());}
    }
    public function test_newsletter_sends_once_and_cannot_be_edited_after_launch(): void
    {
        config(['mail.default'=>'smtp','mail.mailers.smtp.host'=>'test.local']);
        $subscriber=$this->subscriber();$campaign=NewsletterCampaign::create(['user_id'=>$this->owner->id,'subject'=>'Brief','body'=>'Text','status'=>'sending']);$delivery=NewsletterDelivery::create(['newsletter_campaign_id'=>$campaign->id,'newsletter_subscription_id'=>$subscriber->id]);
        Mail::shouldReceive('send')->once()->with('mail.newsletter-campaign',\Mockery::on(fn($data)=>str_contains($data['cancel'],'optout')),\Mockery::type('Closure'));
        (new DeliverNewsletter($delivery->id))->handle();(new DeliverNewsletter($delivery->id))->handle();$this->assertSame('sent',$delivery->fresh()->status);$this->assertSame('sent',$campaign->fresh()->status);
        $this->patchJson('/desktop/campaigns/'.$campaign->id,['subject'=>'Changed','body'=>'Changed'])->assertConflict();
    }
    public function test_analytics_tracks_public_sessions_without_private_paths_or_query_text(): void
    {
        $this->get('/videos')->assertOk();$this->get('/videos')->assertOk();$this->get('/suche?q=private@example.test')->assertOk();$this->get('/desktop')->assertOk();
        $this->assertDatabaseHas('analytics_events',['event'=>'page_view','subject'=>'/videos']);$this->assertDatabaseHas('analytics_events',['event'=>'search','subject'=>'search']);$this->assertSame(0,DB::table('analytics_events')->where('subject','like','%private@example.test%')->count());$this->assertSame(0,DB::table('analytics_events')->where('subject','like','%desktop%')->count());
        $today=now()->toDateString();$this->getJson('/desktop/analytics/data?start='.$today.'&end='.$today)->assertOk()->assertJsonPath('external_available',false);$this->get('/desktop/analytics/export?start='.$today.'&end='.$today)->assertOk();
    }
    public function test_inbox_local_replies_use_existing_public_comment_contract(): void
    {
        $parent=$this->record(['metadata'=>['public_section'=>'beitraege','public_published'=>true]]);$comment=$this->record(['source'=>'website','kind'=>'comment','metadata'=>['parent_record_id'=>$parent->id,'website_comment'=>true,'public_published'=>true]]);
        $this->getJson('/desktop/community/inbox?state=unread')->assertOk()->assertJsonPath('total',1);$this->patchJson('/desktop/community/inbox/'.$comment->id.'/read')->assertOk();$this->getJson('/desktop/community/inbox?state=unread')->assertJsonPath('total',0);
        $id=$this->postJson('/desktop/community/inbox/'.$comment->id.'/reply',['body'=>'Antwort','target'=>'website'])->assertCreated()->json('id');$this->assertDatabaseHas('source_records',['id'=>$id,'kind'=>'comment','status'=>'ready']);$this->assertTrue(app(\App\Services\PublicContent::class)->children($parent)->whereKey($id)->exists());
    }
    public function test_changed_scheduled_content_requires_new_approval(): void
    {
        $record=$this->record();$schedule=app(EditorialPlanning::class)->schedule($record,$this->owner,['website'],now()->addHour()->toIso8601String());$schedule->update(['status'=>'queued']);$record->update(['body'=>'Changed after approval']);(new PublishScheduledContent($schedule->id))->handle(app(EditorialPlanning::class));$this->assertSame('failed',$schedule->fresh()->status);$this->assertFalse($record->fresh()->metadata['public_published']);
    }
    public function test_forward_migrations_preserve_existing_content_and_sales(): void
    {
        if(DB::connection()->getDriverName()!=='sqlite')$this->markTestSkipped('Rollback simulation uses SQLite; forward MySQL migrations run separately in CI.');
        $checkout=require database_path('migrations/2026_09_14_000006_add_checkout_references.php');$workspaces=require database_path('migrations/2026_09_14_000005_complete_desktop_workspaces.php');$checkout->down();$workspaces->down();
        $record=$this->record();$product=$this->product();$sale=Sale::create(['product_id'=>$product->id,'amount_cents'=>500,'currency'=>'EUR','status'=>'paid']);$workspaces->up();$checkout->up();
        $this->assertDatabaseHas('source_records',['id'=>$record->id,'body'=>'Original body']);$this->assertDatabaseHas('products',['id'=>$product->id,'contents'=>'PDF text']);$this->assertDatabaseHas('sales',['id'=>$sale->id,'amount_cents'=>500,'status'=>'paid']);
    }
    public function test_paid_pdf_cannot_be_exposed_as_its_own_sample(): void
    {
        $product=$this->product();$media=$this->pdf();app(\App\Services\BookCatalog::class)->attach($product,$media,'full');
        $this->postJson('/desktop/books/'.$product->id.'/assets',['slot'=>'sample','media_id'=>$media->id])->assertUnprocessable();
        $this->assertEmpty(app(PublicBooks::class)->assets($product));
    }
    public function test_large_taxonomy_refresh_is_queued(): void
    {
        $term=TaxonomyTerm::create(['name'=>'Before','kind'=>'topic','slug'=>'before','active'=>true]);for($i=0;$i<101;$i++)app(Taxonomy::class)->sync($this->record(),[$term->id]);
        $this->patchJson('/desktop/taxonomy/'.$term->id,['name'=>'After','kind'=>'topic','active'=>true])->assertOk();$this->assertSame('queued',$term->fresh()->refresh_status);Queue::assertPushed(\App\Jobs\RefreshTaxonomyAssignments::class);
        (new \App\Jobs\RefreshTaxonomyAssignments($term->id,$this->owner->id))->handle(app(Taxonomy::class));$this->assertSame('ready',$term->fresh()->refresh_status);$this->assertSame(['After'],SourceRecord::first()->metadata['tags']);
    }
    public function test_cancelled_preparation_does_not_enqueue_deliveries(): void
    {
        config(['mail.default'=>'smtp','mail.mailers.smtp.host'=>'test.local']);$this->subscriber();$campaign=NewsletterCampaign::create(['user_id'=>$this->owner->id,'subject'=>'Draft','body'=>'Body','status'=>'draft']);
        app(NewsletterCampaigns::class)->send($campaign);$this->assertSame('preparing',$campaign->fresh()->status);$this->assertDatabaseCount('newsletter_deliveries',0);
        $this->postJson('/desktop/campaigns/'.$campaign->id.'/cancel')->assertOk();(new \App\Jobs\PrepareNewsletterCampaign($campaign->id))->handle(app(NewsletterCampaigns::class));$this->assertDatabaseCount('newsletter_deliveries',0);
    }
    public function test_cancelling_during_delivery_is_not_overwritten(): void
    {
        config(['mail.default'=>'smtp','mail.mailers.smtp.host'=>'test.local']);$subscriber=$this->subscriber();$campaign=NewsletterCampaign::create(['user_id'=>$this->owner->id,'subject'=>'Brief','body'=>'Text','status'=>'sending']);$delivery=NewsletterDelivery::create(['newsletter_campaign_id'=>$campaign->id,'newsletter_subscription_id'=>$subscriber->id]);
        Mail::shouldReceive('send')->once()->andReturnUsing(function()use($campaign){$campaign->update(['status'=>'cancelled']);});(new DeliverNewsletter($delivery->id))->handle();$this->assertSame('cancelled',$campaign->fresh()->status);
    }
    public function test_partial_integration_update_preserves_webhook_secret(): void
    {
        $this->stripe();$this->putJson('/desktop/settings',['section'=>'integrations','provider'=>'stripe','api_key'=>'sk_test_updated_fixture'])->assertOk();$keys=json_decode(app(Settings::class)->secret('integrations_stripe'),true);$this->assertSame('whsec_fixture_only',$keys['webhook_secret']);$this->assertSame('sk_test_updated_fixture',$keys['api_key']);
    }
    public function test_failed_checkout_webhook_does_not_grant_access(): void
    {
        $this->stripe();$product=$this->product();$sale=Sale::create(['product_id'=>$product->id,'user_id'=>$this->owner->id,'amount_cents'=>500,'currency'=>'EUR','provider'=>'stripe','provider_reference'=>'cs_expired_fixture','status'=>'pending']);$this->event('checkout.session.expired',['id'=>'cs_expired_fixture','metadata'=>['sale_id'=>$sale->id]])->assertOk();$this->assertSame('failed',$sale->fresh()->status);$this->assertDatabaseCount('product_entitlements',0);
    }
    public function test_purchased_archived_pdf_is_private_and_linked_from_account(): void
    {
        $buyer=$this->user();$buyer->update(['email_verified_at'=>now()]);$product=$this->product();$media=$this->pdf();app(\App\Services\BookCatalog::class)->attach($product,$media,'full');$product->update(['status'=>'archived']);
        $sale=Sale::create(['product_id'=>$product->id,'user_id'=>$buyer->id,'customer_email'=>'old@example.test','product_title'=>'Purchased title','amount_cents'=>500,'currency'=>'EUR','status'=>'paid']);DB::table('product_entitlements')->insert(['user_id'=>$buyer->id,'product_id'=>$product->id,'sale_id'=>$sale->id,'created_at'=>now(),'updated_at'=>now()]);
        $this->actingAs($buyer);$this->get('/konto')->assertOk()->assertSee('/konto/books/'.$product->id.'/pdf',false)->assertSee('Purchased title');$this->get('/konto/books/'.$product->id.'/pdf')->assertDownload('edition.pdf');
        $this->actingAs($this->user());$this->get('/konto/books/'.$product->id.'/pdf')->assertForbidden();
    }
    public function test_complete_edition_text_is_not_the_public_table_of_contents(): void
    {
        $product=app(\App\Services\BookCatalog::class)->save(['title'=>'Private edition','description'=>'Public description','contents'=>'Public table of contents','edition_text'=>'PRIVATE FULL BOOK BODY','price_cents'=>500,'currency'=>'EUR','status'=>'active']);
        $this->get(app(PublicBooks::class)->card($product)['url'])->assertOk()->assertSee('Public table of contents')->assertDontSee('PRIVATE FULL BOOK BODY');$this->getJson('/desktop/books/'.$product->id)->assertJsonPath('product.metadata.edition_text','PRIVATE FULL BOOK BODY');
        $before=app(PdfEditions::class)->version($product);$product->update(['metadata'=>['edition_text'=>'Changed private edition']]);$this->assertNotSame($before,app(PdfEditions::class)->version($product));
    }
    public function test_checkout_rejects_archived_complete_asset_and_hides_buy_button(): void
    {
        $this->stripe();$product=$this->product();$media=$this->pdf();app(\App\Services\BookCatalog::class)->attach($product,$media,'full');$media->update(['archived_at'=>now()]);$this->postJson('/buecher/'.$product->id.'/checkout')->assertUnprocessable();$this->assertDatabaseCount('sales',0);$this->get(app(PublicBooks::class)->card($product)['url'])->assertOk()->assertDontSee(__('workspaces.buy'));
    }
}
