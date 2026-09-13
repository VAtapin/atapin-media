<?php
namespace Tests\Feature;
use App\Models\{SourceRecord,User,PublicPushSubscription};
use App\Services\{PublicPush,Settings};
use App\Jobs\SendPublicPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
class PublicPushTest extends TestCase
{
    use RefreshDatabase;
    private function event(): SourceRecord {return SourceRecord::create(['source'=>'website','source_id'=>'live','kind'=>'video','title'=>'Live','status'=>'ready','metadata'=>['public_published'=>true,'public_section'=>'live','live_status'=>'scheduled','starts_at'=>now()->addMinutes(10)->toIso8601String()]]);}
    private function subscription(): array {return ['endpoint'=>'https://fcm.googleapis.com/fcm/send/example','keys'=>['p256dh'=>str_repeat('A',87),'auth'=>str_repeat('B',22)]];}
    public function test_push_requires_login_public_event_and_safe_endpoint_and_can_be_cancelled(): void
    {
        $event=$this->event();$url=route('public.live-push',$event);$data=$this->subscription();
        $this->postJson($url,$data)->assertUnauthorized();$this->actingAs(User::factory()->create());
        $this->postJson($url,[...$data,'endpoint'=>'https://127.0.0.1/private'])->assertUnprocessable();
        $this->postJson($url,$data)->assertUnprocessable();
        app(Settings::class)->update(['push_public_key'=>'public']);app(Settings::class)->updateSecrets(['push_private_key'=>'private']);
        $this->postJson($url,$data)->assertOk()->assertJson(['enabled'=>true]);
        $stored=\Illuminate\Support\Facades\DB::table('public_push_subscriptions')->first();$this->assertStringNotContainsString('fcm.googleapis.com',$stored->subscription);
        $this->postJson($url,[...$data,'mode'=>'status'])->assertJson(['enabled'=>true]);
        $this->postJson($url,$data)->assertJson(['enabled'=>false]);$this->assertSame(0,PublicPushSubscription::count());
        $event->update(['metadata'=>['public_published'=>false]]);$this->postJson($url,$data)->assertNotFound();
    }
    public function test_due_dispatch_skips_private_sent_failed_and_cancelled_subscriptions(): void
    {
        Queue::fake();$this->freezeTime();$event=$this->event();$user=User::factory()->create();
        app(Settings::class)->update(['push_public_key'=>'public']);app(Settings::class)->updateSecrets(['push_private_key'=>'private']);
        $this->actingAs($user)->postJson(route('public.live-push',$event),$this->subscription())->assertOk();
        $push=app(PublicPush::class);$this->assertSame(1,$push->dispatchDue());Queue::assertPushed(SendPublicPush::class);
        $subscription=PublicPushSubscription::firstOrFail();$subscription->update(['sent_for'=>now()->addMinutes(10)->timestamp]);$this->assertSame(0,$push->dispatchDue());
        $subscription->update(['sent_for'=>null,'failed_for'=>now()->addMinutes(10)->timestamp]);$this->assertSame(0,$push->dispatchDue());
        $subscription->update(['failed_for'=>null]);$event->update(['metadata'=>['public_published'=>false]]);$this->assertSame(0,$push->dispatchDue());
    }
    public function test_allowlist_rejects_redirect_credentials_ports_and_lookalike_hosts(): void
    {
        $push=app(PublicPush::class);
        foreach(['https://fcm.googleapis.com.evil.test/a','http://fcm.googleapis.com/a','https://user@fcm.googleapis.com/a','https://fcm.googleapis.com:8080/a','https://fcm.googleapis.com/a#fragment'] as $url)$this->assertFalse($push->validEndpoint($url));
        $this->assertTrue($push->validEndpoint('https://web.push.apple.com/example'));
    }
    public function test_delivery_job_records_success_and_does_not_send_after_cancellation(): void
    {
        $this->freezeTime();$event=$this->event();$data=$this->subscription();
        $subscription=PublicPushSubscription::create(['user_id'=>User::factory()->create()->id,'record_id'=>$event->id,'endpoint_hash'=>hash('sha256',$data['endpoint']),'subscription'=>$data]);
        $push=\Mockery::mock(PublicPush::class);
        $push->shouldReceive('send')->once()->andReturn(new \Minishlink\WebPush\MessageSentReport(new \GuzzleHttp\Psr7\Request('POST',$data['endpoint']),new \GuzzleHttp\Psr7\Response(201)));
        $job=new SendPublicPush($subscription->id,now()->addMinutes(10)->timestamp);
        $job->handle($push,app(\App\Services\PublicContent::class),app(\App\Services\PublicLiveReminders::class));
        $this->assertSame(now()->addMinutes(10)->timestamp,$subscription->fresh()->sent_for);
        $job->handle($push,app(\App\Services\PublicContent::class),app(\App\Services\PublicLiveReminders::class));
        $subscription->delete();$job->handle($push,app(\App\Services\PublicContent::class),app(\App\Services\PublicLiveReminders::class));
    }
}
