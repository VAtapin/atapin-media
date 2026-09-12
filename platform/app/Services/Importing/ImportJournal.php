<?php
namespace App\Services\Importing;
use App\Models\ImportRun;
use App\Models\ImportItem;
class ImportJournal
{
    public function item(ImportRun $run,string $key): ?ImportItem {return ImportItem::where('import_run_id',$run->id)->where('key',hash('sha256',$key))->first();}
    public function done(ImportRun $run,string $key,array $signature=[]): bool
    {
        $item=$this->item($run,$key);
        return $item && !in_array($item->outcome,['pending','failed']) && (!$signature || ($item->metadata['signature']??[])===$signature);
    }
    public function record(ImportRun $run,string $key,string $label,string $type,string $outcome,?string $id=null,array $metadata=[]): ImportItem
    {
        return ImportItem::updateOrCreate(['import_run_id'=>$run->id,'key'=>hash('sha256',$key)],['label'=>mb_substr($label,0,4000),'type'=>$type,'outcome'=>$outcome,'subject_id'=>$id,'metadata'=>$metadata]);
    }
    public function summary(ImportRun $run): array
    {
        return ImportItem::where('import_run_id',$run->id)->whereNotIn('type',['archive-entry','checkpoint'])->selectRaw('outcome, COUNT(*) as total')->groupBy('outcome')->pluck('total','outcome')->all();
    }
}
