<?php
namespace Tests\Feature;
use App\Models\{User,SourceRecord,Role};
use App\Services\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class PublicCommunityTest extends TestCase {
    use RefreshDatabase;
    public function test_questions_require_verified_accounts_and_moderation(): void {
        $data=['title'=>'Visitor question','body'=>'A real visitor question','type'=>'question'];
        $this->post('/community',$data)->assertRedirect('/login');
        $reader=User::factory()->create(['email_verified_at'=>null]);$this->actingAs($reader)->post('/community',$data)->assertForbidden();
        $reader->forceFill(['email_verified_at'=>now()])->save();$this->post('/community',$data)->assertRedirect();$record=SourceRecord::firstOrFail();
        $this->get('/community')->assertDontSee('Visitor question');$this->patch('/desktop/community/moderation/'.$record->id,['decision'=>'publish'])->assertForbidden();
        app(Access::class)->seed();$moderator=User::factory()->create();$moderator->roles()->attach(Role::where('name','Moderator')->firstOrFail());
        $this->actingAs($moderator)->get('/desktop/community/moderation')->assertOk()->assertSee('Visitor question');$this->patch('/desktop/community/moderation/'.$record->id,['decision'=>'publish'])->assertRedirect();$this->get('/community')->assertSee('Visitor question');
        $this->patch('/desktop/community/moderation/'.$record->id,['decision'=>'reject'])->assertRedirect();$this->get('/community')->assertDontSee('Visitor question');
    }
}
