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

    public function test_playlists_preserve_missing_positions_and_link_real_content(): void
    {
        $this->login();
        $record = SourceRecord::create(['source'=>'youtube','source_id'=>'video-1','kind'=>'video','title'=>'Original','body'=>'Text','status'=>'unsorted','metadata'=>[]]);
        $collection = app(\App\Services\Importing\ContentMetadataImporter::class)->playlist('youtube',['id'=>'PLtest','title'=>'Playlist','ordered_items'=>[
            ['position'=>1,'id'=>'video-1','title'=>'Original'],['position'=>2,'id'=>null,'title'=>'Unavailable'],['position'=>3,'id'=>'foreign','title'=>'Other creator'],
        ]]);
        $this->getJson('/desktop/content/playlists')->assertOk()->assertJsonCount(1,'data')->assertJsonPath('data.0.kind','playlist');
        $this->getJson('/desktop/content/playlists/'.$collection->id)->assertOk()->assertJsonCount(3,'items')
            ->assertJsonPath('items.0.detail_url',route('content.show',$record))->assertJsonPath('items.1.detail_url',null)->assertJsonPath('items.2.detail_url',null);
        for ($position=4;$position<=101;$position++) $collection->items()->create(['position'=>$position,'source_id'=>null,'title'=>'Unavailable']);
        $this->getJson('/desktop/content/playlists/'.$collection->id)->assertOk()->assertJsonCount(100,'items');
        $this->getJson('/desktop/content/playlists/'.$collection->id.'?page=2')->assertOk()->assertJsonCount(1,'items')->assertJsonPath('items.0.position',101);
    }
}
