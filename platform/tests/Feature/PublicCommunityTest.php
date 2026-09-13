<?php

namespace Tests\Feature;

use App\Jobs\ModeratePublicContent;
use App\Models\{Role, SourceRecord, User};
use App\Services\{Access, PublicAiModerator, PublicCommunityModeration, Settings};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http, Queue};
use Tests\TestCase;

class PublicCommunityTest extends TestCase
{
    use RefreshDatabase;

    private function event(): SourceRecord
    {
        return SourceRecord::create(['source'=>'website','source_id'=>'live','kind'=>'video','title'=>'Live','body'=>'Live context','status'=>'ready','metadata'=>['public_published'=>true,'public_section'=>'live']]);
    }

    private function enableAi(): void
    {
        app(Settings::class)->update(['ai_enabled'=>true,'ai_provider'=>'openai','ai_model'=>'moderation-model']);
        app(Settings::class)->updateSecrets(['ai_api_key'=>'test-only']);
    }

    private function aiResponse(string $verdict, float $risk, array $reasons=[]): array
    {
        return ['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode(['verdict'=>$verdict,'risk'=>$risk,'reasons'=>$reasons],JSON_THROW_ON_ERROR)]]]]];
    }

    public function test_guests_and_signed_in_users_can_submit_without_auth_or_rate_limit(): void
    {
        Queue::fake();
        $data=['title'=>'Visitor question','body'=>'A real visitor question','type'=>'question'];
        $this->post('/community',$data)->assertRedirect();
        $user=User::factory()->create();
        $this->actingAs($user)->post('/community',[...$data,'title'=>'Member question'])->assertRedirect();
        for($i=0;$i<4;$i++)$this->post('/community',[...$data,'title'=>'Guest question '.$i])->assertRedirect();
        $records=SourceRecord::where('kind','post')->get();
        $this->assertCount(6,$records);
        $this->assertSame('guest',$records->first()->metadata['author_type']);
        $this->assertSame('user',$records->get(1)->metadata['author_type']);
        $this->assertSame('pending_ai',$records->first()->metadata['moderation']['state']);
        Queue::assertPushed(ModeratePublicContent::class,6);
    }

    public function test_guests_and_signed_in_users_can_send_live_chat_without_auth_or_rate_limit(): void
    {
        Queue::fake();
        $event=$this->event();
        $this->post(route('public.message-submit',$event),['body'=>'Guest chat message'])->assertRedirect();
        $this->actingAs(User::factory()->create())->post(route('public.message-submit',$event),['body'=>'Member chat message'])->assertRedirect();
        $chat=SourceRecord::where('kind','live_chat')->get();
        $this->assertCount(2,$chat);
        $this->assertSame('Gast',$chat->first()->metadata['author']);
        $this->assertSame('guest',$chat->first()->metadata['author_type']);
        $this->assertSame('user',$chat->get(1)->metadata['author_type']);
        Queue::assertPushed(ModeratePublicContent::class,2);
    }

    public function test_ai_allows_respectful_messages_and_publishes_them(): void
    {
        Queue::fake();$this->enableAi();Http::fake(['api.openai.com/*'=>Http::response($this->aiResponse('allow',0.02))]);
        $this->post('/community',['title'=>'Hope','body'=>'Thank you for this encouraging discussion.','type'=>'discussion'])->assertRedirect();
        $record=SourceRecord::where('kind','post')->firstOrFail();
        (new ModeratePublicContent($record->id))->handle(app(PublicAiModerator::class),app(PublicCommunityModeration::class),app(\App\Services\Audit::class));
        $record=$record->fresh();
        $this->assertSame('ready',$record->status);
        $this->assertTrue($record->metadata['public_published']);
        $this->assertSame('published',$record->metadata['moderation']['state']);
        $this->get('/community')->assertSee('Hope');
    }

    public function test_ai_sends_suspicious_messages_to_the_human_desktop_queue(): void
    {
        Queue::fake();$this->enableAi();Http::fake(['api.openai.com/*'=>Http::response($this->aiResponse('review',0.94,['profanity']))]);
        $this->post('/community',['title'=>'Review me','body'=>'Suspicious content','type'=>'discussion'])->assertRedirect();
        $record=SourceRecord::where('kind','post')->firstOrFail();
        (new ModeratePublicContent($record->id))->handle(app(PublicAiModerator::class),app(PublicCommunityModeration::class),app(\App\Services\Audit::class));
        $record=$record->fresh();
        $this->assertSame('needs_attention',$record->status);
        $this->assertFalse($record->metadata['public_published']);
        $this->assertSame('human_review',$record->metadata['moderation']['state']);
        $this->assertSame(1,app(PublicCommunityModeration::class)->pending()->count());
        $this->get('/community')->assertDontSee('Review me');
    }

    public function test_moderation_is_a_protected_desktop_interface(): void
    {
        app(Access::class)->seed();
        $moderator=User::factory()->create();$moderator->roles()->attach(Role::where('name','Moderator')->firstOrFail());
        $record=SourceRecord::create(['source'=>'website','source_id'=>'pending','kind'=>'post','title'=>'Needs decision','body'=>'Message for a human','status'=>'needs_attention','metadata'=>['public_section'=>'community','website_community'=>true,'public_published'=>false,'author'=>'Gast','moderation'=>['state'=>'human_review','risk'=>0.9,'reasons'=>['profanity']]]]);
        $this->actingAs($moderator)->get('/desktop/community/moderation')->assertRedirect('/desktop?open=community');
        $this->actingAs($moderator)->get('/desktop')->assertOk()->assertSee('Community')->assertSee('Needs decision')->assertDontSee('public-navigation',false);
        $this->actingAs($moderator)->patch(route('desktop.community-moderate',$record),['decision'=>'publish'])->assertRedirect('/desktop?open=community');
        $this->assertTrue($record->fresh()->metadata['public_published']);
    }
}
