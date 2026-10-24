<?php
namespace Tests\Feature;
use App\Models\{Media,SourceRecord};
use App\Services\{MediaDerivatives,CanonicalMediaStorage,PublicContent,AiCoverGenerator,Settings};
use App\Jobs\{PrepareContentMedia,GenerateContentCover};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Storage,Queue,Process,Http,Cache};
use Tests\TestCase;

class MediaDerivativesTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void{parent::setUp();Storage::fake('local');Storage::fake('media-canonical',['url'=>'/media']);config(['platform.media_upload_reserve_free_bytes'=>0]);Queue::fake();}
    private function png(): string{return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jvyoAAAAASUVORK5CYII=');}
    private function record(array $metadata=[]): SourceRecord
    {
        Storage::disk('media-canonical')->put('video.mp4','original');$video=Media::create(['source'=>'upload','title'=>'Video','kind'=>'video','mime'=>'video/mp4','disk'=>'media-canonical','path'=>'video.mp4','original_name'=>'video.mp4','bytes'=>8,'status'=>'ready']);
        return SourceRecord::create(['source'=>'upload','source_id'=>uniqid(),'kind'=>'video','title'=>'Gottes Wort','body'=>'Original text','status'=>'ready','metadata'=>['media_ids'=>[$video->id],...$metadata]]);
    }
    public function test_missing_cover_is_queued_once_and_existing_cover_is_not_overwritten(): void
    {
        $record=$this->record();$record->update(['title'=>'New title']);Queue::assertPushed(PrepareContentMedia::class,1);
        $media=app(MediaDerivatives::class);Process::fake(function($p){file_put_contents(end($p->command),"\xFF\xD8\xFF\xC0".pack('nCnnC',17,8,9,16,3)."\x01\x11\x00\x02\x11\x00\x03\x11\x00\xFF\xD9");return Process::result();});
        $job=new PrepareContentMedia($record->id);$job->handle($media);
        $record->refresh();$cover=Media::findOrFail($record->metadata['cover_media_id']);$this->assertSame('media-canonical',$cover->disk);$this->assertMatchesRegularExpression('/^[a-f0-9]{64}\.jpg$/',$cover->path);
        $this->assertSame('original',Storage::disk('media-canonical')->get('video.mp4'));$id=$cover->id;$job->handle($media);$this->assertSame($id,$record->fresh()->metadata['cover_media_id']);
    }
    public function test_public_video_queues_hls_and_publishes_complete_renditions_without_rewriting_original(): void
    {
        $record=$this->record(['public_published'=>true]);Queue::assertPushed(PrepareContentMedia::class,fn($job)=>$job->operation==='hls');
        Process::fake(function($process){
            if(in_array('-show_entries',$process->command,true))return Process::result(output:json_encode(['streams'=>[['codec_type'=>'video','width'=>640,'height'=>360],['codec_type'=>'audio']]]));
            if(in_array('-hls_segment_filename',$process->command,true)){
                $segmentTemplate=$process->command[array_search('-hls_segment_filename',$process->command,true)+1];$root=dirname(dirname($segmentTemplate));
                file_put_contents($root.'/index.m3u8',"#EXTM3U\n#EXT-X-STREAM-INF:BANDWIDTH=930000,RESOLUTION=640x360\n360p/index.m3u8\n");
                mkdir($root.'/360p',0700,true);file_put_contents($root.'/360p/index.m3u8',"#EXTM3U\n#EXTINF:6.0,\nsegment_00000.ts\n");file_put_contents($root.'/360p/segment_00000.ts','segment');
                mkdir($root.'/240p',0700,true);file_put_contents($root.'/240p/index.m3u8',"#EXTM3U\n#EXTINF:6.0,\nsegment_00000.ts\n");file_put_contents($root.'/240p/segment_00000.ts','segment');
            }else{$output=end($process->command);file_put_contents($output,'compressed');}
            return Process::result();
        });
        (new PrepareContentMedia($record->id,'hls'))->handle(app(MediaDerivatives::class));$record->refresh();
        $playback=$record->metadata['hls_playback'];$this->assertSame('ready',$playback['state'],json_encode($record->metadata['media_jobs']['hls']??[]));$hash=basename(dirname($playback['url']));
        $this->assertFileExists(Storage::disk('media-canonical')->path('hls/'.$hash.'/index.m3u8'));
        $this->assertFileExists(Storage::disk('media-canonical')->path('hls/'.$hash.'/360p/segment_00000.ts'));
        $this->assertFileExists(Storage::disk('media-canonical')->path('hls/'.$hash.'/playback.mp4'));
        $page=$this->get(app(PublicContent::class)->card($record->fresh())['url'])->assertOk()->assertSee('data-hls-src="/media/hls/'.$hash.'/index.m3u8"',false)->assertSee('data-mp4-src="/media/hls/'.$hash.'/playback.mp4"',false);
        $this->assertSame('original',Storage::disk('media-canonical')->get('video.mp4'));
        Process::assertRan(fn($process)=>in_array('libx264',$process->command,true)&&in_array('independent_segments',$process->command,true));
    }
    public function test_hls_failure_stops_the_pipeline_and_requires_explicit_retry(): void
    {
        Cache::forget('media.hls.paused');$record=$this->record(['public_published'=>true]);Queue::fake();
        Process::fake(function($process){
            if(in_array('-show_entries',$process->command,true))return Process::result(output:json_encode(['streams'=>[['codec_type'=>'video','width'=>640,'height'=>360]]]));
            return Process::result(exitCode:1,errorOutput:'encoder unavailable');
        });
        (new PrepareContentMedia($record->id,'hls'))->handle(app(MediaDerivatives::class));$record->refresh();
        $this->assertSame('failed',$record->metadata['media_jobs']['hls']['state']);
        $this->assertStringContainsString('encoder unavailable',$record->metadata['media_jobs']['hls']['error']);
        $this->assertSame($record->id,Cache::get('media.hls.paused')['source_record_id']);
        $this->assertSame(0,app(\App\Services\ContentMediaPreparation::class)->queueMissing($record,true));
        Cache::forget('media.hls.paused');
        $this->assertSame(1,app(\App\Services\ContentMediaPreparation::class)->queueMissing($record,true,true));
        Queue::assertPushed(PrepareContentMedia::class,fn($job)=>$job->operation==='hls');
    }
    public function test_cleanup_removes_only_matching_abandoned_hls_work_directories(): void
    {
        $hash=str_repeat('a',64);$suffix=str_repeat('b',16);$work=Storage::disk('local')->path('media-work/hls/'.$hash.'-'.$suffix);
        $stage=Storage::disk('media-canonical')->path('.hls-'.$hash.'-'.$suffix);$unrelated=Storage::disk('local')->path('media-work/hls/keep-me');
        mkdir($work,0700,true);mkdir($stage,0700,true);mkdir($unrelated,0700,true);

        $this->artisan('media:cleanup-abandoned-work')->assertSuccessful();

        $this->assertDirectoryDoesNotExist($work);$this->assertDirectoryDoesNotExist($stage);$this->assertDirectoryExists($unrelated);
    }
    public function test_podcast_copies_aac_audio_and_public_page_never_loads_video(): void
    {
        $record=$this->record(['public_section'=>'podcast','public_published'=>true]);$media=app(MediaDerivatives::class);
        Process::fake(function($p){if(in_array('-show_entries',$p->command,true))return Process::result(output:json_encode(['streams'=>[['codec_name'=>'aac']]]));file_put_contents(end($p->command),'audio-bytes');return Process::result();});
        (new PrepareContentMedia($record->id,'podcast'))->handle($media);$record->refresh();
        $audio=Media::findOrFail($record->metadata['podcast_audio_id']);$this->assertSame('audio/mp4',$audio->mime);$this->assertMatchesRegularExpression('/^[a-f0-9]{64}\.m4a$/',$audio->path);
        Process::assertRan(fn($p)=>in_array('copy',$p->command,true)&&in_array('-vn',$p->command,true));
        $this->get(route('public.podcast',['episode'=>$record->id]))->assertOk()->assertSee('<audio',false)->assertDontSee('<video',false)->assertDontSee('src="/media/video.mp4"',false);
        $this->assertSame('Original text',$record->body);$this->assertSame('original',Storage::disk('media-canonical')->get('video.mp4'));
    }
    public function test_incompatible_audio_encodes_audio_only_and_failure_is_visible(): void
    {
        $record=$this->record(['public_section'=>'podcast']);
        Process::fake(function($p){if(in_array('-show_entries',$p->command,true))return Process::result(output:json_encode(['streams'=>[['codec_name'=>'pcm_s16le']]]));file_put_contents(end($p->command),'encoded-audio');return Process::result();});
        (new PrepareContentMedia($record->id,'podcast'))->handle(app(MediaDerivatives::class));Process::assertRan(fn($p)=>in_array('libmp3lame',$p->command,true)&&in_array('-vn',$p->command,true));
        $other=$this->record(['public_section'=>'podcast']);Process::fake(['*'=>Process::result(exitCode:1)]);(new PrepareContentMedia($other->id,'podcast'))->handle(app(MediaDerivatives::class));
        $this->assertSame('failed',$other->fresh()->metadata['media_jobs']['podcast']['state']);
    }
    public function test_manual_and_batch_podcast_assignment_preserves_comments_and_video(): void
    {
        $record=$this->record();$ids=$record->metadata['media_ids'];app(\App\Services\Importing\RecordOrganization::class)->apply(['ids'=>[$record->id],'target_profile'=>'podcast']);
        $this->assertSame('podcast',$record->fresh()->metadata['public_section']);$this->assertSame($ids,$record->fresh()->metadata['media_ids']);Queue::assertPushed(PrepareContentMedia::class,fn($j)=>$j->operation==='podcast');
    }
    public function test_ai_cover_uses_shared_style_project_topic_and_canonical_storage(): void
    {
        $record=$this->record();app(Settings::class)->update(['ai_enabled'=>true,'ai_provider'=>'openai','ai_model'=>'text-model','ai_image_model'=>'image-model','cover_style_prompt'=>'Shared navy and golden style','site_name'=>'Project']);app(Settings::class)->updateSecrets(['ai_api_key'=>'test-only']);
        Http::fake(['api.openai.com/*'=>Http::response(['data'=>[['b64_json'=>base64_encode($this->png())]]])]);
        $version=app(\App\Services\ContentShortDescriptions::class)->version($record);(new GenerateContentCover($record->id,null,$version))->handle(app(AiCoverGenerator::class));
        Http::assertSent(fn($r)=>$r['model']==='image-model'&&str_contains($r['prompt'],'Shared navy and golden style')&&str_contains($r['prompt'],'Project'));
        $record->refresh();$cover=Media::findOrFail($record->metadata['cover_media_id']);$this->assertSame(hash('sha256',$this->png()).'.png',$cover->path);$this->assertSame('completed',$record->metadata['media_jobs']['ai_cover']['state']);
    }
    public function test_derived_cover_can_be_shared_by_posts_with_video_without_reparenting(): void
    {
        $first=$this->record();$second=$this->record();$second->update(['kind'=>'post']);
        $service=app(MediaDerivatives::class);$path=$service->temporary('png');file_put_contents($path,$this->png());
        try{$image=$service->register($path,'image/png','image','Cover',$service->video($first));}finally{unlink($path);}
        app(\App\Services\Importing\MediaCoverAssignment::class)->assign($image,$first);
        app(\App\Services\Importing\MediaCoverAssignment::class)->assign($image,$second);
        $this->assertSame($image->id,$second->fresh()->metadata['cover_media_id']);$this->assertSame($service->video($first)->id,$image->fresh()->parent_id);
    }
    public function test_changed_cover_skips_a_stale_ai_request_before_payment(): void
    {
        $record=$this->record();$version=app(\App\Services\ContentShortDescriptions::class)->version($record);
        $record->updateQuietly(['metadata'=>[...$record->metadata,'cover_media_id'=>'new-manual-cover']]);Http::preventStrayRequests();
        (new GenerateContentCover($record->id,null,$version))->handle(app(AiCoverGenerator::class));Http::assertNothingSent();
        $this->assertSame('superseded',$record->fresh()->metadata['media_jobs']['ai_cover']['state']);
    }
}
