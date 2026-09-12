<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use App\Models\SourceRecord;
class PlaylistEditorTest extends TestCase
{
    use RefreshDatabase;
    public function test_local_playlist_edit_preserves_originals_and_survives_reimport(): void
    {
        Queue::fake();app(\App\Services\Access::class)->seed();$user=\App\Models\User::factory()->create();$this->actingAs($user);
        $importer=app(\App\Services\Importing\ContentMetadataImporter::class);$data=['id'=>'PLoriginal','title'=>'Original','ordered_items'=>[['id'=>'missing','title'=>'Missing']]];$playlist=$importer->playlist('youtube',$data);
        $record=SourceRecord::create(['source'=>'upload','source_id'=>'local-video','kind'=>'video','title'=>'Local video','body'=>'Original','status'=>'ready','metadata'=>[]]);
        $base='/desktop/content/playlists/'.$playlist->id;
        $this->postJson($base.'/members',['record_id'=>$record->id])->assertForbidden();$user->roles()->attach(\App\Models\Role::where('name','Owner')->firstOrFail());
        $this->patchJson($base,['title'=>'Edited','description'=>'Local description'])->assertOk();$this->postJson($base.'/members',['record_id'=>$record->id])->assertOk();$this->postJson($base.'/members',['record_id'=>$record->id])->assertOk();$this->assertSame(2,$playlist->items()->count());
        $member=$playlist->items()->where('source_record_id',$record->id)->firstOrFail();$this->patchJson($base.'/members/'.$member->id,['action'=>'up'])->assertOk();
        $this->getJson($base)->assertJsonPath('items.0.title','Local video')->assertJsonPath('items.0.detail_url',route('content.show',$record));
        $importer->playlist('youtube',$data);$this->assertSame('Edited',$playlist->fresh()->title);$this->assertSame(2,$playlist->items()->count());$this->assertSame($data,$playlist->fresh()->metadata['latest_import']['raw']);
        $other=$importer->playlist('youtube',['id'=>'PLother']);$this->patchJson('/desktop/content/playlists/'.$other->id.'/members/'.$member->id,['action'=>'remove'])->assertNotFound();
        $this->patchJson($base.'/members/'.$member->id,['action'=>'remove'])->assertOk();$this->assertDatabaseHas('source_records',['id'=>$record->id,'body'=>'Original']);$this->assertSame(1,$playlist->items()->count());
    }
}
