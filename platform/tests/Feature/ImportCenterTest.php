<?php
namespace Tests\Feature;

use App\Jobs\ImportArchive;
use App\Services\Access;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ImportCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(Access::class)->seed();
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['password' => 'kurz5']);
        $user->roles()->attach(Role::where('name', $role)->firstOrFail());

        return $user;
    }

    public function test_import_center_options_are_exposed_to_authorized_users(): void
    {
        $this->actingAs($this->user('Owner'));

        $this->getJson('/desktop/imports/options')->assertOk()
            ->assertJsonStructure([
                'sources',
                'targets',
                'accepted_fields',
            ]);
    }

    public function test_import_store_queues_run_with_target_profile(): void
    {
        Bus::fake();
        $user = $this->user('Mediengestalter');

        $this->actingAs($user)->postJson('/desktop/imports', [
            'source' => 'local-folder',
            'path' => 'incoming/videos',
            'target_profile' => 'videos',
            'notes' => 'manual archive import',
        ])->assertOk()
            ->assertJsonStructure(['status', 'import_id']);

        Bus::assertDispatched(ImportArchive::class);
        $this->assertDatabaseHas('import_runs', [
            'source' => 'local-folder',
            'source_kind' => 'local-folder',
            'target_profile' => 'videos',
            'user_id' => $user->id,
        ]);
    }

    public function test_import_center_is_blocked_without_import_permission(): void
    {
        $this->actingAs($this->user('Editor'));
        $this->postJson('/desktop/imports', ['source' => 'intake'])->assertForbidden();
    }

    public function test_server_browser_has_navigation_and_does_not_escape_import_root(): void
    {
        $root = sys_get_temp_dir().'/atapin-browser-'.bin2hex(random_bytes(8));
        mkdir($root.'/nested', 0700, true);
        file_put_contents($root.'/nested/original.txt', 'original');
        config(['platform.import_inbox_root' => $root]);
        try {
            $this->actingAs($this->user('Owner'));
            $this->getJson('/desktop/imports/files')->assertOk()->assertJsonPath('data.0.type', 'folder')->assertJsonPath('parent', null);
            $this->getJson('/desktop/imports/files?path=nested')->assertOk()->assertJsonPath('path', 'nested')->assertJsonPath('parent', '')->assertJsonPath('data.0.type', 'file');
            $this->getJson('/desktop/imports/files?path=..')->assertUnprocessable();
            $this->getJson('/desktop/imports/files?path=nested/original.txt')->assertUnprocessable();
        } finally {\Illuminate\Support\Facades\File::deleteDirectory($root);}
    }
}
