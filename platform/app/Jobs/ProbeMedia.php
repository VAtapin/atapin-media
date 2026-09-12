<?php
namespace App\Jobs;
use App\Models\Media;
use App\Services\MediaTechnicalProbe;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
class ProbeMedia implements ShouldQueue, ShouldBeUnique
{
    use Queueable;
    public int $timeout=90;
    public int $tries=1;
    public int $uniqueFor=120;
    public function __construct(public string $mediaId) {}
    public function uniqueId(): string {return $this->mediaId;}
    public function handle(MediaTechnicalProbe $probe): void
    {
        $media=Media::findOrFail($this->mediaId);
        try {$technical=$probe->inspect($media);$status='ready';}
        catch(\Throwable $error) {$technical=null;$status='failed';}
        DB::transaction(function () use ($technical,$status,$media) {
            $current=Media::lockForUpdate()->findOrFail($media->id);
            if($current->path!==$media->path||$current->disk!==$media->disk||$current->sha256!==$media->sha256)return;
            $metadata=$current->metadata??[];$metadata['technical_status']=$status;
            if($technical!==null)$metadata['technical']=$technical;
            $current->update(['metadata'=>$metadata]);
        });
    }
    public function failed(?\Throwable $error): void
    {
        DB::transaction(function () {$media=Media::lockForUpdate()->find($this->mediaId);if($media)$media->update(['metadata'=>[...($media->metadata??[]),'technical_status'=>'failed']]);});
    }
}
