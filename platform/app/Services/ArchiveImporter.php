<?php
namespace App\Services;
use App\Models\ImportRun;
use App\Models\Media;
use App\Models\SourceRecord;
use App\Services\Importing\ContentMetadataImporter;
class ArchiveImporter
{
    private string $root;
    private ImportRun $run;
    private array $notes=[];
    public function import(ImportRun $run):void
    {
        $this->run=$run; $root=realpath(config('platform.'.$run->source.'_root'));
        if (!$root || !is_dir($root)) throw new \RuntimeException('Configured archive directory is missing.');
        $this->root=$root; $run->update(['status'=>'running']);
        if ($run->source==='intake') $this->intake(); else $this->youtube();
        $run->update(['status'=>$this->notes?'partial':'complete','notes'=>$this->notes]);
    }

    public function runIntake(ImportRun $run): void
    {
        $this->run = $run;
        $root = realpath(config('platform.intake_root'));
        if (!$root || !is_dir($root)) {
            throw new \RuntimeException('Configured archive directory is missing.');
        }
        $this->root = $root;
        $run->update(['status'=>'running']);
        $this->intake();
        $run->update(['status'=>$this->notes?'partial':'complete','notes'=>$this->notes]);
    }

    public function runYoutube(ImportRun $run): void
    {
        $this->run = $run;
        $root = realpath(config('platform.youtube_root'));
        if (!$root || !is_dir($root)) {
            throw new \RuntimeException('Configured archive directory is missing.');
        }
        $this->root = $root;
        $run->update(['status'=>'running']);
        $this->youtube();
        $run->update(['status'=>$this->notes?'partial':'complete','notes'=>$this->notes]);
    }
    private function path(string $relative):string
    {
        if (str_contains($relative,"\0") || str_starts_with($relative,'/') || str_contains($relative,'\\')) throw new \RuntimeException('Invalid archive path.');
        $path=realpath($this->root.'/'.$relative);
        if (!$path || !str_starts_with($path,$this->root.DIRECTORY_SEPARATOR) || !is_file($path)) throw new \RuntimeException('Archive file is missing or outside its root.');
        return $path;
    }
    private function json(string $relative):array
    {
        $path=$this->path($relative);
        if (filesize($path)>64*1024*1024) throw new \RuntimeException('Metadata exceeds 64 MB.');
        $data=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
        if (!is_array($data)) throw new \RuntimeException('Invalid archive metadata.'); return $data;
    }
    private function attempt(string $label,callable $fn):void
    {
        try {$fn();} catch(\Throwable $e) {
            if(count($this->notes)<100)$this->notes[]=$label.': '.$e->getMessage();
            $this->run->increment('skipped');
        }
    }
    private function media(string $relative,string $id,string $name,?int $bytes=null,?string $sha=null,array $metadata=[]):Media
    {
        $path=$this->path($relative); $size=filesize($path);
        $this->run->increment('discovered');
        if($bytes!==null && $size!==$bytes)throw new \RuntimeException('Archive file size differs from its manifest.');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($path)?:'application/octet-stream';
        $metadata['target_profile'] = $this->run->target_profile ?: $this->defaultTargetProfile();
        $media=Media::firstOrCreate(['source'=>$this->run->source,'source_id'=>$id],[
            'title'=>mb_substr($name,0,255),'original_name'=>mb_substr(basename($name),0,255),'kind'=>MediaLibrary::kind($mime),
            'mime'=>$mime,'bytes'=>$size,'disk'=>$this->run->source,'path'=>$relative,'sha256'=>$sha,'asset_role'=>$metadata['role']??null,
            'metadata'=>$metadata,'status'=>'unsorted','user_id'=>$this->run->user_id]);
        $this->run->increment($media->wasRecentlyCreated?'imported':'skipped');return $media;
    }
    private function intake():void
    {
        $iterator=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root,\FilesystemIterator::SKIP_DOTS));
        foreach($iterator as $file) {
            if(!$file->isFile() || !str_ends_with($file->getFilename(),'.metadata.json'))continue;
            $relative=str_replace(DIRECTORY_SEPARATOR,'/',substr($file->getPathname(),strlen($this->root)+1));
            $this->attempt($relative,function()use($relative){
                $record=$this->json($relative);
                if(($record['schema']??'')!=='atapin-intake/v1'||empty($record['completed_at']))return;
                $this->media($record['stored_path'],$record['id'],$record['original_name'],(int)$record['bytes'],null,
                    ['manifest'=>$relative,'received_at'=>$record['received_at'],'original_relative_path'=>$record['original_relative_path']]);
            });
        }
    }
    private function youtube():void
    {
        foreach(glob($this->root.'/items/*/metadata.json')?:[] as $file) {
            $id=basename(dirname($file));if(!preg_match('/^[a-zA-Z0-9_-]{11}$/D',$id))continue;
            $this->attempt($id,function()use($id){
                $raw=$this->json('items/'.$id.'/metadata.json');$info=$raw['youtube']??[];
                if(!in_array($info['availability']??null,[null,'public'],true))return;
                $state=$this->json('items/'.$id.'/state.json');$mediaIds=[];
                foreach(($state['phases']??[]) as $phase=>$details){
                    if(!in_array($details['state']??null,['complete','partial'],true))continue;
                    foreach($details['files']??[] as $asset){
                        if(!in_array($phase,['video','thumbnail','subtitles'],true))continue;
                        $relative='items/'.$id.'/'.$asset['path'];
                        $this->attempt($relative,function()use($id,$asset,$relative,$phase,$info,&$mediaIds){
                            $name=$phase==='video'?($info['title']??$id).'.'.pathinfo($asset['path'],PATHINFO_EXTENSION):basename($asset['path']);
                            $media=$this->media($relative,$id.':'.hash('sha256',$asset['path']),$name,(int)$asset['bytes'],$asset['sha256']??null,['youtube_id'=>$id,'role'=>$phase]);
                            $mediaIds[$phase][]=$media->id;
                        });
                    }
                }
                $primary = $mediaIds['video'][0] ?? null;
                if ($primary) Media::whereIn('id', [...($mediaIds['thumbnail']??[]), ...($mediaIds['subtitles']??[])])->whereNull('parent_id')->update(['parent_id'=>$primary]);
                $record = SourceRecord::firstOrCreate(['source'=>'youtube','source_id'=>$id],[
                    'kind'=>in_array('shorts',$raw['sources']??[],true)?'short':'video','title'=>$info['title']??$id,'body'=>$info['description']??'','status'=>'unsorted',
                    'metadata'=>['archive_path'=>'items/'.$id,'sources'=>$raw['sources']??[],'media'=>$mediaIds,'target_profile'=>$this->run->target_profile?:'videos',
                        'published_date'=>$info['upload_date']??null,'duration'=>$info['duration']??null,'channel'=>$info['channel']??null,
                        'webpage_url'=>$info['webpage_url']??'https://www.youtube.com/watch?v='.$id,
                        'comments_path'=>is_file($this->root.'/items/'.$id.'/comments.json')?'items/'.$id.'/comments.json':null,
                        'collection_state'=>$state['state']??'partial']]);
                $metadata = $record->metadata;
                foreach ($mediaIds as $phase => $ids) $metadata['media'][$phase] = array_values(array_unique([...($metadata['media'][$phase]??[]), ...$ids]));
                $record->update(['metadata'=>$metadata]);
                if (is_file($this->root.'/items/'.$id.'/comments.json')) {
                    $comments = $this->json('items/'.$id.'/comments.json');
                    app(ContentMetadataImporter::class)->comments('youtube', $id, $comments['comments']??[], ['import_id'=>$this->run->id]);
                }
            });
        }
        foreach(glob($this->root.'/posts/*/post.json')?:[] as $file){
            $id=basename(dirname($file));if(!preg_match('/^[a-zA-Z0-9_-]+$/D',$id))continue;
            $this->attempt($id,function()use($id){
                $post=$this->json('posts/'.$id.'/post.json');$images=[];
                foreach($post['image_files']??[] as $asset){
                    $relative='posts/'.$id.'/'.$asset['file'];
                    $this->attempt($relative,function()use($relative,$asset,$id,&$images){$images[]=$this->media($relative,$id.':'.$asset['file'],$asset['file'],null,null,['youtube_post'=>$id])->id;});
                }
                $record = SourceRecord::firstOrCreate(['source'=>'youtube','source_id'=>$id],[
                    'kind'=>'post','title'=>mb_substr($post['text']??$id,0,120),'body'=>$post['text']??'','status'=>'unsorted',
                    'metadata'=>['archive_path'=>'posts/'.$id,'published_label'=>$post['published_label']??null,'url'=>$post['url']??null,'images'=>$images,'target_profile'=>$this->run->target_profile?:'posts',
                        'likes_label'=>$post['likes_label']??null,'links'=>$post['links']??[],'raw'=>$post]]);
                $metadata = $record->metadata;
                $metadata['images'] = array_values(array_unique([...($metadata['images']??[]), ...$images]));
                $record->update(['metadata'=>$metadata]);
                $poll = $post['raw']['backstageAttachment']['pollRenderer'] ?? null;
                if (is_array($poll)) app(ContentMetadataImporter::class)->record('youtube', 'poll:'.$id, 'poll',
                    mb_substr($post['text']??$id,0,120), $post['text']??'', ['parent_source_id'=>$id,'poll'=>$poll,'import_id'=>$this->run->id]);
            });
        }
        foreach(glob($this->root.'/playlists/*.json')?:[] as $file){
            $relative='playlists/'.basename($file);
            $this->attempt($relative,function()use($relative){
                $playlist=$this->json($relative);$id=$playlist['id']??null;if(!$id)return;
                app(ContentMetadataImporter::class)->playlist('youtube',$playlist,['archive_path'=>$relative,'cover_file'=>$playlist['cover_file']??null]);
            });
        }
        if(is_file($this->root.'/report.json')){
            $report=$this->json('report.json');
            if(($report['posts']['possible_history_limit']??false))$this->notes[]='YouTube public posts may omit older history. See archive report.json.';
        }
    }

    private function defaultTargetProfile(): string
    {
        return match ($this->run->source) {
            'youtube' => 'videos',
            'intake' => 'media_library',
            default => 'mixed',
        };
    }
}
