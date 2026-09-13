<?php
namespace Tests\Feature;
use App\Models\{User,Role,SourceRecord,PublicContentState};
use App\Jobs\VerifyPublicAccount;
use App\Services\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Queue,URL};
use Tests\TestCase;
class PublicAccountTest extends TestCase {
    use RefreshDatabase;
    public function test_staff_account_is_not_asked_to_verify_email_or_manage_media_in_public_account(): void {
        app(Access::class)->seed();$owner=User::factory()->create(['email_verified_at'=>null]);$owner->roles()->attach(Role::where('name','Owner')->firstOrFail());
        Queue::fake();$this->actingAs($owner)->get('/konto')->assertOk()->assertSee('Media Desktop öffnen')->assertDontSee('Meine Merkliste')->assertDontSee('Browser-Erinnerungen')->assertDontSee('Profil und Passwort')->assertDontSee('Bestätigung ausstehend')->assertDontSee('Bestätigung erneut senden');
        $this->post('/konto/verify')->assertRedirect();$this->patch('/konto/profile',['name'=>'Changed','current_password'=>'password','password'=>'new-password-123','password_confirmation'=>'new-password-123'])->assertNotFound();Queue::assertNotPushed(VerifyPublicAccount::class);
    }
    public function test_staff_cannot_create_public_content_states(): void {
        app(Access::class)->seed();$owner=User::factory()->create();$owner->roles()->attach(Role::where('name','Owner')->firstOrFail());
        $record=SourceRecord::create(['source'=>'website','source_id'=>'staff-state','kind'=>'video','title'=>'Staff video','status'=>'ready','metadata'=>['public_published'=>true]]);
        $this->actingAs($owner)->postJson(route('public.record-state',$record),['action'=>'bookmark'])->assertForbidden();
        $this->assertDatabaseMissing('public_content_states',['user_id'=>$owner->id,'subject_id'=>$record->id]);
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
        $this->delete('/konto/state/'.$state->id)->assertRedirect();$this->assertDatabaseMissing('public_content_states',['id'=>$state->id]);
    }
}
