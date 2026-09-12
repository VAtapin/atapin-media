<?php
namespace App\Services;
use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
class MediaTechnicalProbe
{
    protected function process(array $arguments): Process {return new Process($arguments,timeout:60);}
    public function inspect(Media $media): array
    {
        $path=Storage::disk($media->disk)->path($media->path);
        if(!is_file($path)) throw new \RuntimeException(__('imports.unavailable'));
        if($media->kind==='image') {
            $size=getimagesize($path);if(!$size) throw new \RuntimeException(__('imports.probe_failed'));
            return ['width'=>$size[0],'height'=>$size[1],'format'=>$media->mime];
        }
        $process=$this->process([config('platform.media_ffprobe_binary'),'-v','error','-protocol_whitelist','file,pipe','-show_entries','format=duration,format_name:stream=codec_type,codec_name,width,height','-of','json','-i',$path]);
        $process->mustRun();
        if(strlen($process->getOutput())>1024*1024) throw new \RuntimeException(__('imports.probe_failed'));
        return $this->normalize(json_decode($process->getOutput(),true,32,JSON_THROW_ON_ERROR));
    }
    public function normalize(array $data): array
    {
        $video=collect($data['streams']??[])->firstWhere('codec_type','video');$audio=collect($data['streams']??[])->firstWhere('codec_type','audio');
        $result=['format'=>mb_substr($data['format']['format_name']??'',0,200),'codec'=>mb_substr($video['codec_name']??$audio['codec_name']??'',0,100)];
        if(isset($data['format']['duration'])&&is_numeric($data['format']['duration'])&&(float)$data['format']['duration']>=0) $result['duration']=(float)$data['format']['duration'];
        foreach(['width','height'] as $key) if(isset($video[$key])&&(int)$video[$key]>0) $result[$key]=(int)$video[$key];
        return $result;
    }
}
