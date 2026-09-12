<?php
namespace Tests\Feature;
use App\Models\ImportRun;
use App\Models\Media;
use App\Models\SourceRecord;
use App\Models\Collection;
use App\Services\ArchiveImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
class ArchiveImportTest extends TestCase
{
    use RefreshDatabase;
    private string $root;
    protected function setUp():void{parent::setUp();$this->root=sys_get_temp_dir().'/atapin-import-'.bin2hex(random_bytes(10));mkdir($this->root);}
    protected function tearDown():void{File::deleteDirectory($this->root);parent::tearDown();}
    private function write(string $path,mixed $value):void
    {
        File::ensureDirectoryExists(dirname($this->root.'/'.$path));
        file_put_contents($this->root.'/'.$path,is_array($value)?json_encode($value):$value);
    }
    private function runArchive(string $source):ImportRun
    {
        config(['platform.'.$source.'_root'=>$this->root]);
        $run=ImportRun::create(['source'=>$source]);app(ArchiveImporter::class)->import($run);return $run->fresh();
    }
    public function test_intake_registers_original_without_copy_and_repeat_does_not_duplicate():void
    {
        $this->write('files/notes.txt','Hallo');
        $this->write('files/notes.txt.metadata.json',['schema'=>'atapin-intake/v1','id'=>'intake-1','stored_path'=>'files/notes.txt',
            'original_name'=>'Notizen.txt','bytes'=>5,'completed_at'=>'2026-09-11','received_at'=>'2026-09-11','original_relative_path'=>'notes.txt']);
        $run=$this->runArchive('intake');$this->assertSame('complete',$run->status);$this->assertSame(1,$run->imported);
        $this->assertDatabaseHas('media',['disk'=>'intake','path'=>'files/notes.txt','status'=>'unsorted']);
        $media=Media::first();$media->update(['title'=>'Edited title']);
        $this->runArchive('intake');$this->assertDatabaseCount('media',1);$this->assertSame('Edited title',$media->fresh()->title);
        $this->assertSame('Hallo',file_get_contents($this->root.'/files/notes.txt'));
    }
    public function test_youtube_posts_and_playlist_order_are_preserved():void
    {
        $id='abcdefghijk';
        $this->write('items/'.$id.'/metadata.json',['sources'=>['shorts'],'youtube'=>['title'=>'Hoffnung','description'=>'Text','upload_date'=>'20260101']]);
        $this->write('items/'.$id.'/state.json',['state'=>'partial','phases'=>[]]);
        $this->write('posts/Ug-post/post.json',['text'=>'Ein Beitrag','published_label'=>'vor 2 Jahren','image_files'=>[]]);
        $this->write('playlists/PLtest.json',['id'=>'PLtest','title'=>'Serie','ordered_items'=>[
            ['position'=>1,'id'=>$id,'title'=>'Hoffnung'],['position'=>2,'id'=>null,'title'=>'Unavailable'],['position'=>3,'id'=>$id,'title'=>'Repeated']]]);
        $this->runArchive('youtube');$this->runArchive('youtube');
        $this->assertDatabaseCount('source_records',2);$this->assertDatabaseHas('source_records',['source_id'=>$id,'kind'=>'short','status'=>'review']);
        $this->assertSame('vor 2 Jahren',SourceRecord::where('kind','post')->first()->metadata['published_label']);
        $collection=Collection::firstOrFail();$this->assertSame([$id,null,$id],$collection->items->pluck('source_id')->all());
        $this->assertSame([1,2,3],$collection->items->pluck('position')->all());
    }
    public function test_escape_paths_and_size_mismatch_are_not_registered():void
    {
        $this->write('wrong.txt','short');
        foreach(['../outside.txt','wrong.txt'] as $i=>$path)$this->write('record'.$i.'.metadata.json',[
            'schema'=>'atapin-intake/v1','id'=>'bad'.$i,'stored_path'=>$path,'original_name'=>'File','bytes'=>100,'completed_at'=>'now','received_at'=>'now','original_relative_path'=>'']);
        $run=$this->runArchive('intake');$this->assertSame('partial',$run->status);$this->assertDatabaseCount('media',0);
        $this->assertCount(2,$run->notes);
    }
}
