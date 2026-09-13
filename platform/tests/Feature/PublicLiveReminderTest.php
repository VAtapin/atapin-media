<?php
namespace Tests\Feature;
use App\Models\{SourceRecord,PublicContentState,User};
use App\Services\{PublicContent,PublicLiveReminders};
use App\Jobs\SendPublicLiveReminder;
use App\Mail\PublicLiveReminderMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Mail,Queue};
use Tests\TestCase;
class PublicLiveReminderTest extends TestCase
{
    use RefreshDatabase;
    private function event(): SourceRecord
    {
        config(['mail.default'=>'smtp']);$this->freezeTime();
        return SourceRecord::create(['source'=>'website','source_id'=>'live-event','kind'=>'video','title'=>'Scheduled event','status'=>'ready','metadata'=>['public_published'=>true,'public_section'=>'live','live_status'=>'scheduled','starts_at'=>now()->addMinutes(10)->toIso8601String()]]);
    }
    public function test_reminders_require_smtp_a_future_event_and_authentication(): void
    {
        $event=$this->event();$url=route('public.record-state',$event);$user=User::factory()->create();
        $this->postJson($url,['action'=>'reminder'])->assertUnauthorized();
        config(['mail.default'=>'log']);$this->actingAs($user)->postJson($url,['action'=>'reminder'])->assertUnprocessable();
        config(['mail.default'=>'smtp']);$this->postJson($url,['action'=>'reminder'])->assertOk();
        $state=PublicContentState::firstOrFail();$this->assertSame('email',$state->value['channel']);
        $this->postJson($url,['action'=>'reminder','enabled'=>false])->assertOk();$this->assertFalse($state->fresh()->value['enabled']);
    }
    public function test_due_reminders_send_local_links_once_and_recheck_cancelled_or_private_events(): void
    {
        Mail::fake();Queue::fake();$event=$this->event();$user=User::factory()->create();
        $this->actingAs($user)->postJson(route('public.record-state',$event),['action'=>'reminder'])->assertOk();
        $state=PublicContentState::firstOrFail();$service=app(PublicLiveReminders::class);$content=app(PublicContent::class);
        $this->assertSame(1,$service->dispatchDue());Queue::assertPushed(SendPublicLiveReminder::class);
        $job=new SendPublicLiveReminder($state->id,$service->time($event)->timestamp);$job->handle($content,$service);$job->handle($content,$service);
        Mail::assertSent(PublicLiveReminderMail::class,1);Mail::assertSent(PublicLiveReminderMail::class,fn($mail)=>str_contains($mail->eventUrl,'/live?event=')&&!str_contains($mail->eventUrl,'youtube'));
        $this->assertSame(0,$service->dispatchDue());
        $state->update(['value'=>['enabled'=>false]]);$job->handle($content,$service);Mail::assertSentCount(1);
        $state->update(['value'=>['enabled'=>true]]);$event->update(['metadata'=>[...$event->metadata,'public_published'=>false]]);$job->handle($content,$service);Mail::assertSentCount(1);
    }
    public function test_rescheduled_events_and_terminal_failures_are_not_sent_by_old_jobs(): void
    {
        Mail::fake();Queue::fake();$event=$this->event();$user=User::factory()->create();
        $this->actingAs($user)->postJson(route('public.record-state',$event),['action'=>'reminder'])->assertOk();$state=PublicContentState::firstOrFail();$service=app(PublicLiveReminders::class);
        $job=new SendPublicLiveReminder($state->id,$service->time($event)->timestamp);
        $event->update(['metadata'=>[...$event->metadata,'starts_at'=>now()->addMinutes(30)->toIso8601String()]]);
        $job->handle(app(PublicContent::class),$service);Mail::assertNothingSent();$this->assertSame(0,$service->dispatchDue());
        $event->update(['metadata'=>[...$event->metadata,'starts_at'=>now()->addMinutes(10)->toIso8601String()]]);$job->failed(new \RuntimeException('test failure'));$this->assertSame(0,$service->dispatchDue());
    }
}
