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
        $this->actingAs($user)->get('/desktop')->assertOk()->assertDontSee('jobs_waiting');
        $this->get('/desktop/media')->assertForbidden();
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
        $this->get('/desktop/media?q=Hoffnung')->assertOk()->assertSee('Hoffnung');
        $this->get('/desktop/media?q=absent')->assertDontSee('Hoffnung');
        $this->assertDatabaseHas('audit_events',['action'=>'media.uploaded','subject'=>$media->id]);
    }
    public function test_settings_invalidate_cache_and_escape_public_text(): void
    {
        $settings=app(Settings::class); $settings->all();
        $this->actingAs($this->user('Owner'))->put('/desktop/settings',[
            'site_name'=>'<script>bad()</script>','site_description'=>'Text','contact_email'=>'owner@example.com'])->assertRedirect();
        $this->assertSame('<script>bad()</script>',$settings->get('site_name'));
        $this->get('/')->assertOk()->assertSee('&lt;script&gt;bad()&lt;/script&gt;',false)->assertDontSee('<script>bad()</script>',false);
    }
    public function test_foundation_screens_render_without_translation_keys(): void
    {
        $this->get('/login')->assertOk()->assertSee('Anmelden')->assertDontSee('ui.login');
        $this->actingAs($this->user('Owner'));
        foreach (['/desktop','/desktop/media','/desktop/settings','/desktop/audit'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('ui.')->assertHeader('X-Robots-Tag','noindex, nofollow');
        }
    }
    public function test_role_seeding_is_idempotent(): void
    {
        app(Access::class)->seed(); $this->assertDatabaseCount('roles',6);
        $editor=$this->user('Editor');
        $this->assertTrue($editor->hasPermission('content.publish'));
        $this->assertFalse($editor->hasPermission('users.manage'));
    }
    public function test_last_owner_cannot_be_demoted_and_short_password_account_can_be_created():void
    {
        $owner=$this->user('Owner');$this->actingAs($owner);$role=Role::where('name','Editor')->firstOrFail();
        $this->patch('/desktop/users/'.$owner->id,['role_id'=>$role->id])->assertSessionHasErrors('role_id');
        $this->assertTrue($owner->fresh()->hasPermission('users.manage'));
        $this->post('/desktop/users',['name'=>'Editor','email'=>'editor@example.com','password'=>'12345','role_id'=>$role->id])->assertRedirect();
        $this->assertTrue(User::where('email','editor@example.com')->first()->hasPermission('content.edit'));
        $this->get('/desktop/users')->assertOk()->assertSee('editor@example.com');
    }
}
