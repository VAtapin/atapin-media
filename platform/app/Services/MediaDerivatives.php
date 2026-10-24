<?php
namespace App\Services;

use App\Models\{Media,SourceRecord};
use Illuminate\Support\Facades\{DB,Process,Storage};

/** Public derivatives; source media is never rewritten. */
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
    public function hls(SourceRecord $record): string
    {
        $video=$this->video($record);if(!$video)throw new \RuntimeException('Local video is unavailable.');
        $location=app(MediaOriginalLocator::class)->find($video);if(!$location)throw new \RuntimeException('Local video is unavailable.');
        $source=app(MediaOriginalLocator::class)->path($location);$sha=hash_file('sha256',$source);if(!$sha)throw new \RuntimeException('Cannot verify source video.');
        $relative='hls/'.$sha;$playlist=Storage::disk(CanonicalMediaStorage::DISK)->path($relative.'/index.m3u8');
        $fallback=Storage::disk(CanonicalMediaStorage::DISK)->path($relative.'/playback.mp4');
        if(is_file($playlist)&&filesize($playlist)>0&&is_file($fallback)&&filesize($fallback)>0){if(!chmod(dirname(dirname($playlist)),0755))throw new \RuntimeException('Cannot make public HLS directory readable.');$this->makeHlsTreePublic(dirname($playlist));return '/media/'.$relative.'/index.m3u8';}
        $root=Storage::disk('local')->path('media-work/hls');if(!is_dir($root)&&!mkdir($root,0700,true)&&!is_dir($root))throw new \RuntimeException('HLS work directory is unavailable.');
        $lock=fopen($root.'/'.$sha.'.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new \RuntimeException('Cannot lock HLS conversion.');
        $work=$root.'/'.$sha.'-'.bin2hex(random_bytes(8));$stage=Storage::disk(CanonicalMediaStorage::DISK)->path('.hls-'.$sha.'-'.bin2hex(random_bytes(8)));$final=Storage::disk(CanonicalMediaStorage::DISK)->path($relative);
        try{
            if(is_file($playlist)&&filesize($playlist)>0&&is_file($fallback)&&filesize($fallback)>0){if(!chmod(dirname(dirname($playlist)),0755))throw new \RuntimeException('Cannot make public HLS directory readable.');$this->makeHlsTreePublic(dirname($playlist));return '/media/'.$relative.'/index.m3u8';}
            if(!mkdir($work,0700,true))throw new \RuntimeException('HLS work directory is unavailable.');
            $probe=Process::timeout(45)->run([config('platform.media_ffprobe_binary','ffprobe'),'-v','error','-show_entries','stream=codec_type,width,height','-of','json',$source]);
            if(!$probe->successful())throw new \RuntimeException('Cannot inspect source video.');
            $streams=json_decode($probe->output(),true,16,JSON_THROW_ON_ERROR)['streams']??[];$videoInfo=collect($streams)->firstWhere('codec_type','video')??[];$hasAudio=(bool)collect($streams)->firstWhere('codec_type','audio');$sourceHeight=(int)($videoInfo['height']??0);$sourceWidth=(int)($videoInfo['width']??0);
            if($sourceHeight<240||$sourceWidth<240)throw new \RuntimeException('Source video resolution is too small for HLS.');
            $profiles=[['height'=>240,'bitrate'=>450000],['height'=>360,'bitrate'=>800000],['height'=>480,'bitrate'=>1400000],['height'=>720,'bitrate'=>2800000],['height'=>1080,'bitrate'=>5000000]];
            $profiles=array_values(array_filter($profiles,fn($profile)=>$profile['height']<=$sourceHeight));
            if(!$profiles)$profiles=[['height'=>$sourceHeight-($sourceHeight%2),'bitrate'=>450000]];
            $filter=[];$args=['-nostdin','-hide_banner','-loglevel','error','-y','-i',$source];$split=implode('',array_map(fn($i)=>'[vsrc'.$i.']',array_keys($profiles)));
            $filter[]='[0:v:0]split='.count($profiles).$split;
            foreach($profiles as $i=>$profile){$height=$profile['height'];$width=max(2,(int)(($sourceWidth*$height/$sourceHeight)/2)*2);$filter[]='[vsrc'.$i.']scale='.$width.':'.$height.':flags=lanczos[v'.$i.']';}
            $args=[...$args,'-filter_complex',implode(';',$filter)];$varMap=[];
            foreach($profiles as $i=>$profile){$args=[...$args,'-map','[v'.$i.']'];if($hasAudio)$args=[...$args,'-map','0:a:0'];$args=[...$args,'-c:v:'.$i,'libx264','-preset','veryfast','-threads','2','-profile:v:'.$i,'main','-pix_fmt','yuv420p','-b:v:'.$i,(string)$profile['bitrate'],'-maxrate:v:'.$i,(string)round($profile['bitrate']*1.15),'-bufsize:v:'.$i,(string)($profile['bitrate']*2)];if($hasAudio)$args=[...$args,'-c:a:'.$i,'aac','-b:a:'.$i,'128k'];$varMap[]='v:'.$i.($hasAudio?',a:'.$i:'').',name:'.$profile['height'].'p';}
            $args=[...$args,'-force_key_frames','expr:gte(t,n_forced*6)','-f','hls','-hls_time','6','-hls_playlist_type','vod','-hls_flags','independent_segments','-master_pl_name','index.m3u8','-var_stream_map',implode(' ',$varMap),'-hls_segment_filename',$work.'/%v/segment_%05d.ts',$work.'/%v/index.m3u8'];
            $result=Process::timeout(1800)->run([config('platform.media_ffmpeg_binary','ffmpeg'),...$args]);
            if(!$result->successful()||!is_file($work.'/index.m3u8'))throw new \RuntimeException('HLS encoding failed: '.mb_substr(trim($result->errorOutput()),0,500));
            foreach($profiles as $profile){$variant=$work.'/'.$profile['height'].'p/index.m3u8';if(!is_file($variant)||!glob($work.'/'.$profile['height'].'p/segment_*.ts'))throw new \RuntimeException('HLS output is incomplete.');}
            $fallbackResult=Process::timeout(1200)->run([config('platform.media_ffmpeg_binary','ffmpeg'),'-nostdin','-hide_banner','-loglevel','error','-y','-i',$source,'-map','0:v:0','-map','0:a:0?','-vf','scale=-2:min(720\\,ih)','-c:v','libx264','-preset','veryfast','-threads','2','-pix_fmt','yuv420p','-b:v','2800k','-maxrate','3200k','-bufsize','5600k','-c:a','aac','-b:a','128k','-movflags','+faststart',$work.'/playback.mp4']);
            if(!$fallbackResult->successful()||!is_file($work.'/playback.mp4')||filesize($work.'/playback.mp4')===0)throw new \RuntimeException('MP4 fallback encoding failed: '.mb_substr(trim($fallbackResult->errorOutput()),0,500));
            if(!is_dir(dirname($final))&&!mkdir(dirname($final),0755,true)&&!is_dir(dirname($final)))throw new \RuntimeException('Public HLS directory is unavailable.');
            if(!chmod(dirname($final),0755))throw new \RuntimeException('Cannot make public HLS directory readable.');
            if(!\Illuminate\Support\Facades\File::copyDirectory($work,$stage))throw new \RuntimeException('Cannot stage HLS output.');
            $this->makeHlsTreePublic($stage);
            if(is_dir($final)){if(!\Illuminate\Support\Facades\File::deleteDirectory($final))throw new \RuntimeException('Cannot replace incomplete HLS output.');}
            if(!rename($stage,$final))throw new \RuntimeException('Cannot publish HLS output.');
            return '/media/'.$relative.'/index.m3u8';
        }finally{if(is_dir($work))\Illuminate\Support\Facades\File::deleteDirectory($work);if(is_dir($stage))\Illuminate\Support\Facades\File::deleteDirectory($stage);flock($lock,LOCK_UN);fclose($lock);}
    }
    private function makeHlsTreePublic(string $root): void
    {
        $items=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::SELF_FIRST);
        foreach($items as $item){$mode=$item->isDir()?0755:0644;if(!chmod($item->getPathname(),$mode))throw new \RuntimeException('Cannot set public HLS file permissions.');}
        if(!chmod($root,0755))throw new \RuntimeException('Cannot set public HLS directory permissions.');
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
