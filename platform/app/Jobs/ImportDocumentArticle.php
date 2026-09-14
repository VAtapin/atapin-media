<?php
namespace App\Jobs;
use App\Models\{Media,User};
use App\Services\DocumentArticleImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
class ImportDocumentArticle implements ShouldQueue
{
    use Queueable;
    public int $tries=1;
    public function __construct(public string $mediaId,public int $actorId) {}
    public function handle(DocumentArticleImport $service): void
    {
        $media=Media::find($this->mediaId);if(!$media)return;
        try{$user=User::find($this->actorId);if(!$user?->hasPermission('content.edit')||!$user->hasPermission('media.view'))throw new \RuntimeException('Permission revoked.');$record=$service->import($media);$state=['status'=>'completed','record_id'=>$record->id];}
        catch(\Throwable){$state=['status'=>'failed'];}
        $media->refresh();$media->update(['metadata'=>[...($media->metadata??[]),'document_import'=>$state]]);
    }
    public function failed(?\Throwable $exception): void
    {
        \Illuminate\Support\Facades\DB::transaction(function(){
            $media=Media::lockForUpdate()->find($this->mediaId);
            if($media&&($media->metadata['document_import']['status']??null)==='queued')$media->update(['metadata'=>[...($media->metadata??[]),'document_import'=>['status'=>'failed']]]);
        });
    }
}
