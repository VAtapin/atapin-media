<?php
namespace App\Jobs;
use App\Models\{ImportItem,ImportRun,SourceRecord};
use App\Services\{PublicImageDownload,MediaDerivatives};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\{ShouldQueue,ShouldBeUnique};
use Illuminate\Support\Facades\DB;
class DownloadImportedImage implements ShouldQueue,ShouldBeUnique
{
    use Queueable;
    public int $tries=1;
    public int $timeout=90;
    public int $uniqueFor=120;
    public function __construct(public int $id){}
    public function uniqueId(): string{return (string)$this->id;}
    public function handle(PublicImageDownload $download,MediaDerivatives $media): void
    {
        $item=ImportItem::find($this->id);if(!$item||$item->outcome==='connected')return;
        $record=SourceRecord::find($item->metadata['record_id']??0);if(!$record)return;
        $file=null;
        try{
            $file=$download->download($item->metadata['url']);
            $image=$media->register($file['path'],$file['mime'],'image',$record->title);
            DB::transaction(function()use($record,$image,$item){
                $current=SourceRecord::lockForUpdate()->findOrFail($record->id);
                $meta=$current->metadata;$role=$item->metadata['role']??'image';$meta['media_ids']=array_values(array_unique([...($meta['media_ids']??[]),$image->id]));
                $meta['remote_images'][$role]=$image->id;
                if(in_array($role,['cover','thumbnail'],true)&&!($meta['cover_media_id']??null))$meta['cover_media_id']=$image->id;
                $current->update(['metadata'=>$meta]);
                $image->usages()->firstOrCreate(['used_as'=>$role,'subject_type'=>SourceRecord::class,'subject_id'=>(string)$record->id]);
                $item->update(['outcome'=>'connected','subject_id'=>$image->id,'metadata'=>[...$item->metadata,'reason'=>null]]);
            });
        }catch(\Throwable $error){$this->failed($error);}
        finally{if($file&&is_file($file['path']))unlink($file['path']);}
    }
    public function failed(?\Throwable $error): void
    {
        $item=ImportItem::find($this->id);if(!$item)return;
        $item->update(['outcome'=>'failed','metadata'=>[...$item->metadata,'reason'=>__('imports.external_image_failed')]]);
        ImportRun::whereKey($item->import_run_id)->where('status','complete')->update(['status'=>'partial']);
    }
}
