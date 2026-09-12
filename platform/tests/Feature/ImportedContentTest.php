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
    public function test_private_account_data_filters_live_chat_children_and_local_references(): void
    {
        $this->login();$importer=app(\App\Services\Importing\ContentMetadataImporter::class);
        $video=$importer->record('youtube','abcdefghijk','video','Video','',[]);
        $chat=$importer->record('youtube','live-chat:1','live_chat','Chat','Chat',['parent_source_id'=>'abcdefghijk','references'=>[['type'=>'videoLink','id'=>'missing0001','text'=>'Missing']]]);
        $archive=$importer->record('youtube','channel:owner','channel','Channel','',['archive_data'=>true,'library_only'=>true,'takeout_data'=>['channel'=>['title'=>'Original']]]);
        $this->getJson('/desktop/content')->assertOk()->assertJsonCount(2,'data');
        $this->getJson('/desktop/content?kind=archive_data')->assertOk()->assertJsonCount(1,'data');
        $this->getJson('/desktop/content?kind=channel')->assertOk()->assertJsonCount(1,'data');
        $this->getJson('/desktop/content/'.$archive->id)->assertOk()->assertJsonPath('target_profile','media_library')->assertJsonPath('takeout_data.channel.title','Original');
        $this->getJson('/desktop/content/'.$chat->id)->assertOk()->assertJsonPath('references.0.detail_url',null)->assertJsonPath('references.0.missing',true)->assertJsonPath('references.1.detail_url',route('content.show',$video));
        $this->getJson('/desktop/content/'.$video->id.'/children')->assertOk()->assertJsonPath('data.0.kind','live_chat');
        app(\App\Services\Importing\ContentLifecycle::class)->delete($video);$this->assertTrue($chat->fresh()->trashed());
        app(\App\Services\Importing\ContentLifecycle::class)->restore($video->fresh());$this->assertFalse($chat->fresh()->trashed());
    }
    public function test_account_archive_is_not_sent_to_paid_ai(): void
    {
        $this->login();\Illuminate\Support\Facades\Queue::fake();
        $classifier=\Mockery::mock(\App\Services\Importing\AiContentClassifier::class);$classifier->shouldReceive('available')->andReturn(true);app()->instance(\App\Services\Importing\AiContentClassifier::class,$classifier);
        $archive=SourceRecord::create(['source'=>'youtube','source_id'=>'history:1','kind'=>'history','title'=>'History','status'=>'unsorted','metadata'=>['archive_data'=>true]]);
        $record=SourceRecord::create(['source'=>'youtube','source_id'=>'video:1','kind'=>'video','title'=>'Video','status'=>'unsorted','metadata'=>[]]);
        \Illuminate\Support\Facades\Queue::assertNotPushed(\App\Jobs\ClassifyImportedContent::class,fn($job)=>$job->id===(string)$archive->id);
        \Illuminate\Support\Facades\Queue::fake();
        $classifier->shouldNotReceive('classify');
        (new \App\Jobs\ClassifyImportedContent('record',(string)$archive->id))->handle($classifier,app(\App\Services\Importing\ContentAssignment::class));
        $this->postJson('/desktop/content/classify',['type'=>'record','batch'=>true])->assertOk()->assertJsonPath('count',1);
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\ClassifyImportedContent::class,1);
        $this->postJson('/desktop/content/classify',['type'=>'record','id'=>(string)$archive->id])->assertUnprocessable();
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

    public function test_content_distinguishes_local_video_from_metadata_and_rejects_active_links(): void
    {
        $this->login();
        \Illuminate\Support\Facades\Storage::fake('private');
        $media = \App\Models\Media::create(['title'=>'Video', 'original_name'=>'video.mp4', 'kind'=>'video', 'mime'=>'video/mp4', 'disk'=>'private', 'path'=>'video.mp4', 'bytes'=>5, 'status'=>'unsorted', 'source'=>'upload']);
        $record = SourceRecord::create(['source'=>'youtube','source_id'=>'dQw4w9WgXcQ','kind'=>'video','title'=>'Video','status'=>'unsorted','metadata'=>['media_ids'=>[$media->id], 'url'=>'javascript:alert(1)']]);
        $this->getJson('/desktop/content/'.$record->id)->assertOk()->assertJsonPath('has_local_video',false)->assertJsonPath('assets.0.download_url',null)
            ->assertJsonPath('external_url','https://www.youtube.com/watch?v=dQw4w9WgXcQ');
        \Illuminate\Support\Facades\Storage::disk('private')->put('video.mp4', 'video');
        $this->getJson('/desktop/content/'.$record->id)->assertOk()->assertJsonPath('has_local_video',true)->assertJsonPath('assets.0.available',true);
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
