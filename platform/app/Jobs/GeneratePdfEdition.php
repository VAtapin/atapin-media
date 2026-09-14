<?php
namespace App\Jobs;
use App\Models\{Product,SourceRecord,User};
use App\Services\{PdfEditions,CanonicalMediaStorage,BookCatalog};
use App\Services\Importing\ImportedMediaRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\{DB,Storage};

class GeneratePdfEdition implements ShouldQueue
{
    use Queueable;
    public int $tries=1;
    public int $timeout=180;
    public function __construct(public string $type,public int $id,public int $user,public string $version){}
    public function handle(PdfEditions $pdf): void
    {
        $model=$this->type==='product'?Product::class:SourceRecord::class;$subject=$model::find($this->id);
        if(!$subject)return;
        $user=User::find($this->user);if(!$user||!$user->hasPermission($this->type==='product'?'shop.manage':'content.edit')) { $this->failed(new \RuntimeException('permission'));return; }
        $temporary='pdf-work/'.bin2hex(random_bytes(16)).'.pdf';
        try {
            $subject->updateQuietly(['metadata'=>[...($subject->metadata??[]),'pdf_job'=>['status'=>'processing']]]);
            Storage::disk('local')->put($temporary,$pdf->render($subject));
            $stored=app(CanonicalMediaStorage::class)->storePath(Storage::disk('local')->path($temporary),'application/pdf','pdf');
            $filename=$stored['filename'];unset($stored['filename']);$stored['original_name']=$filename;
            DB::transaction(function()use($subject,$stored,$pdf){
                $subject=$subject->newQuery()->lockForUpdate()->findOrFail($subject->id);
                $user=User::find($this->user);abort_unless($user?->hasPermission($this->type==='product'?'shop.manage':'content.edit'),403);
                if($subject instanceof SourceRecord&&!empty($subject->metadata['public_published']))abort_unless($user->hasPermission('content.publish'),403);
                if(!hash_equals($this->version,$pdf->version($subject)))throw new \RuntimeException('stale');
                $media=app(ImportedMediaRegistry::class)->register([...$stored,'title'=>$subject->title.'.pdf','mime'=>'application/pdf','kind'=>'pdf','status'=>'ready','user_id'=>$this->user,'source'=>'pdf-edition','source_id'=>$this->type.':'.$this->id.':'.$this->version]);
                if($subject instanceof Product)app(BookCatalog::class)->attach($subject,$media,'full');
                else $media->usages()->firstOrCreate(['subject_type'=>SourceRecord::class,'subject_id'=>(string)$subject->id,'used_as'=>'pdf']);
                $subject->updateQuietly(['metadata'=>[...($subject->metadata??[]),'pdf_media_id'=>$media->id,'media_ids'=>array_values(array_unique([...($subject->metadata['media_ids']??[]),$media->id])),'pdf_job'=>['status'=>'completed','media_id'=>$media->id]]]);
            });
        } catch(\Throwable $e) { $this->failed($e); }
        finally { Storage::disk('local')->delete($temporary); }
    }
    public function failed(?\Throwable $error): void
    {
        $model=$this->type==='product'?Product::class:SourceRecord::class;
        if($subject=$model::find($this->id))$subject->updateQuietly(['metadata'=>[...($subject->metadata??[]),'pdf_job'=>['status'=>'failed','error'=>__('workspaces.pdf_failed')]]]);
    }
}
