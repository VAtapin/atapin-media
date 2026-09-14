<?php
namespace Tests\Feature;
use App\Models\{User,Role,Permission,SourceRecord,Task,DesktopAiRequest};
use App\Services\{Access,Settings,EditorialRecommendations,DesktopAi};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Queue,Http};
use Tests\TestCase;
class EditorialRecommendationsTest extends TestCase
{
    use RefreshDatabase;
    public function test_permission_scoped_facts_are_bounded_and_generated_without_mutations():void
    {
        app(Access::class)->seed();Queue::fake();Http::preventStrayRequests();$role=Role::create(['name'=>'Recommendations only']);$role->permissions()->attach(Permission::whereIn('name',['content.edit','desktop.view'])->pluck('id'));$user=User::factory()->create();$user->roles()->attach($role);$this->actingAs($user);Task::create(['title'=>'Private project task','status'=>'open','priority'=>'high']);
        for($i=0;$i<32;$i++)SourceRecord::create(['source'=>'manual','source_id'=>(string)$i,'kind'=>'post','title'=>'Draft '.$i,'body'=>'Secret body not needed','status'=>'review','metadata'=>['public_published'=>false]]);
        $context=app(EditorialRecommendations::class)->context($user);$this->assertCount(30,$context['content']);$this->assertTrue($context['content_limited']);$this->assertArrayNotHasKey('tasks',$context);$this->assertStringNotContainsString('Secret body',json_encode($context));
        app(Settings::class)->update(['ai_enabled'=>true,'ai_provider'=>'openai','ai_model'=>'test-model']);app(Settings::class)->updateSecrets(['ai_api_key'=>'test-not-real']);$id=$this->postJson('/desktop/assistant',['purpose'=>'prioritize','question'=>'What next?'])->assertAccepted()->json('id');$proposal=['answer'=>'Review draft #1 first.','title'=>'','short_description'=>'','seo_title'=>'','seo_description'=>'','social_text'=>''];Http::fake(['api.openai.com/*'=>Http::response(['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode($proposal)]]]]])]);(new \App\Jobs\AnswerDesktopAi($id))->handle(app(DesktopAi::class));$this->assertSame('completed',DesktopAiRequest::findOrFail($id)->status);Http::assertSent(fn($r)=>$r['store']===false&&$r['text']['format']['strict']===true&&str_contains($r['input'],'content_limited')&&!str_contains($r['input'],'Private project task'));$this->postJson('/desktop/assistant/'.$id.'/apply')->assertUnprocessable();$this->assertSame(32,SourceRecord::where('status','review')->count());
        $this->postJson('/desktop/assistant',['purpose'=>'prioritize','question'=>'What next?','source_record_id'=>1])->assertUnprocessable();
    }
}
