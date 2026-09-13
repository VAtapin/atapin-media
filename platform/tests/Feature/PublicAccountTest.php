<?php
namespace Tests\Feature;
use App\Models\{User,SourceRecord,PublicContentState};
use App\Jobs\VerifyPublicAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Queue,URL};
use Tests\TestCase;
class PublicAccountTest extends TestCase {
    use RefreshDatabase;
    public function test_registration_verifies_email_without_granting_admin_permissions(): void {
        Queue::fake();$this->get('/registrieren')->assertOk();
        $this->post('/registrieren',['name'=>'Reader','email'=>'reader@example.test','password'=>'long-password-123','password_confirmation'=>'long-password-123','consent'=>true])->assertRedirect('/konto');
        $user=User::firstOrFail();$this->assertNull($user->email_verified_at);$this->assertFalse($user->hasPermission('desktop.view'));
        Queue::assertPushed(VerifyPublicAccount::class);$this->get('/konto')->assertOk();$this->get('/desktop')->assertForbidden();
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
