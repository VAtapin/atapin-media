<?php
namespace Tests\Feature;
use App\Models\User;
use App\Models\Role;
use App\Models\Media;
use App\Services\Access;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
class FoundationTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp(); config(['app.locale'=>'de','platform.brand'=>'Manna Vom Himmel']);
        app()->setLocale('de'); app(Access::class)->seed();
    }
    private function user(string $role): User
    {
        $user = User::factory()->create(['password'=>'kurz5']);
        $user->roles()->attach(Role::where('name',$role)->firstOrFail()); return $user;
    }
    public function test_private_routes_require_authentication_and_permissions(): void
    {
        $this->get('/desktop')->assertRedirect('/login');
        $user = $this->user('Moderator');
        $this->actingAs($user)->get('/desktop')->assertOk()
            ->assertDontSee('jobs_waiting')
            ->assertSee('settings-app-template', false)->assertSee('Mein Profil');
        $this->put('/desktop/settings',['site_name'=>'changed'])->assertForbidden();
    }
    public function test_short_password_login_logout_and_throttling(): void
    {
        $user = $this->user('Owner');
        $this->post('/login',['email'=>$user->email,'password'=>'kurz5'])->assertRedirect('/desktop');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/login'); $this->assertGuest();
        for ($i=0; $i<5; $i++) $this->post('/login',['email'=>$user->email,'password'=>'wrong'])->assertSessionHasErrors('email');
        $this->post('/login',['email'=>$user->email,'password'=>'kurz5'])->assertSessionHasErrors('email');
        $this->assertGuest(); $this->assertDatabaseHas('audit_events',['action'=>'auth.logout']);
    }
    public function test_upload_search_rename_download_and_untrusted_preview(): void
    {
        Storage::fake('local'); $this->actingAs($this->user('Owner'));
        $file = UploadedFile::fake()->createWithContent('Notizen.html','<script>alert(1)</script>');
        $this->post('/desktop/media',['file'=>$file])->assertRedirect();
        $media = Media::firstOrFail(); Storage::disk('local')->assertExists($media->path);
        $this->get('/desktop/media/'.$media->id.'/preview')->assertStatus(415);
        $this->get('/desktop/media/'.$media->id.'/download')->assertOk()->assertDownload('Notizen.html');
        $this->patch('/desktop/media/'.$media->id,['title'=>'Hoffnung'])->assertRedirect();
        $this->assertDatabaseHas('audit_events',['action'=>'media.uploaded','subject'=>$media->id]);
    }
    public function test_settings_invalidate_cache_and_escape_public_text(): void
    {
        $settings=app(Settings::class); $settings->all();
        $this->actingAs($this->user('Owner'))->put('/desktop/settings',[
            'site_name'=>'<script>bad()</script>','site_description'=>'Text','contact_email'=>'owner@example.com',
            'desktop_icon_set'=>'manna','desktop_wallpaper'=>'mountains','desktop_accent'=>'gold'])->assertRedirect();
        $this->assertSame('<script>bad()</script>',$settings->get('site_name'));
        $this->assertSame('manna',$settings->get('desktop_icon_set'));
        $this->get('/')->assertOk()->assertSee('&lt;script&gt;bad()&lt;/script&gt;',false)->assertDontSee('<script>bad()</script>',false);
    }
    public function test_desktop_appearance_requires_known_options(): void
    {
        $this->actingAs($this->user('Owner'))->put('/desktop/settings',[
            'site_name'=>'Manna Vom Himmel', 'desktop_icon_set'=>'unknown', 'desktop_wallpaper'=>'space', 'desktop_accent'=>'red',
        ])->assertSessionHasErrors(['desktop_icon_set','desktop_wallpaper','desktop_accent']);
        $this->actingAs($this->user('Owner'))->put('/desktop/settings',[
            'site_name'=>'Manna Vom Himmel', 'desktop_icon_set'=>'standard', 'desktop_wallpaper'=>'navy', 'desktop_accent'=>'sky',
        ])->assertRedirect();
        $this->assertSame('standard', app(Settings::class)->get('desktop_icon_set'));
        $this->get('/desktop')->assertOk()
            ->assertSee('data-wallpaper="navy"', false)
            ->assertSee('/assets/brand/owner/desktop/standard/Videos.png', false);
    }
    public function test_custom_desktop_wallpaper_is_validated_and_served_to_desktop_users(): void
    {
        Storage::fake('local');
        $this->actingAs($this->user('Owner'))->put('/desktop/settings',[
            'site_name'=>'Manna Vom Himmel', 'desktop_icon_set'=>'green', 'desktop_wallpaper'=>'custom', 'desktop_accent'=>'gold',
        ])->assertSessionHasErrors('desktop_custom_wallpaper');
        $file = UploadedFile::fake()->createWithContent('wallpaper.png', file_get_contents(public_path('assets/brand/owner/desktop/wallpapers/desktop1.png')));
        $this->actingAs($this->user('Owner'))->put('/desktop/settings',[
            'site_name'=>'Manna Vom Himmel', 'desktop_icon_set'=>'sol', 'desktop_wallpaper'=>'custom', 'desktop_accent'=>'sky',
            'desktop_custom_wallpaper'=>$file,
        ])->assertRedirect();
        $path = app(Settings::class)->get('desktop_custom_wallpaper');
        Storage::disk('local')->assertExists($path);
        $this->get('/desktop/wallpaper')->assertOk()->assertHeader('X-Content-Type-Options','nosniff');
        $this->get('/desktop')->assertOk()
            ->assertSee('data-wallpaper="custom"', false)
            ->assertSee('/desktop/wallpaper', false)
            ->assertSee('/assets/brand/owner/desktop/sol/Videos.png', false);
    }
    public function test_desktop_is_the_only_internal_application_shell(): void
    {
        $this->get('/login')->assertOk()->assertSee('Anmelden')->assertDontSee('ui.login');
        $this->actingAs($this->user('Owner'));
        $this->get('/desktop')->assertOk()->assertDontSee('<iframe', false);
        foreach (['/desktop/media','/desktop/settings','/desktop/users','/desktop/projects','/desktop/tasks','/desktop/imports','/desktop/shop'] as $url) $this->get($url)->assertStatus(405);
        foreach (['/desktop/calendar','/desktop/audit'] as $url) $this->get($url)->assertNotFound();
    }
    public function test_role_seeding_is_idempotent(): void
    {
        app(Access::class)->seed(); $this->assertDatabaseCount('roles',6);
        $editor=$this->user('Editor');
        $this->assertTrue($editor->hasPermission('content.publish'));
        $this->assertFalse($editor->hasPermission('users.manage'));
    }
    public function test_settings_sections_store_runtime_values_and_encrypt_secrets(): void
    {
        $this->actingAs($this->user('Owner'));
        $this->put('/desktop/settings', ['section'=>'ai','ai_provider'=>'openai','ai_model'=>'gpt-test','ai_enabled'=>'1','ai_api_key'=>'secret-value'])->assertRedirect();
        $this->assertSame('openai', app(Settings::class)->get('ai_provider'));
        $this->assertSame('secret-value', app(Settings::class)->secret('ai_api_key'));
        $this->assertDatabaseMissing('settings', ['key'=>'secret.ai_api_key','value'=>json_encode('secret-value')]);
        $this->putJson('/desktop/settings', ['section'=>'social','provider'=>'youtube','public_url'=>'https://youtube.com/@manna','external_id'=>'manna','api_key'=>'youtube-secret'])
            ->assertOk()->assertJson(['status'=>'saved','section'=>'social','provider'=>'youtube']);
        $this->assertSame('https://youtube.com/@manna', app(Settings::class)->get('social_connections')['youtube']['public_url']);
        $this->assertStringContainsString('youtube-secret', app(Settings::class)->secret('social_youtube'));
        $this->putJson('/desktop/settings', ['section'=>'integrations','provider'=>'stripe','account_id'=>'acct_123','api_key'=>'stripe-secret'])
            ->assertOk()->assertJson(['status'=>'saved','section'=>'integrations','provider'=>'stripe']);
        $this->assertSame('acct_123', app(Settings::class)->get('integration_connections')['stripe']['external_id']);
        $this->put('/desktop/settings', ['section'=>'publishing','publishing_default_visibility'=>'internal','publishing_default_timezone'=>'Europe/Berlin','publishing_approval_required'=>'1'])->assertRedirect();
        $this->assertTrue(app(Settings::class)->get('publishing_approval_required'));
        $this->putJson('/desktop/settings', ['section'=>'desktop_design','desktop_icon_set'=>'manna','desktop_wallpaper'=>'navy','desktop_accent'=>'gold','desktop_density'=>'comfortable','desktop_effects'=>true])
            ->assertOk()->assertJson(['status'=>'saved','section'=>'desktop_design']);
        $this->assertSame('navy', app(Settings::class)->get('desktop_wallpaper'));
        $this->putJson('/desktop/settings', ['section'=>'system','site_name'=>'Manna','system_locale'=>'de','system_timezone'=>'Europe/Berlin','legal_locale'=>'de','impressum'=>'<p onclick="alert(1)">Impressum</p><script>alert(1)</script>','privacy_policy'=>'<p>Datenschutz</p>','editorial_policy'=>'<p>Redaktion</p>'])
            ->assertOk()->assertJson(['status'=>'saved','section'=>'system']);
        $this->assertSame('<p>Impressum</p>', app(Settings::class)->get('legal_documents')['de']['impressum']);
        $this->putJson('/desktop/settings', ['section'=>'system','site_name'=>'Manna','system_locale'=>'de','system_timezone'=>'Europe/Berlin','legal_locale'=>'en','impressum'=>'<p>Imprint</p>'])
            ->assertOk()->assertJson(['status'=>'saved','section'=>'system']);
        $this->assertSame('<p>Impressum</p>', app(Settings::class)->get('legal_documents')['de']['impressum']);
        $this->assertSame('<p>Imprint</p>', app(Settings::class)->get('legal_documents')['en']['impressum']);
        $this->get('/desktop')->assertOk()->assertSee('desktop-settings', false)
            ->assertDontSee('/assets/app.css', false)->assertDontSee('<iframe', false);
    }
    public function test_desktop_includes_settings_directly(): void
    {
        $owner = $this->user('Owner'); $this->actingAs($owner);
        $this->get('/desktop/settings')->assertStatus(405);
        $this->get('/desktop/shop')->assertStatus(405);
        $desktop = $this->get('/desktop')->assertOk();
        foreach (['Videos','Beiträge','Bücher & PDF','Podcast','Live Studio','Media Library','Projekte','Aufgaben','Kalender','Community','Newsletter','Themen & Kategorien','Publishing','Shop & Verkäufe','KI-Assistent','Analytics','Import Center','Integrationen','Einstellungen'] as $name) $desktop->assertSee($name);
        $desktop->assertSee('desktop-settings', false)->assertSee('/assets/desktop-settings.css?v=3', false)
            ->assertSee($owner->email)->assertSee('data-user-cancel', false)->assertSee('data-user-create-cancel', false)
            ->assertDontSee('/assets/app.css', false)->assertDontSee('<iframe', false);
        $desktop->assertDontSee('>Subscribers<', false)->assertDontSee('>Bilder<', false)->assertDontSee('>Audio<', false)->assertDontSee('>Dateien<', false);
    }
    public function test_last_owner_cannot_be_demoted_and_short_password_account_can_be_created():void
    {
        $owner=$this->user('Owner');$this->actingAs($owner);$role=Role::where('name','Editor')->firstOrFail();
        $this->patch('/desktop/users/'.$owner->id,['name'=>$owner->name,'email'=>$owner->email,'role_id'=>$role->id])->assertSessionHasErrors('role_id');
        $this->assertTrue($owner->fresh()->hasPermission('users.manage'));
        $this->post('/desktop/users',['name'=>'Editor','email'=>'editor@example.com','password'=>'12345','role_id'=>$role->id])->assertRedirect();
        $this->assertTrue(User::where('email','editor@example.com')->first()->hasPermission('content.edit'));
    }
    public function test_user_can_update_personal_profile_without_changing_project_social_connections(): void
    {
        $user = $this->user('Owner'); $this->actingAs($user);
        $this->patchJson('/desktop/profile', ['name'=>'Oleg','email'=>'oleg@example.com','phone'=>'+49 123','location'=>'Berlin',
            'website'=>'https://oleg.example','bio'=>'Profiltext','personal_youtube'=>'https://youtube.com/@oleg','personal_instagram'=>'https://instagram.com/oleg'])
            ->assertOk()->assertJson(['status'=>'saved','name'=>'Oleg']);
        $this->assertDatabaseHas('user_profiles',['user_id'=>$user->id,'phone'=>'+49 123','location'=>'Berlin']);
        $this->assertSame('https://youtube.com/@oleg', $user->fresh()->profile->social_links['youtube']);
        $this->assertNull(app(Settings::class)->get('social_connections'));
        $this->patchJson('/desktop/profile', ['name'=>'Oleg','email'=>'oleg@example.com','current_password'=>'wrong','password'=>'neu123','password_confirmation'=>'neu123'])
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->patchJson('/desktop/profile', ['name'=>'Oleg','email'=>'oleg@example.com','current_password'=>'kurz5','password'=>'neu123','password_confirmation'=>'neu123'])
            ->assertOk();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('neu123', $user->fresh()->password));
        $this->assertSame('https://youtube.com/@oleg', $user->fresh()->profile->social_links['youtube']);
    }
}
