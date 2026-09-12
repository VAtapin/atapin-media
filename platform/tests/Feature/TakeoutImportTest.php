<?php
namespace Tests\Feature;
use App\Models\ImportRun;
use App\Models\ImportItem;
use App\Models\Media;
use App\Models\SourceRecord;
use App\Models\Collection;
use App\Services\Importing\ImportCenter;
use App\Services\Importing\TakeoutArchiveAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;
class TakeoutImportTest extends TestCase
{
    use RefreshDatabase;
    private string $root;
    private string $batch='takeout-20260911T193951Z-1';
    private string $base='Takeout/YouTube und YouTube Music/';
    protected function setUp(): void
    {
        parent::setUp();$this->root=sys_get_temp_dir().'/takeout-test-'.bin2hex(random_bytes(8));mkdir($this->root.'/inbox',0700,true);mkdir($this->root.'/zips');
        config(['platform.takeout_root'=>$this->root.'/zips','platform.import_inbox_root'=>$this->root.'/inbox','filesystems.disks.import-inbox.root'=>$this->root.'/inbox','platform.media_upload_reserve_free_bytes'=>0]);Storage::forgetDisk('import-inbox');
    }
    protected function tearDown(): void {File::deleteDirectory($this->root);parent::tearDown();}
    private function csv(array $headers,array $rows): string
    {
        $stream=fopen('php://temp','r+');fputcsv($stream,$headers,escape:'');foreach($rows as $row)fputcsv($stream,$row,escape:'');rewind($stream);$text=stream_get_contents($stream);fclose($stream);return $text;
    }
    private function zip(int $n,array $files): void
    {
        $zip=new ZipArchive;$zip->open($this->root.'/zips/'.$this->batch.'-'.sprintf('%03d',$n).'.zip',ZipArchive::CREATE);
        foreach($files as $name=>$text)$zip->addFromString($this->base.$name,$text);$zip->close();
    }
    private function fixture(): void
    {
        $this->zip(1,[
            'Video-Metadaten/Videos.csv'=>$this->csv(['Video-ID','Videotitel (Original)','Videobeschreibung (Original)','Ungefähre Dauer (ms)','Tag: 1'],[['abcdefghijk','Fixture Video','Original description',60000,'original tag']]),
            'Video-Metadaten/Videotexte.csv'=>$this->csv(['Video-ID','Videobeschreibung – Textabschnitte 1','Videobeschreibung – Textabschnitte 2','Videotitel – Textabschnitte 1'],[['abcdefghijk','Original description, ','with additional details','Fixture Video']]),
            'Beiträge/Beiträge.csv'=>$this->csv(['Beitrags‑ID','Text des Beitrags','Name für Bild 1','Text der Antwortoption1fürUmfrage/Quiz','Text der Antwortoption2fürUmfrage/Quiz'],[['UgFixture','Real post text','UgFixture_image.jpg','Yes','No']]),
            'Kommentare/Kommentare.csv'=>$this->csv(['Kommentar-ID','Video-ID','Beitrags-ID','Kommentartext','Übergeordnete Kommentar-ID'],[['C1','abcdefghijk','','Video comment',''],['C2','','UgFixture','Post comment','']]),
            'Playlists/Playlists.csv'=>$this->csv(['Playlist-ID','Playlist-Titel (Original)','Playlist-Beschreibung (Original)'],[['PLfixture','Fixture playlist','Original playlist text']]),
            'Playlists/Fixture playlist-Videos.csv'=>$this->csv(['Video-ID','Zeitstempel bei Erstellung des Playlist-Videos'],[['abcdefghijk','2026-01-01'],['missing0001','2026-01-02']]),
            'Verlauf/Wiedergabeverlauf.html'=>'<html>Private viewing history</html>',
        ]);
        $this->zip(2,['Videos/Fixture Video.mp4'=>pack('N',24).'ftypisom'.str_repeat("\0",12),'Beiträge/UgFixture_image.jpg'=>base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jvyoAAAAASUVORK5CYII=')]);
    }
    private function runImport(): ImportRun
    {
        $run=ImportRun::create(['source'=>'youtube-takeout','source_options'=>['batch'=>$this->batch,'expected_parts'=>2],'target_profile'=>'mixed']);app(ImportCenter::class)->run($run);return $run->fresh();
    }
    public function test_german_multipart_takeout_builds_complete_content_and_attachments(): void
    {
        $progress = new class extends \App\Services\Importing\ImportProgress {
            public array $samples = [];
            public function checkpoint(ImportRun $run, ?string $stage = null, array $details = [], bool $force = false): void
            {
                parent::checkpoint($run, $stage, $details, $force); $this->samples[] = $run->progress;
            }
        };
        app()->instance(\App\Services\Importing\ImportProgress::class, $progress);
        $this->fixture();$run=$this->runImport();$this->assertSame('partial',$run->status);
        $extract = collect($progress->samples)->first(fn($p)=>($p['stage']??null)==='extract' && ($p['part']??null)===2 && ($p['file_bytes']??0)>0);
        $this->assertNotNull($extract); $this->assertSame(2, $extract['parts']); $this->assertGreaterThan(0, $extract['extract_total_bytes']);
        $this->assertContains('verify_archive', array_column($progress->samples, 'stage'));
        $this->assertContains('verify_file', array_column($progress->samples, 'stage'));
        $video=SourceRecord::where('source_id','abcdefghijk')->firstOrFail();$this->assertStringContainsString('additional details',$video->body);$this->assertCount(1,$video->metadata['media_ids']);
        $this->assertSame(60.0,(float)$video->metadata['duration']);
        $post=SourceRecord::where('source_id','UgFixture')->firstOrFail();$this->assertSame('Real post text',$post->body);$this->assertCount(1,$post->metadata['media_ids']);
        $this->assertSame(['Yes','No'],array_column(SourceRecord::where('kind','poll')->firstOrFail()->metadata['poll']['options'],'text'));
        $this->assertSame(2,SourceRecord::where('kind','comment')->count());$this->assertSame('UgFixture',SourceRecord::where('source_id','comment:UgFixture:C2')->firstOrFail()->metadata['parent_source_id']);
        $this->assertSame(['abcdefghijk','missing0001'],Collection::firstOrFail()->items()->orderBy('position')->pluck('source_id')->all());
        $this->assertTrue(ImportItem::where('import_run_id',$run->id)->where('outcome','unsupported')->exists());
        $this->assertTrue(is_file($this->root.'/zips/'.$this->batch.'-001.zip'));
        $this->assertSame(1,SourceRecord::where('kind','post')->count()); // Image is not a new post.
    }
    public function test_repeat_enriches_reviewed_record_and_resume_preserves_membership(): void
    {
        $this->fixture();$existing=SourceRecord::create(['source'=>'youtube','source_id'=>'abcdefghijk','kind'=>'short','title'=>'Owner title','body'=>'Owner text','status'=>'ready','metadata'=>[]]);
        $run=$this->runImport();$this->assertSame('Owner title',$existing->fresh()->title);$this->assertSame('Owner text',$existing->fresh()->body);$this->assertStringContainsString('additional details',$existing->fresh()->metadata['original_description']);
        $count=Media::count();$items=ImportItem::count();$run->update(['status'=>'queued']);app(ImportCenter::class)->run($run);
        $this->assertSame($count,Media::count());$this->assertSame($items,ImportItem::count());$this->assertSame(2,Collection::firstOrFail()->items()->count());
        $this->runImport();$this->assertSame($count,Media::count());$this->assertSame(1,SourceRecord::where('source_id','abcdefghijk')->count());
    }
    public function test_report_zip_is_excluded_and_missing_parts_rejected(): void
    {
        $this->zip(1,[]);file_put_contents($this->root.'/zips/takeout-20260911T193951Z-001.zip','Separate report');
        $inventory=app(TakeoutArchiveAdapter::class)->inventory();$this->assertCount(1,$inventory['reports']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);app(TakeoutArchiveAdapter::class)->validate(['batch'=>$this->batch,'expected_parts'=>8]);
    }
    public function test_ambiguous_video_does_not_attach_to_a_random_record(): void
    {
        $this->zip(1,['Video-Metadaten/Videos.csv'=>$this->csv(['Video-ID','Videotitel (Original)'],[['abcdefghijk','Same title!'],['lmnopqrstuv','Same title?']])]);
        $this->zip(2,['Videos/Same title.mp4'=>'Synthetic ambiguous original']);$run=$this->runImport();
        $this->assertTrue(ImportItem::where('import_run_id',$run->id)->where('outcome','ambiguous')->exists());
        foreach(SourceRecord::all() as $record)$this->assertEmpty($record->metadata['media_ids']);
    }
    public function test_takeout_traversal_is_rejected_before_any_registration(): void
    {
        $this->zip(1,['../../../../escape.txt'=>'Unsafe']);$this->zip(2,['placeholder.txt'=>'Safe placeholder']);
        $this->expectException(\RuntimeException::class);$this->runImport();
    }
    private function report(array $paths): string
    {
        $folders=[];
        foreach($paths as $path)$folders[dirname($path)][]=basename($path);
        $html='<html><head><meta charset="UTF-8"></head><body><div id="service-details-YOUTUBE"><div class="service_name"><h1>YouTube und YouTube Music</h1></div><div class="extracted-list">';
        foreach($folders as $folder=>$files) {
            $html.='<div class="table-row"><button class="extracted-folder"><div class="extracted-folder-name">'.htmlspecialchars($folder).'</div></button>';
            foreach($files as $file)$html.='<div class="extracted-child"><div class="file-leaf"><div class="extracted-file-name"> '.htmlspecialchars($file).'</div></div></div>';
            $html.='</div>';
        }
        $html.='</div></div><script>throw new Error("must never execute");</script></body></html>';
        $zip=new ZipArchive;$path=$this->root.'/zips/takeout-20260911T193951Z-001.zip';$zip->open($path,ZipArchive::CREATE);$zip->addFromString('Takeout/archive_browser.html',$html);$zip->close();return $path;
    }
    public function test_manifest_is_preferred_but_missing_report_keeps_the_csv_import(): void
    {
        $this->fixture();$this->report(['Video-Metadaten/Videos.csv','Videos/Fixture Video.mp4']);
        $batch=app(TakeoutArchiveAdapter::class)->inventory()['batches'][0];$this->assertNotNull($batch['report']);
        $run=$this->runImport();$item=app(\App\Services\Importing\ImportJournal::class)->item($run,'takeout-manifest');
        $this->assertSame('linked',$item->outcome);$this->assertSame(2,$item->metadata['expected_files']);$this->assertSame(0,$item->metadata['missing_files']);
        unlink($this->root.'/zips/takeout-20260911T193951Z-001.zip');$fallback=$this->runImport();
        $item=app(\App\Services\Importing\ImportJournal::class)->item($fallback,'takeout-manifest');
        $this->assertSame('unsupported',$item->outcome);$this->assertFalse($item->metadata['manifest_available']);
        $this->assertSame(1,SourceRecord::where('source_id','abcdefghijk')->count());
    }
    public function test_manifest_missing_files_are_reported_without_discarding_available_content(): void
    {
        $this->fixture();$this->report(['Videos/Fixture Video.mp4','Videos/Missing video.mp4']);$run=$this->runImport();
        $this->assertSame(1,ImportItem::where('import_run_id',$run->id)->where('type','manifest-file')->where('outcome','missing')->count());
        $this->assertNotNull(SourceRecord::where('source_id','abcdefghijk')->first());
        $this->report(['Videos/Fixture Video.mp4']);$run->update(['status'=>'queued']);app(ImportCenter::class)->run($run);
        $this->assertSame(0,ImportItem::where('import_run_id',$run->id)->where('type','manifest-file')->count());
    }
    public function test_unreadable_optional_report_does_not_block_import_and_retry_clears_its_error(): void
    {
        $this->fixture();file_put_contents($this->root.'/zips/takeout-20260911T193951Z-001.zip','Not a ZIP');$run=$this->runImport();
        $journal=app(\App\Services\Importing\ImportJournal::class);$this->assertSame('failed',$journal->item($run,'takeout-report')->outcome);
        $this->assertSame(1,SourceRecord::where('source_id','abcdefghijk')->count());
        unlink($this->root.'/zips/takeout-20260911T193951Z-001.zip');$this->report(['Videos/Fixture Video.mp4']);
        $run->update(['status'=>'queued']);app(ImportCenter::class)->run($run);$this->assertSame('complete',$journal->item($run,'takeout-report')->outcome);
    }
    public function test_english_directory_and_basic_csv_aliases_use_the_same_content_importer(): void
    {
        $this->zip(1,[
            'Video-Metadata/Videos.csv'=>$this->csv(['Video ID','Video title (original)','Video description (original)'],[['abcdefghijk','English video','Original description']]),
            'Posts/Posts.csv'=>$this->csv(['Post ID','Post text','Image name 1'],[['UgEnglish','Original post','UgEnglish_image.png']]),
            'Comments/Comments.csv'=>$this->csv(['Comment ID','Post ID','Comment text'],[['CEnglish','UgEnglish','Original comment']]),
        ]);
        $this->zip(2,['Videos/English video.mp4'=>pack('N',24).'ftypisom'.str_repeat("\0",12),'Posts/UgEnglish_image.png'=>base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jvyoAAAAASUVORK5CYII=')]);
        $this->runImport();$this->assertSame('Original description',SourceRecord::where('source_id','abcdefghijk')->firstOrFail()->body);
        $this->assertCount(1,SourceRecord::where('source_id','UgEnglish')->firstOrFail()->metadata['media_ids']);
        $this->assertSame('Original comment',SourceRecord::where('source_id','comment:UgEnglish:CEnglish')->firstOrFail()->body);
    }
    public function test_prepared_multipart_folder_imports_in_place_with_optional_report(): void
    {
        $this->fixture();$report=$this->report(['Videos/Fixture Video.mp4']);
        $folder=$this->root.'/zips/prepared';mkdir($folder);
        foreach([1,2] as $n) {
            mkdir($folder.'/part-'.$n);$zip=new ZipArchive;$zip->open($this->root.'/zips/'.$this->batch.'-'.sprintf('%03d',$n).'.zip');$zip->extractTo($folder.'/part-'.$n);$zip->close();
        }
        $zip=new ZipArchive;$zip->open($report);$zip->extractTo($folder.'/report');$zip->close();
        config(['filesystems.disks.takeout.root'=>$this->root.'/zips']);Storage::forgetDisk('takeout');
        $input=app(ImportCenter::class)->prepareInput('youtube-takeout',['batch'=>'folder:prepared']);
        $run=ImportRun::create([...$input,'status'=>'queued']);app(ImportCenter::class)->run($run);
        $video=SourceRecord::where('source_id','abcdefghijk')->firstOrFail();$this->assertCount(1,$video->metadata['media_ids']);
        $media=Media::findOrFail($video->metadata['media_ids'][0]);$this->assertSame('takeout',$media->disk);
        $this->assertFileExists(Storage::disk('takeout')->path($media->path));$this->assertDirectoryDoesNotExist($this->root.'/inbox/archives');
        $this->assertTrue(app(\App\Services\Importing\ImportJournal::class)->item($run,'takeout-manifest')->metadata['manifest_available']);
        app(ImportCenter::class)->run($run->fresh());$this->assertSame(1,SourceRecord::where('source_id','abcdefghijk')->count());
    }
    public function test_short_queue_slices_resume_without_reextracting_or_duplicate_content(): void
    {
        \Illuminate\Support\Facades\Queue::fake();$this->fixture();
        app()->instance(\App\Services\Importing\ImportWorkBudget::class,new \App\Services\Importing\ImportWorkBudget(0));
        $run=ImportRun::create(['source'=>'youtube-takeout','source_options'=>['batch'=>$this->batch,'expected_parts'=>2]]);
        $started=null;$slices=0;
        do {
            app(ImportCenter::class)->run($run->fresh());$run->refresh();$started??=$run->started_at;
            $this->assertTrue($started->equalTo($run->started_at));$this->assertNull($run->error);$slices++;
        } while($run->status==='queued' && $slices<50);
        $this->assertLessThan(50,$slices);$this->assertGreaterThan(2,$slices);$this->assertSame('partial',$run->status);
        $this->assertSame(1,SourceRecord::where('source_id','abcdefghijk')->count());$this->assertSame(2,SourceRecord::where('kind','comment')->count());
        $this->assertSame(2,Collection::firstOrFail()->items()->count());
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\ImportArchive::class,$slices-1);
    }
}
