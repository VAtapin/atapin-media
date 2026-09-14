<?php
namespace App\Services\Importing;
use App\Models\{ImportRun,SourceRecord};
class RemoteImageImport
{
    public function queue(ImportRun $run,SourceRecord $record,string $url,string $role='image'): void
    {
        $key='remote-image:'.$record->id.':'.hash('sha256',$url);$journal=app(ImportJournal::class);
        $item=$journal->item($run,$key);if($item)return;
        $item=$journal->record($run,$key,$record->title.' · '.$role,'remote-image','pending',null,['url'=>$url,'record_id'=>$record->id,'role'=>$role]);
        dispatch((new \App\Jobs\DownloadImportedImage($item->id))->afterCommit());
    }
}
