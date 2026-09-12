<?php
namespace App\Services\Importing;
use App\Models\ImportRun;
use App\Models\Media;
use App\Models\SourceRecord;
use App\Services\MediaOriginalLocator;
use App\Services\MediaTechnicalProbe;
use Illuminate\Support\Facades\Storage;
class LocalVideoAuditAdapter implements ImportAdapter
{
    public function source(): string {return 'local-video-check';}
    public function validate(array $input): array {return $input;}
    public function normalize(array $input): array {return ['source'=>$this->source(),'source_kind'=>$this->source(),'source_ref'=>'','source_options'=>[],'target_profile'=>'mixed'];}
    public function import(ImportRun $run): void
    {
        $journal=app(ImportJournal::class); $linked=[];
        foreach(Media::where('kind','video')->with('originals')->orderBy('id')->cursor() as $media) {
            app(ImportProgress::class)->checkpoint($run,'video_check');
            $key='video-check:'.$media->id; if($journal->done($run,$key))continue;
            $outcome='available'; $data=['browser_status'=>'not_checked','preview_url'=>route('media.preview',$media,false),'download_url'=>route('media.download',$media,false)];
            try {
                $location=app(MediaOriginalLocator::class)->find($media);
                if(!$location){$outcome='missing';$data['reason']=__('imports.audit_missing');}
                else {
                    $disk=Storage::disk($location['disk']); $bytes=config('filesystems.disks.'.$location['disk'].'.driver')==='local'?filesize(app(MediaOriginalLocator::class)->path($location)):$disk->size($location['path']);
                    $data=[...$data,...$location,'bytes'=>$bytes,'expected_bytes'=>$media->bytes];
                    if($bytes!==$media->bytes)throw new \RuntimeException(__('imports.audit_size'));
                    if(!in_array($media->mime,['video/mp4','video/webm'],true)){$outcome='unsupported';$data['reason']=__('imports.audit_format');}
                    $technical=app(MediaTechnicalProbe::class)->inspect($media);$data['technical']=$technical;
                    if(!($technical['width']??0)||!($technical['height']??0))throw new \RuntimeException(__('imports.audit_stream'));
                    $media->update(['metadata'=>[...($media->metadata??[]),'technical'=>$technical,'technical_status'=>'ready']]);
                }
            } catch(\Throwable $e){$outcome='failed';$data['reason']=in_array($e->getMessage(),[__('imports.audit_size'),__('imports.audit_stream')],true)?$e->getMessage():__('imports.probe_failed');}
            $journal->record($run,$key,$media->title,'video-check',$outcome,(string)$media->id,$data);
            $run->increment('discovered');$run->increment($outcome==='available'?'imported':'skipped');
            $linked[]=$media->id;
        }
        foreach(SourceRecord::whereIn('kind',['video','short'])->where('source','!=','catalog-reset')->cursor() as $record) {
            app(ImportProgress::class)->checkpoint($run);
            $ids=app(LocalMediaLinks::class)->ids($record);
            if(!Media::whereIn('id',$ids)->where('kind','video')->exists())$journal->record($run,'metadata-only:'.$record->id,$record->title,'content-check','missing',(string)$record->id,['reason'=>__('imports.audit_no_file')]);
        }
    }
}
