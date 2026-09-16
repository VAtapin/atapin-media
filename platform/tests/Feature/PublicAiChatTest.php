<?php

namespace Tests\Feature;

use App\Models\{Product,PublicAiChatRequest,SourceRecord,User};
use App\Services\{AdminKnowledgeBase,Settings};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Queue};
use Tests\TestCase;

class PublicAiChatTest extends TestCase
{
    use RefreshDatabase;

    private function event(): SourceRecord
    {
        return SourceRecord::create(['source'=>'website','source_id'=>'live','kind'=>'video','title'=>'Public live',
            'body'=>'Published context','status'=>'ready','metadata'=>['public_published'=>true,'public_section'=>'live']]);
    }
    private function enable(): void
    {
        app(Settings::class)->update(['ai_enabled'=>true,'ai_provider'=>'openai','ai_model'=>'configured-model','ai_chat_enabled'=>true]);
        app(Settings::class)->updateSecrets(['ai_api_key'=>'test-only']);
    }
    private function answer(string $text='Short answer'): void
    {
        Http::fake(['api.openai.com/*'=>Http::response(['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>$text]]]]])]);
    }

    public function test_login_consent_and_toggle_are_enforced_without_queuing(): void
    {
        Queue::fake();Http::preventStrayRequests();$event=$this->event();$url=route('public.ai-chat',$event);
        $this->postJson($url,['question'=>'Question','consent'=>true])->assertUnauthorized();
        $this->actingAs(User::factory()->create());
        $this->postJson($url,['question'=>'Question','consent'=>true])->assertUnprocessable();
        $this->enable();$this->postJson($url,['question'=>'Question'])->assertUnprocessable();$this->answer();
        $this->postJson($url,['question'=>'@Assistent Question','consent'=>true])->assertOk()->assertJson(['status'=>'completed','answer'=>'Short answer']);
        $this->postJson($url,['question'=>'Second question','consent'=>true])->assertOk();
        Queue::assertNothingPushed();$this->assertSame(2,PublicAiChatRequest::where('status','completed')->count());
        $this->assertSame('Question',PublicAiChatRequest::firstOrFail()->question);
    }

    public function test_active_books_contents_and_published_site_context_are_sent_but_private_data_is_not(): void
    {
        Queue::fake();$this->enable();$event=$this->event();
        Product::create(['title'=>'Visible book','description'=>'Published book description','contents'=>'Public chapter one',
            'status'=>'active','currency'=>'EUR','price_cents'=>0]);
        Product::create(['title'=>'Draft secret book','description'=>'Secret synopsis','contents'=>'Private draft chapter',
            'status'=>'draft','currency'=>'EUR','price_cents'=>0]);
        SourceRecord::create(['source'=>'website','source_id'=>'private','kind'=>'post','title'=>'Private secret',
            'body'=>'Private archive secret','status'=>'ready','metadata'=>['public_published'=>false]]);
        $this->answer('Visible book');$user=User::factory()->create();$this->actingAs($user);
        $this->get('/live?event='.$event->id)->assertOk()->assertSee('class="public-ai-chat-form"',false);
        $this->postJson(route('public.ai-chat',$event),['question'=>'Какие книги есть на сайте?','consent'=>true])
            ->assertOk()->assertJsonPath('answer','Visible book');
        Http::assertSent(fn($request)=>$request['model']==='configured-model'&&$request['store']===false
            &&str_contains($request['input'],'Visible book')&&str_contains($request['input'],'Public chapter one')
            &&str_contains($request['input'],'Published context')&&str_contains($request['input'],'server_clock')
            &&!str_contains($request['input'],'Draft secret book')&&!str_contains($request['input'],'Private archive secret'));
        Http::assertSentCount(1);Queue::assertNothingPushed();
        $entry=PublicAiChatRequest::firstOrFail();
        $this->getJson(route('public.ai-chat-status',$entry))->assertJson(['status'=>'completed','answer'=>'Visible book']);
        $this->actingAs(User::factory()->create())->getJson(route('public.ai-chat-status',$entry))->assertNotFound();
    }

    public function test_provider_failure_is_reported_immediately_and_recorded(): void
    {
        Queue::fake();$this->enable();$event=$this->event();
        Http::fake(['api.openai.com/*'=>Http::response(['error'=>['message'=>'provider failed']],500)]);
        $this->actingAs(User::factory()->create())->postJson(route('public.ai-chat',$event),
            ['question'=>'Question','consent'=>true])->assertStatus(503)->assertJsonPath('status','failed');
        $this->assertSame('failed',PublicAiChatRequest::firstOrFail()->status);Queue::assertNothingPushed();
    }

    public function test_published_podcast_and_poll_are_available_to_matching_questions(): void
    {
        $this->enable();$event=$this->event();
        SourceRecord::create(['source'=>'website','source_id'=>'episode','kind'=>'post','title'=>'Faith podcast episode',
            'body'=>'Published episode details','status'=>'ready','metadata'=>['public_published'=>true,'public_section'=>'podcast']]);
        SourceRecord::create(['source'=>'website','source_id'=>'poll','kind'=>'poll','title'=>'Reader poll',
            'body'=>'Published poll options','status'=>'ready','metadata'=>['public_published'=>true,'public_section'=>'community']]);
        SourceRecord::create(['source'=>'website','source_id'=>'hidden-poll','kind'=>'poll','title'=>'Secret poll',
            'body'=>'Private options','status'=>'ready','metadata'=>['public_published'=>false,'public_section'=>'community']]);
        $this->answer();$this->actingAs(User::factory()->create());
        $this->postJson(route('public.ai-chat',$event),['question'=>'Какие подкасты есть?','consent'=>true])->assertOk();
        $this->postJson(route('public.ai-chat',$event),['question'=>'Какие опросы есть?','consent'=>true])->assertOk();
        Http::assertSent(fn($request)=>str_contains($request['input'],'Faith podcast episode')
            &&!str_contains($request['input'],'Secret poll'));
        Http::assertSent(fn($request)=>str_contains($request['input'],'Reader poll')
            &&!str_contains($request['input'],'Secret poll'));
        Http::assertSentCount(2);
    }

    public function test_public_button_manual_is_supplied_without_admin_entries(): void
    {
        $this->enable();$event=$this->event();app(AdminKnowledgeBase::class)->sync();
        $this->answer();$this->actingAs(User::factory()->create());
        $this->postJson(route('public.ai-chat',$event),
            ['question'=>'Где оглавление книги?','consent'=>true])->assertOk();
        Http::assertSent(fn($request)=>str_contains($request['input'],'public-book-controls')
            &&str_contains($request['input'],'Inhaltsverzeichnis')
            &&!str_contains($request['input'],'desktop-navigation')
            &&!str_contains($request['input'],'secret'));
    }
}
