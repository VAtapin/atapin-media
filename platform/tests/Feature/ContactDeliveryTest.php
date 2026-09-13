<?php
namespace Tests\Feature;
use App\Models\ContactMessage;
use App\Services\{Settings,PublicLiveReminders};
use App\Jobs\ForwardContactMessage;
use App\Mail\ContactEnquiryMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Mail,Queue};
use Tests\TestCase;
class ContactDeliveryTest extends TestCase
{
    use RefreshDatabase;
    public function test_contact_is_queued_and_delivered_to_settings_email_once(): void
    {
        Mail::fake();Queue::fake();config(['mail.default'=>'sendmail']);
        app(Settings::class)->update(['contact_email'=>'owner@example.test']);
        $this->post('/kontakt',['name'=>'Visitor','email'=>'visitor@example.test','subject'=>'Question','body'=>'A private question','privacy'=>true])->assertRedirect();
        $message=ContactMessage::firstOrFail();Queue::assertPushed(ForwardContactMessage::class);
        $job=new ForwardContactMessage($message->id);$job->handle(app(Settings::class),app(PublicLiveReminders::class));$job->handle(app(Settings::class),app(PublicLiveReminders::class));
        Mail::assertSent(ContactEnquiryMail::class,fn($mail)=>$mail->hasTo('owner@example.test'));
        Mail::assertSentCount(1);$this->assertSame('sent',$message->fresh()->delivery_status);
    }
    public function test_missing_configuration_and_failure_are_visible_and_retry_is_protected(): void
    {
        Mail::fake();$message=ContactMessage::create(['name'=>'Visitor','email'=>'visitor@example.test','subject'=>'Question','body'=>'Private body','consented_at'=>now()]);
        $job=new ForwardContactMessage($message->id);$job->handle(app(Settings::class),app(PublicLiveReminders::class));
        $this->assertSame('configuration_required',$message->fresh()->delivery_status);Mail::assertNothingSent();
        $job->failed(new \RuntimeException('Private transport detail'));$this->assertSame('failed',$message->fresh()->delivery_status);
        $this->postJson(route('contact.retry',$message))->assertUnauthorized();
        $this->actingAs(\App\Models\User::factory()->create())->postJson(route('contact.retry',$message))->assertForbidden();
    }
}
