<?php
namespace App\Services;

use App\Models\{Media,SourceRecord};
use Illuminate\Support\Facades\{DB,Process,Storage};

/** Optional covers and audio, never rewriting or optimizing the original video. */
class MediaDerivatives
{
    public function video(SourceRecord $record): ?Media
    {
        return Media::whereNull('archived_at')->whereIn('id',app(\App\Services\Importing\LocalMediaLinks::class)->ids($record))->where('kind','video')->first();
    }
    public function hasCover(SourceRecord $record): bool
    {
        return Media::whereNull('archived_at')->whereIn('id',app(\App\Services\Importing\LocalMediaLinks::class)->ids($record))->where('kind','image')->exists();
    }
    public function register(string $path,string $mime,string $kind,string $title,?Media $parent=null): Media
    {
        $stored=app(CanonicalMediaStorage::class)->storePath($path,$mime);
        $media=app(\App\Services\Importing\ImportedMediaRegistry::class)->register(['sha256'=>$stored['sha256'],'title'=>mb_substr($title,0,255),'original_name'=>$stored['filename'],
            'kind'=>$kind,'mime'=>$mime,'disk'=>$stored['disk'],'path'=>$stored['path'],'bytes'=>$stored['bytes'],'status'=>'ready',
            'source'=>'derived','source_id'=>$stored['sha256'],'asset_role'=>$kind==='image'?'thumbnail':'audio','parent_id'=>$parent?->id,'metadata'=>['derivative'=>true]]);
        return $media;
    }
    public function temporary(string $extension): string
    {
        $disk=Storage::disk('local');$disk->makeDirectory('media-work');
        return $disk->path('media-work/'.bin2hex(random_bytes(16)).'.'.$extension);
    }
    public function frame(SourceRecord $record): void
    {
        if($this->hasCover($record))return;
        $video=$this->video($record);if(!$video)throw new \RuntimeException('Local video is unavailable.');
        $location=app(MediaOriginalLocator::class)->find($video);if(!$location)throw new \RuntimeException('Local video is unavailable.');
        $source=app(MediaOriginalLocator::class)->path($location);$output=$this->temporary('jpg');
        try{
            $binary=config('platform.media_ffmpeg_binary','ffmpeg');
            foreach([1,0] as $second){
                $result=Process::timeout(90)->run([$binary,'-nostdin','-y','-ss',(string)$second,'-i',$source,'-frames:v','1','-vf','scale=1280:-2','-q:v','3',$output]);
                if($result->successful() && is_file($output) && filesize($output)>0)break;
            }
            if(!is_file($output)||(@getimagesize($output)['mime']??null)!=='image/jpeg')throw new \RuntimeException('Cannot extract cover.');
            $image=$this->register($output,'image/jpeg','image',$record->title,$video);
            DB::transaction(function()use($record,$image){
                $current=SourceRecord::lockForUpdate()->findOrFail($record->id);
                if(!$this->hasCover($current))app(\App\Services\Importing\MediaCoverAssignment::class)->assign($image,$current);
            });
        }finally{if(is_file($output))unlink($output);}
    }
    public function podcast(SourceRecord $record): void
    {
        $ids=app(\App\Services\Importing\LocalMediaLinks::class)->ids($record);
        $audio=Media::whereNull('archived_at')->whereIn('id',$ids)->where('kind','audio')->first();
        if(!$audio){
            $video=$this->video($record);$location=$video?app(MediaOriginalLocator::class)->find($video):null;
            if(!$location)throw new \RuntimeException('Local video is unavailable.');
            $source=app(MediaOriginalLocator::class)->path($location);
            $probe=Process::timeout(30)->run([config('platform.media_ffprobe_binary','ffprobe'),'-v','error','-select_streams','a:0','-show_entries','stream=codec_name','-of','json',$source]);
            if(!$probe->successful())throw new \RuntimeException('Cannot inspect audio.');
            $codec=json_decode($probe->output(),true)['streams'][0]['codec_name']??null;
            if(!$codec)throw new \RuntimeException('Video has no audio stream.');
            $copy=in_array($codec,['aac','mp3'],true);$extension=$codec==='aac'?'m4a':'mp3';$mime=$extension==='m4a'?'audio/mp4':'audio/mpeg';
            $output=$this->temporary($extension);
            try{
                $command=[config('platform.media_ffmpeg_binary','ffmpeg'),'-nostdin','-y','-i',$source,'-map','0:a:0','-vn','-c:a',$copy?'copy':'libmp3lame'];
                if(!$copy)$command=[...$command,'-b:a','128k'];
                $result=Process::timeout(1800)->run([...$command,$output]);
                if(!$result->successful()||!is_file($output)||filesize($output)===0)throw new \RuntimeException('Cannot extract podcast audio.');
                $audio=$this->register($output,$mime,'audio',$record->title,$video);
            }finally{if(is_file($output))unlink($output);}
        }
        DB::transaction(function()use($record,$audio){
            $current=SourceRecord::lockForUpdate()->findOrFail($record->id);
            if(($current->metadata['public_section']??null)!=='podcast')return;
            $current->update(['metadata'=>[...$current->metadata,'podcast_audio_id'=>$audio->id,'media_ids'=>array_values(array_unique([...($current->metadata['media_ids']??[]),$audio->id]))]]);
            $audio->usages()->firstOrCreate(['used_as'=>'audio','subject_type'=>SourceRecord::class,'subject_id'=>(string)$record->id]);
        });
    }
}
