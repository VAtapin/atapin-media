<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\MediaOriginal;
use App\Models\SourceRecordSnapshot;
use App\Services\Importing\ContentMetadataImporter;
use App\Services\Importing\ImportedMediaRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImportMergeTest extends TestCase
{
    use RefreshDatabase;
    public function test_identical_files_from_different_sources_reuse_media_and_preserve_locations_and_tags(): void
    {
        Queue::fake(); $classifier=\Mockery::mock(\App\Services\Importing\AiContentClassifier::class); $classifier->shouldReceive('available')->andReturn(true);
        app()->instance(\App\Services\Importing\AiContentClassifier::class,$classifier);
        $attributes=['source'=>'youtube','source_id'=>'video-id','disk'=>'youtube','path'=>'items/video.mp4',
            'original_name'=>'video.mp4','title'=>'Original','mime'=>'video/mp4','kind'=>'video','bytes'=>10,
            'sha256'=>hash('sha256','same video'),'status'=>'unsorted','metadata'=>[]];
        $registry = app(ImportedMediaRegistry::class); $first=$registry->register($attributes);
        $first->update(['title'=>'Accepted title','status'=>'ready']);
        $second=$registry->register([...$attributes,'source'=>'local-archive','source_id'=>'takeout-file','disk'=>'import-inbox','path'=>'Takeout/new-name.mp4']);
        $this->assertSame($first->id,$second->id); $this->assertSame('Accepted title',$second->title);
        $this->assertSame('ready',$second->status); $this->assertDatabaseCount('media',1); $this->assertDatabaseCount('media_originals',2);
        Queue::assertPushed(\App\Jobs\ClassifyImportedContent::class,1);
        $registry->register($attributes); $this->assertDatabaseCount('media_originals',2);
        $variant=$registry->register([...$attributes,'source'=>'local-archive','source_id'=>'other-quality','path'=>'Takeout/other.mp4','sha256'=>hash('sha256','different video')]);
        $this->assertNotSame($first->id,$variant->id); $this->assertDatabaseCount('media',2);
    }
    public function test_repeat_merges_missing_metadata_and_longer_original_text_but_preserves_accepted_edits(): void
    {
        Queue::fake(); $importer=app(ContentMetadataImporter::class);
        $record=$importer->record('youtube','video-id','video','Original title','Short',['media_ids'=>['first']]);
        $record=$importer->record('youtube','video-id','video','Export title','Longer original description',['duration'=>12,'media_ids'=>['second']]);
        $this->assertSame('Longer original description',$record->body); $this->assertSame(['first','second'],$record->metadata['media_ids']);
        $record->update(['title'=>'Accepted title','body'=>'Owner text','status'=>'ready','metadata'=>[...$record->metadata,'tags'=>['Accepted'],'classification_origin'=>'manual']]);
        $record=$importer->record('youtube','video-id','video','Takeout title','Even longer original description from Takeout',['width'=>1920,'duration'=>20,'media_ids'=>['third']]);
        $this->assertSame('Accepted title',$record->title); $this->assertSame('Owner text',$record->body);
        $this->assertSame(['Accepted'],$record->metadata['tags']); $this->assertSame(12,$record->metadata['duration']);
        $this->assertSame(1920,$record->metadata['width']); $this->assertSame(['first','second','third'],$record->metadata['media_ids']);
        $this->assertTrue(SourceRecordSnapshot::where('source_record_id',$record->id)->where('body','Even longer original description from Takeout')->exists());
        $this->assertStringContainsString('Even longer original description from Takeout',app(\App\Services\Importing\AiContentEvidence::class)->build($record)['evidence']['body']);
        $count=SourceRecordSnapshot::count(); $importer->record('youtube','video-id','video','Takeout title','Even longer original description from Takeout',['width'=>1920,'duration'=>20,'media_ids'=>['third']]);
        $this->assertSame($count,SourceRecordSnapshot::count()); $this->assertDatabaseCount('source_records',1);
    }
    public function test_original_import_versions_require_permission_and_matching_record(): void
    {
        Queue::fake(); $importer=app(ContentMetadataImporter::class);
        $record=$importer->record('youtube','first','video','Title','Original text',[]);
        $other=$importer->record('youtube','second','video','Other','Other text',[]);
        $snapshot=SourceRecordSnapshot::where('source_record_id',$record->id)->firstOrFail();
        app(\App\Services\Access::class)->seed(); $user=\App\Models\User::factory()->create();
        $this->actingAs($user)->getJson('/desktop/content/'.$record->id.'/imports/'.$snapshot->id)->assertForbidden();
        $user->roles()->attach(\App\Models\Role::where('name','Owner')->firstOrFail());
        $this->getJson('/desktop/content/'.$record->id.'/imports/'.$snapshot->id)->assertOk()->assertJsonPath('body','Original text');
        $this->getJson('/desktop/content/'.$other->id.'/imports/'.$snapshot->id)->assertNotFound();
    }
}
