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
}
