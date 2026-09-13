<?php
namespace Tests\Feature;
use App\Models\User;
use App\Jobs\ResetPublicPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Queue,Hash};
use Tests\TestCase;
class PublicPasswordTest extends TestCase {
    use RefreshDatabase;
    public function test_reset_uses_existing_broker_and_queue_without_disclosing_accounts(): void {
        Queue::fake();$user=User::factory()->create(['email'=>'reader@example.test']);
        $this->get('/passwort-vergessen')->assertOk();$this->from('/passwort-vergessen')->post('/passwort-vergessen',['email'=>$user->email])->assertSessionHas('public_status',__('public.password_reset_requested'));
        Queue::assertPushed(ResetPublicPassword::class,function($job)use($user){$data=['email'=>$user->email,'token'=>$job->token,'password'=>'new-long-password-123','password_confirmation'=>'new-long-password-123'];$this->get('/passwort-zuruecksetzen/'.$job->token.'?email='.$user->email)->assertOk();$this->post('/passwort-zuruecksetzen',$data)->assertRedirect('/login');$this->assertTrue(Hash::check($data['password'],$user->fresh()->password));$this->post('/passwort-zuruecksetzen',$data)->assertSessionHasErrors();return true;});
        $this->travel(2)->minutes();$this->post('/passwort-vergessen',['email'=>'unknown@example.test'])->assertSessionHas('public_status',__('public.password_reset_requested'));
    }
}
