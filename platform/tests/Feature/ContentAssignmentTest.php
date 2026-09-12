<?php
namespace Tests\Feature;
use App\Jobs\ClassifyImportedContent;
use App\Jobs\ImportArchive;
use App\Models\ImportRun;
use App\Models\Media;
use App\Models\Role;
use App\Models\SourceRecord;
use App\Models\User;
use App\Services\Access;
use App\Services\Settings;
use App\Services\Importing\AiContentClassifier;
use App\Services\Importing\ContentAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContentAssignmentTest extends TestCase
{
    use RefreshDatabase;
    private function login(string $role = 'Owner'): User
    {
        app(Access::class)->seed(); $user = User::factory()->create();
        $user->roles()->attach(Role::where('name',$role)->firstOrFail()); $this->actingAs($user); return $user;
    }
    private function media(string $mime = 'text/plain'): Media
    {
        Storage::fake('local'); Storage::disk('local')->put('original','Ein ausführlicher Beitrag über Hoffnung.');
        return Media::create(['disk'=>'local','path'=>'original','source'=>'upload','source_id'=>'original','original_name'=>'original.txt',
            'title'=>'Original','kind'=>$mime === 'text/plain' ? 'document' : 'video','mime'=>$mime,'bytes'=>60,'sha256'=>str_repeat('a',64),'status'=>'unsorted','metadata'=>[]]);
    }
    private function ai(): void
    {
        Queue::fake();
        app(Settings::class)->update(['ai_enabled'=>true,'ai_provider'=>'openai','ai_model'=>'configured-model','ai_auto_classify'=>true]);
        app(Settings::class)->updateSecrets(['ai_api_key'=>'test-not-a-real-secret']);
    }
    private function proposal(): array { return ['title'=>'Hoffnung','summary'=>'Ein Beitrag über Hoffnung.','tags'=>['Hoffnung'],'target_profile'=>'posts','confidence'=>0.97]; }
    private function response(): array { return ['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode($this->proposal())]]]]]; }
    private function classify(Media $media): void { (new ClassifyImportedContent('media',$media->id))->handle(app(AiContentClassifier::class),app(ContentAssignment::class)); }
    public function test_manual_assignment_keeps_original_and_does_not_duplicate_linked_content(): void
    {
        $this->login(); $media = $this->media();
        $data = ['title'=>'Edited','status'=>'ready','target_profile'=>'videos','tags'=>['Faith']];
        $this->patchJson('/desktop/media/'.$media->id,$data)->assertOk();
        $this->patchJson('/desktop/media/'.$media->id,$data)->assertOk();
        $this->assertDatabaseCount('source_records',1); $this->assertDatabaseCount('media_usages',1);
        $this->assertSame('manual',$media->fresh()->classification_origin); $this->assertSame('Faith',$media->tags()->first()->name);
        $this->assertSame('video',SourceRecord::first()->kind); $this->assertTrue(Storage::disk('local')->exists('original'));
        $record = SourceRecord::first();
        $this->patchJson('/desktop/content/'.$record->id,['title'=>'Post','body'=>'Original body','kind'=>'post','status'=>'ready','tags'=>['Tag']])->assertOk();
        $this->assertSame('post',$record->fresh()->kind);
        $this->patchJson('/desktop/content/'.$record->id,['title'=>'Post','body'=>'','kind'=>'post','status'=>'ready'])->assertOk();
        $this->assertSame('',$record->fresh()->body);
    }
    public function test_retry_preserves_owner_and_only_requeues_failed_or_partial_runs(): void
    {
        $user = $this->login(); Queue::fake();
        $run = ImportRun::create(['source'=>'local-archive','status'=>'failed','user_id'=>$user->id,'source_options'=>['media_id'=>'original'],'error'=>'Interrupted']);
        $this->postJson('/desktop/imports/'.$run->id.'/retry')->assertOk();
        $this->assertSame($user->id,$run->fresh()->user_id); $this->assertNull($run->fresh()->error);
        Queue::assertPushed(ImportArchive::class,1);
        $this->postJson('/desktop/imports/'.$run->id.'/retry')->assertConflict();
    }
    public function test_ai_applies_validated_result_and_records_history(): void
    {
        $this->ai(); Http::fake(['api.openai.com/*'=>Http::response($this->response())]);
        $media = $this->media(); Queue::assertPushed(ClassifyImportedContent::class);
        $this->classify($media);
        $this->assertSame('ready',$media->fresh()->status); $this->assertSame('ai',$media->fresh()->classification_origin);
        $this->assertSame('applied',$media->classifications()->first()->status); $this->assertSame('post',SourceRecord::first()->kind);
        Http::assertSent(fn ($request) => $request['store'] === false && $request['text']['format']['strict'] === true && $request['model'] === 'configured-model');
    }
    public function test_filename_only_media_is_not_marked_understood(): void
    {
        $this->ai(); Http::fake(['api.openai.com/*'=>Http::response($this->response())]); $media = $this->media('video/mp4');
        $this->classify($media); $this->assertSame('needs_attention',$media->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_existing_descriptions_and_subtitles_are_used_without_reading_video(): void
    {
        $this->ai(); Http::fake(['api.openai.com/*'=>Http::response($this->response())]);
        $media = $this->media('video/mp4');
        Storage::disk('local')->put('ready.vtt', "WEBVTT\n\n1\n00:00:01.000 --> 00:00:03.000\nBereits vorhandene Untertitel");
        $subtitle = Media::create(['disk'=>'local','path'=>'ready.vtt','source'=>'upload','original_name'=>'ready.vtt','title'=>'Subtitle',
            'kind'=>'document','mime'=>'text/vtt','bytes'=>90,'parent_id'=>$media->id,'status'=>'ready']);
        SourceRecord::create(['source'=>'youtube','source_id'=>'source-video','title'=>'Original title','body'=>'Vorhandene Beschreibung',
            'kind'=>'video','status'=>'unsorted','metadata'=>['media_ids'=>[$media->id,$subtitle->id]]]);
        $input = app(\App\Services\Importing\AiContentEvidence::class)->build($media);
        $this->assertStringContainsString('Vorhandene Beschreibung',$input['evidence']['body']);
        $this->assertStringContainsString('Bereits vorhandene Untertitel',$input['evidence']['body']);
        $this->assertStringNotContainsString('ausführlicher Beitrag',$input['evidence']['body']);
        $this->assertNull($input['image']);
        $this->classify($media); $this->assertSame('videos',$media->fresh()->metadata['target_profile']);
    }

    public function test_ai_undo_restores_state_without_deleting_originals_or_overwriting_manual_edits(): void
    {
        $this->login(); $this->ai(); Http::fake(['api.openai.com/*'=>Http::response($this->response())]);
        $media = $this->media(); $this->classify($media); $log = $media->classifications()->first();
        $this->postJson('/desktop/media/'.$media->id.'/classifications/'.$log->id.'/undo')->assertOk();
        $this->assertSame('Original',$media->fresh()->title); $this->assertSame('unsorted',$media->fresh()->status);
        $this->assertDatabaseCount('source_records',1); $this->getJson('/desktop/content?section=posts')->assertOk()->assertJsonCount(0,'data');
        $this->assertTrue(Storage::disk('local')->exists('original'));
        $this->assertSame('undone',$log->fresh()->status);
        $this->classify($media); $latest = $media->classifications()->latest('id')->first();
        $media->update(['title'=>'Manual correction']);
        $this->postJson('/desktop/media/'.$media->id.'/classifications/'.$latest->id.'/undo')->assertConflict();
        $this->assertSame('Manual correction',$media->fresh()->title);
    }
    public function test_manual_edits_during_ai_request_are_not_overwritten(): void
    {
        $this->ai(); $media = $this->media();
        Http::fake(function () use ($media) { $media->update(['title'=>'Manual correction']); return Http::response($this->response()); });
        $this->classify($media); $this->assertSame('Manual correction',$media->fresh()->title);
        $this->assertSame('superseded',$media->classifications()->first()->status);
    }
    public function test_ai_errors_are_reviewable_and_never_become_success(): void
    {
        $this->ai(); Http::fake(['api.openai.com/*'=>Http::response([],429)]); $media = $this->media();
        try {$this->classify($media); $this->fail('Expected provider error');} catch (\RuntimeException $error) {$this->assertStringContainsString('429',$error->getMessage());}
        $this->assertSame('needs_attention',$media->fresh()->status); $this->assertSame('failed',$media->classifications()->first()->status);
    }
    public function test_classification_and_assignment_require_permissions_and_configuration(): void
    {
        $this->login(); $media = $this->media();
        $this->postJson('/desktop/content/classify',['type'=>'media','id'=>$media->id])->assertUnprocessable();
        $this->login('Support');
        $this->patchJson('/desktop/media/'.$media->id,['title'=>'Forbidden'])->assertForbidden();
        $this->postJson('/desktop/content/classify',['type'=>'media','id'=>$media->id])->assertForbidden();
    }
}
