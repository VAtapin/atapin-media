<?php
namespace Tests\Feature;
use App\Models\SourceRecord;
use App\Models\Role;
use App\Models\User;
use App\Services\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportedContentTest extends TestCase
{
    use RefreshDatabase;
    private function login(string $role = 'Owner'): User
    {
        app(Access::class)->seed(); $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $role)->firstOrFail()); $this->actingAs($user); return $user;
    }
    public function test_sections_show_real_imported_content_without_publishing(): void
    {
        $this->login();
        foreach (['video','short','post','poll','comment'] as $kind) SourceRecord::create([
            'source'=>'youtube','source_id'=>$kind,'kind'=>$kind,'title'=>'Original','body'=>'Description', 'status'=>'unsorted','metadata'=>[],
        ]);
        $this->getJson('/desktop/content?section=videos')->assertOk()->assertJsonCount(2,'data');
        $this->getJson('/desktop/content?section=posts')->assertOk()->assertJsonCount(1,'data');
        $this->getJson('/desktop/content?section=community')->assertOk()->assertJsonCount(2,'data');
        $record = SourceRecord::where('kind','post')->firstOrFail();
        $this->getJson('/desktop/content/'.$record->id)->assertOk()->assertJsonPath('body','Description')->assertJsonPath('private',true);
        $this->assertSame('unsorted',$record->fresh()->status);
    }
    public function test_content_and_server_inventory_are_private(): void
    {
        $this->getJson('/desktop/content')->assertUnauthorized();
        $this->login('Support');
        $this->getJson('/desktop/content')->assertForbidden();
        $this->getJson('/desktop/imports/files')->assertForbidden();
    }
}
