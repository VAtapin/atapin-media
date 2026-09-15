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
        $this->get('/ueber-uns')->assertOk()->assertSee(__('public.about_intro_title'))->assertSee('public-about-page',false);
        app(Settings::class)->update(['legal_documents'=>['de'=>['about_text'=>'<p>Database about</p>','mission_text'=>'<p>Database mission</p>']]]);
        $this->get('/ueber-uns')->assertOk()->assertSee('<p>Database about</p>',false)->assertDontSee('&lt;p&gt;',false);
        $this->get('/unsere-mission')->assertOk()->assertSee('<p>Database mission</p>',false);
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
    public function test_information_page_editor_preserves_safe_word_like_formatting(): void
    {
        app(Access::class)->seed();$owner=User::factory()->create();$owner->roles()->attach(Role::where('name','Owner')->firstOrFail());$this->actingAs($owner);
        $this->putJson('/desktop/settings',[
            'section'=>'system','site_name'=>'Test site','system_locale'=>'de','system_timezone'=>'Europe/Berlin','legal_locale'=>'de',
            'about_text'=>'<h2>Über uns</h2><p style="text-align: right; color: red">Text</p><table><tr><td>Erster</td></tr></table><script>alert(1)</script>',
            'community_guidelines'=>'<h2>Gemeinschaft</h2><p>Bitte freundlich bleiben</p><script>bad()</script>',
        ])->assertOk();
        $stored=app(Settings::class)->get('legal_documents')['de']['about_text'];
        $this->assertStringContainsString('<h2>Über uns</h2>',$stored);
        $this->assertStringContainsString('text-align: right',$stored);
        $this->assertStringNotContainsString('color: red',$stored);
        $this->get('/ueber-uns')->assertOk()->assertSee('<h2>Über uns</h2>',false)->assertSee('<table>',false)
            ->assertDontSee('&lt;h2&gt;',false)->assertDontSee('alert(1)');
        $this->get('/community')->assertOk()->assertSee('<h2>Gemeinschaft</h2>',false)->assertDontSee('bad()');
    }
}
