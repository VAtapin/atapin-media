<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Media;
use App\Models\MediaClassification;
use App\Models\MediaUsage;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaLibraryModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_can_keep_organisation_and_ai_history_without_changing_its_original(): void
    {
        $media = Media::create(['title'=>'Original','original_name'=>'original.mp4','kind'=>'video','mime'=>'video/mp4','bytes'=>100,
            'disk'=>'youtube','path'=>'items/example/video.mp4','source'=>'youtube','source_id'=>'example:video','status'=>'unsorted']);
        $asset = Media::create(['title'=>'Thumbnail','original_name'=>'thumbnail.webp','kind'=>'image','mime'=>'image/webp','bytes'=>20,
            'disk'=>'youtube','path'=>'items/example/thumbnail.webp','source'=>'youtube','source_id'=>'example:thumbnail','status'=>'unsorted','parent_id'=>$media->id,'asset_role'=>'thumbnail']);
        $tag = Tag::create(['name'=>'Hoffnung','slug'=>'hoffnung']);
        $collection = Collection::create(['title'=>'Predigten','source'=>'manual','source_id'=>'predigten']);
        $media->tags()->attach($tag);
        $media->collections()->attach($collection, ['position'=>3]);
        MediaUsage::create(['media_id'=>$media->id,'used_as'=>'cover','subject_type'=>'project','subject_id'=>'17']);
        MediaClassification::create(['media_id'=>$media->id,'provider'=>'local-ai','model'=>'test','status'=>'applied','confidence'=>.93,
            'proposal'=>['tags'=>['Hoffnung']], 'applied_changes'=>['status'=>'ready']]);

        $this->assertTrue($media->fresh()->tags->contains($tag));
        $this->assertSame($asset->id, $media->fresh()->assets->sole()->id);
        $this->assertSame('cover', $media->fresh()->usages->sole()->used_as);
        $this->assertSame('applied', $media->fresh()->classifications->sole()->status);
        $this->assertSame('items/example/video.mp4', $media->fresh()->path);
    }
}
