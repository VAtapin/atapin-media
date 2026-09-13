<?php
namespace Tests\Feature;
use App\Models\{ContactMessage,Role,User};
use App\Services\{Access,Settings};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class PublicContactTest extends TestCase
{
    use RefreshDatabase;
    public function test_information_pages_use_localized_database_content_and_keep_empty_states(): void
    {
        $this->get('/ueber-uns')->assertOk()->assertSee(__('public.section_pending'));
        app(Settings::class)->update(['legal_documents'=>['de'=>['about_text'=>'<p>Database about</p>','mission_text'=>'<p>Database mission</p>']]]);
        $this->get('/ueber-uns')->assertOk()->assertSee('Database about');
        $this->get('/unsere-mission')->assertOk()->assertSee('Database mission');
    }
    public function test_contact_form_saves_an_enquiry_without_publishing_private_details(): void
    {
        $this->get('/kontakt')->assertOk()->assertSee('name="privacy"',false);
        $data=['name'=>'Visitor','email'=>'visitor@example.test','subject'=>'Private enquiry','body'=>'Private enquiry body'];
        $this->postJson('/kontakt',$data)->assertUnprocessable();
        $this->postJson('/kontakt',[...$data,'privacy'=>true,'website'=>'spam'])->assertUnprocessable();
        $this->post('/kontakt',[...$data,'privacy'=>true])->assertRedirect('/kontakt');
        $this->assertDatabaseHas('contact_messages',['email'=>'visitor@example.test','subject'=>'Private enquiry']);
        $this->get('/community')->assertDontSee('Private enquiry');
        $this->getJson('/desktop/contact-messages')->assertUnauthorized();
        $this->assertSame(1,ContactMessage::count());
    }
    public function test_settings_editor_saves_and_clears_information_pages_and_inbox_requires_permission(): void
    {
        app(Access::class)->seed();$user=User::factory()->create();
        $this->actingAs($user)->getJson('/desktop/contact-messages')->assertForbidden();
        $user->roles()->attach(Role::where('name','Owner')->firstOrFail());$user->unsetRelation('roles');
        $data=['section'=>'system','site_name'=>'Test site','system_locale'=>'de','system_timezone'=>'Europe/Berlin','legal_locale'=>'de','about_text'=>'<p>About edited</p><script>secret()</script>','mission_text'=>'Mission edited'];
        $this->putJson('/desktop/settings',$data)->assertOk();
        $this->get('/ueber-uns')->assertSee('About edited')->assertDontSee('secret()');
        $this->get('/unsere-mission')->assertSee('Mission edited');
        $this->putJson('/desktop/settings',[...$data,'about_text'=>''])->assertOk();
        $this->get('/ueber-uns')->assertDontSee('About edited');
        $this->getJson('/desktop/contact-messages')->assertOk()->assertJsonPath('total',0);
    }
}
