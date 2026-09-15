<?php
namespace Tests\Feature;
use App\Models\{SourceRecord,User,PublicAiChatRequest};
use App\Services\{Settings,PublicAiChat};
use App\Jobs\AnswerPublicAiChat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Queue};
use Tests\TestCase;
class PublicAiChatTest extends TestCase
{
    use RefreshDatabase;
    private function event(): SourceRecord {return SourceRecord::create(['source'=>'website','source_id'=>'live','kind'=>'video','title'=>'Public live','body'=>'Published context','status'=>'ready','metadata'=>['public_published'=>true,'public_section'=>'live']]);}
    private function enable(): void {app(Settings::class)->update(['ai_enabled'=>true,'ai_provider'=>'openai','ai_model'=>'configured-model','ai_chat_enabled'=>true]);app(Settings::class)->updateSecrets(['ai_api_key'=>'test-only']);}
    public function test_login_consent_toggle_is_enforced_without_an_application_daily_limit(): void
    {
        Queue::fake();Http::preventStrayRequests();$event=$this->event();$url=route('public.ai-chat',$event);
        $this->postJson($url,['question'=>'Question','consent'=>true])->assertUnauthorized();$this->actingAs(User::factory()->create());
        $this->postJson($url,['question'=>'Question','consent'=>true])->assertUnprocessable();$this->enable();
        $this->postJson($url,['question'=>'Question'])->assertUnprocessable();
        $this->postJson($url,['question'=>'@Assistent Question','consent'=>true])->assertStatus(202);Queue::assertPushed(AnswerPublicAiChat::class);
        $this->postJson($url,['question'=>'Second question','consent'=>true])->assertStatus(202);
        $this->assertStringNotContainsString('Question',\Illuminate\Support\Facades\DB::table('public_ai_chat_requests')->first()->question);
    }
    public function test_only_published_context_is_sent_and_answers_are_private(): void
    {
        Queue::fake();$this->enable();$event=$this->event();
        SourceRecord::create(['source'=>'website','source_id'=>'private','kind'=>'post','title'=>'Private secret','body'=>'Private archive secret','status'=>'ready','metadata'=>['public_published'=>false]]);
        Http::fake(['api.openai.com/*'=>Http::response(['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>'Short answer']]]]] )]);
        $user=User::factory()->create();$this->actingAs($user)->get('/live?event='.$event->id)->assertOk()->assertSee('class="public-ai-chat-form"',false);$this->postJson(route('public.ai-chat',$event),['question'=>'Question','consent'=>true])->assertStatus(202);
        $entry=PublicAiChatRequest::firstOrFail();$job=new AnswerPublicAiChat($entry->id);$job->handle(app(PublicAiChat::class));$job->handle(app(PublicAiChat::class));
        Http::assertSent(fn($request)=>$request['model']==='configured-model'&&$request['store']===false&&$request['max_output_tokens']===400&&str_contains($request['input'],'Published context')&&!str_contains($request['input'],'Private archive secret'));Http::assertSentCount(1);
        $this->getJson(route('public.ai-chat-status',$entry))->assertJson(['status'=>'completed','answer'=>'Short answer']);
        $this->actingAs(User::factory()->create())->getJson(route('public.ai-chat-status',$entry))->assertNotFound();
    }
    public function test_disabling_chat_before_worker_run_prevents_api_calls(): void
    {
        Queue::fake();Http::preventStrayRequests();$this->enable();$event=$this->event();
        $this->actingAs(User::factory()->create())->postJson(route('public.ai-chat',$event),['question'=>'Question','consent'=>true])->assertStatus(202);
        app(Settings::class)->update(['ai_chat_enabled'=>false]);$entry=PublicAiChatRequest::firstOrFail();(new AnswerPublicAiChat($entry->id))->handle(app(PublicAiChat::class));
        $this->assertSame('failed',$entry->fresh()->status);Http::assertNothingSent();
    }
}
