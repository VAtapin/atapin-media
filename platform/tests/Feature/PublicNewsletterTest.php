<?php
namespace Tests\Feature;
use App\Models\NewsletterSubscription;
use App\Jobs\ConfirmNewsletterSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Queue,URL};
use Tests\TestCase;
class PublicNewsletterTest extends TestCase {
    use RefreshDatabase;
    public function test_double_opt_in_and_cancellation_require_valid_signed_links(): void {
        Queue::fake();$this->postJson('/newsletter',['email'=>'reader@example.test'])->assertUnprocessable();
        $this->from('/videos')->post('/newsletter',['email'=>'reader@example.test','consent'=>true])->assertRedirect('/videos');
        $entry=NewsletterSubscription::firstOrFail();$this->assertSame('pending',$entry->status);
        Queue::assertPushed(ConfirmNewsletterSubscription::class,function($job)use($entry){
            $this->assertSame(hash('sha256',$job->token),$entry->token_hash);
            $url=URL::temporarySignedRoute('public.newsletter-confirm',now()->addHour(),['subscription'=>$entry->id,'token'=>$job->token]);
            $this->get($url)->assertRedirect('/');$this->assertSame('active',$entry->fresh()->status);
            $cancel=URL::signedRoute('public.newsletter-cancel',['subscription'=>$entry->id,'token'=>$job->token]);
            $this->get($cancel)->assertOk();$this->assertSame('active',$entry->fresh()->status);
            $this->post($cancel)->assertRedirect('/');$this->assertSame('unsubscribed',$entry->fresh()->status);return true;
        });
        $this->get('/newsletter/'.$entry->id.'/confirm?token=wrong')->assertForbidden();
    }
}
