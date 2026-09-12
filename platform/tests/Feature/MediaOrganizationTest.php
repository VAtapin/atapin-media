<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Role;
use App\Models\User;
use App\Services\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaOrganizationTest extends TestCase
{
    use RefreshDatabase;
    private function login(string $role = 'Owner'): void
    {
        app(Access::class)->seed(); $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $role)->firstOrFail()); $this->actingAs($user);
    }
    private function media(string $name): Media
    {
        Storage::disk('local')->put($name, 'Original');
        return Media::create(['disk'=>'local', 'path'=>$name, 'source'=>'upload', 'source_id'=>$name, 'title'=>$name,
            'original_name'=>$name, 'kind'=>'document', 'mime'=>'text/plain', 'bytes'=>8, 'status'=>'unsorted']);
    }
    public function test_collections_bulk_filters_and_archive_restore_preserve_originals(): void
    {
        $this->login(); Storage::fake('local'); $first = $this->media('first.txt'); $second = $this->media('second.txt');
        $id = $this->postJson('/desktop/media/collections', ['title'=>'Sammlung'])->assertCreated()->json('id');
        $this->patchJson('/desktop/media/organize', ['ids'=>[$first->id,$second->id], 'add_tags'=>['Faith'], 'collection_id'=>$id])->assertOk()->assertJsonPath('count',2);
        $this->getJson('/desktop/media/library?tag=Faith&collection='.$id)->assertOk()->assertJsonCount(2,'data');
        $this->patchJson('/desktop/media/collections/'.$id.'/members/'.$second->id, ['action'=>'up'])->assertOk();
        $this->getJson('/desktop/media/collections/'.$id)->assertOk()->assertJsonPath('data.0.id',$second->id);
        $this->patchJson('/desktop/media/organize', ['ids'=>[$first->id], 'archived'=>true])->assertOk();
        $this->getJson('/desktop/media/library')->assertOk()->assertJsonCount(1,'data');
        $this->getJson('/desktop/media/library?archive=archived')->assertOk()->assertJsonPath('data.0.id',$first->id);
        $this->patchJson('/desktop/media/organize', ['ids'=>[$first->id], 'archived'=>false])->assertOk();
        $this->patchJson('/desktop/media/collections/'.$id.'/members/'.$first->id, ['action'=>'remove'])->assertOk();
        $this->assertDatabaseCount('media',2); $this->assertDatabaseCount('collection_media',1);
        $this->assertTrue(Storage::disk('local')->exists('first.txt'));
        $this->getJson('/desktop/media/'.$second->id.'/details')->assertOk()->assertJsonPath('storage.path','second.txt')->assertJsonCount(1,'collections');
    }
    public function test_collection_changes_and_bulk_require_permissions_and_validate_selection(): void
    {
        $this->login(); Storage::fake('local'); $file = $this->media('original.txt');
        $this->patchJson('/desktop/media/organize', ['ids'=>[$file->id,'00000000-0000-4000-8000-000000000000'], 'archived'=>true])->assertUnprocessable();
        $this->assertNull($file->fresh()->archived_at);
        $this->login('Support');
        $this->postJson('/desktop/media/collections',['title'=>'Forbidden'])->assertForbidden();
        $this->patchJson('/desktop/media/organize',['ids'=>[$file->id], 'archived'=>true])->assertForbidden();
        $this->getJson('/desktop/media/'.$file->id.'/details')->assertForbidden();
    }
}
