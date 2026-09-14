<?php
namespace Tests\Feature;
use App\Models\{User,Role,SourceRecord,Product,Sale,BookReview,NewsletterSubscription,PublicAiChatRequest,PublicContentState};
use App\Jobs\VerifyPublicAccount;
use App\Services\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Queue,URL};
use Tests\TestCase;
class PublicAccountTest extends TestCase {
    use RefreshDatabase;
    public function test_staff_account_is_not_asked_to_verify_email_or_manage_media_in_public_account(): void {
        app(Access::class)->seed();$owner=User::factory()->create(['email_verified_at'=>null]);$owner->roles()->attach(Role::where('name','Owner')->firstOrFail());
        Queue::fake();$response=$this->actingAs($owner)->get('/konto')->assertOk()->assertSee('Media Desktop öffnen')->assertViewHas('isStaffAccount',true);
        $dom=new \DOMDocument();@$dom->loadHTML($response->getContent());$visible=$dom->getElementsByTagName('main')->item(0)->textContent;
        // Global JS translations are not visible account sections.
        foreach(['Meine Merkliste','Browser-Erinnerungen','Profil und Passwort','Bestätigung ausstehend','Bestätigung erneut senden'] as $label)$this->assertStringNotContainsString($label,$visible);
        $response->assertDontSee('action="'.route('public.account-profile').'"',false);
        $this->post('/konto/verify')->assertRedirect();$this->patch('/konto/profile',['name'=>'Changed','current_password'=>'password','password'=>'new-password-123','password_confirmation'=>'new-password-123'])->assertNotFound();Queue::assertNotPushed(VerifyPublicAccount::class);
    }
    public function test_staff_cannot_create_public_content_states(): void {
        app(Access::class)->seed();$owner=User::factory()->create();$owner->roles()->attach(Role::where('name','Owner')->firstOrFail());
        $record=SourceRecord::create(['source'=>'website','source_id'=>'staff-state','kind'=>'video','title'=>'Staff video','status'=>'ready','metadata'=>['public_published'=>true]]);
        $this->actingAs($owner)->postJson(route('public.record-state',$record),['action'=>'bookmark'])->assertForbidden();
        $this->assertDatabaseMissing('public_content_states',['user_id'=>$owner->id,'subject_id'=>$record->id]);
    }
    public function test_regular_account_shows_personal_history_sections(): void {
        $user=User::factory()->create(['email'=>'reader@example.test']);$book=Product::create(['title'=>'Meine gekaufte Lektüre','description'=>'Book','price_cents'=>990,'currency'=>'EUR','status'=>'active']);
        Sale::create(['product_id'=>$book->id,'customer_email'=>$user->email,'amount_cents'=>990,'currency'=>'EUR','status'=>'paid','provider'=>'test','paid_at'=>now()]);
        NewsletterSubscription::create(['email'=>$user->email,'locale'=>'de','token_hash'=>hash('sha256','newsletter'),'status'=>'active','delivery_status'=>'sent','consented_at'=>now(),'confirmed_at'=>now()]);
        $parent=SourceRecord::create(['source'=>'website','source_id'=>'account-parent','kind'=>'post','title'=>'Meine Diskussion','body'=>'Eine öffentliche Diskussion','status'=>'ready','metadata'=>['public_section'=>'community','public_published'=>true]]);
        SourceRecord::create(['source'=>'website','source_id'=>'account-comment','kind'=>'comment','title'=>'Meine Antwort','body'=>'Meine persönliche Nachricht','status'=>'ready','metadata'=>['parent_source_id'=>$parent->source_id,'author_user_id'=>$user->id,'public_published'=>true]]);
        BookReview::create(['product_id'=>$book->id,'user_id'=>$user->id,'rating'=>5,'body'=>'Meine Rezension','status'=>'pending']);
        PublicAiChatRequest::create(['user_id'=>$user->id,'record_id'=>$parent->id,'question'=>'Meine Frage','answer'=>'Meine Antwort','status'=>'completed']);
        $response=$this->actingAs($user)->get('/konto');$response->assertOk()->assertSee('Meine Käufe')->assertSee('Meine Abonnements')->assertSee('Meine Beiträge und Nachrichten')->assertSee('Meine Antwort')->assertSee('Meine Buchrezensionen')->assertSee('Meine Rezension')->assertSee('Meine Fragen an den Assistenten')->assertSee('Meine Frage')->assertDontSee('public.account_');
        $response->assertViewHas('messages',fn($history)=>method_exists($history,'perPage')&&$history->perPage()===10)->assertViewHas('reviews',fn($history)=>method_exists($history,'perPage')&&$history->perPage()===10)->assertViewHas('aiChats',fn($history)=>method_exists($history,'perPage')&&$history->perPage()===10);
    }
    public function test_registration_verifies_email_without_granting_admin_permissions(): void {
        Queue::fake();$this->get('/registrieren')->assertOk();
        $this->post('/registrieren',['name'=>'Reader','email'=>'reader@example.test','password'=>'long-password-123','password_confirmation'=>'long-password-123','consent'=>true])->assertRedirect('/konto');
        $user=User::firstOrFail();$this->assertNull($user->email_verified_at);$this->assertFalse($user->hasPermission('desktop.view'));
        Queue::assertPushed(VerifyPublicAccount::class);$this->get('/konto')->assertOk();$this->get('/desktop')->assertForbidden();$this->post('/desktop/media')->assertForbidden();
        $link=URL::temporarySignedRoute('public.account-verify',now()->addHour(),['user'=>$user->id,'hash'=>sha1($user->email)]);
        $this->get($link)->assertRedirect('/konto');$this->assertNotNull($user->fresh()->email_verified_at);
    }
    public function test_account_shows_only_own_public_items_and_guards_removal(): void {
        $mine=User::factory()->create();$other=User::factory()->create();
        $record=SourceRecord::create(['source'=>'website','source_id'=>'public','kind'=>'post','title'=>'Public article','status'=>'ready','metadata'=>['public_published'=>true]]);
        $state=PublicContentState::create(['user_id'=>$mine->id,'subject_type'=>'record','subject_id'=>$record->id,'action'=>'bookmark','value'=>['enabled'=>true]]);
        PublicContentState::create(['user_id'=>$mine->id,'subject_type'=>'record','subject_id'=>$record->id,'action'=>'progress','value'=>['position'=>42]]);
        $this->actingAs($other)->get('/konto')->assertOk()->assertDontSee('Public article');$this->delete('/konto/state/'.$state->id)->assertNotFound();
        $this->actingAs($mine)->get('/konto')->assertOk()->assertSee('Public article')->assertSee('Fortschritt')->assertSee('00:42')->assertDontSee('public.progress')->assertDontSee('{"position":42}');
        $record->update(['metadata'=>['public_published'=>false]]);$this->get('/konto')->assertDontSee('Public article');
        $this->deleteJson('/konto/state/'.$state->id)->assertOk()->assertJson(['kind'=>'account-remove']);$this->assertDatabaseMissing('public_content_states',['id'=>$state->id]);
    }
}
