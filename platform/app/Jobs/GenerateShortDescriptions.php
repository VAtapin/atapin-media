<?php
namespace App\Jobs;

use App\Models\SourceRecord;
use App\Services\ContentShortDescriptions;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class GenerateShortDescriptions implements ShouldQueue
{
    use Queueable;
    public int $tries=1;
    public int $timeout=180;
    public function __construct(public array $versions, public string $token) {}
    public function handle(ContentShortDescriptions $service): void
    {
        $all=SourceRecord::whereIn('id',array_keys($this->versions))->get();
        foreach($all as $record)if(($record->metadata['short_description_job']['token']??null)===$this->token&&$service->version($record)!==($this->versions[(string)$record->id]??null)) {
            $record->updateQuietly(['metadata'=>[...$record->metadata,'short_description_job'=>['state'=>'superseded']]]);
        }
        $records=$all->filter(fn($r)=>
            ($r->metadata['short_description_job']['token']??null)===$this->token && $service->version($r)===($this->versions[(string)$r->id]??null)
            && ($r->metadata['short_description_origin']??null)!=='manual' && !($r->metadata['archive_data']??false))->values()->all();
        if(!$records)return;
        $results=$service->generate($records);
        foreach($records as $record)DB::transaction(function()use($record,$service,$results){
            $current=SourceRecord::lockForUpdate()->find($record->id);
            if(!$current || ($current->metadata['short_description_job']['token']??null)!==$this->token)return;
            if($service->version($current)!==$this->versions[(string)$record->id]){$current->updateQuietly(['metadata'=>[...$current->metadata,'short_description_job'=>['state'=>'superseded']]]);return;}
            $meta=$current->metadata; $summary=$results[(string)$record->id];
            $meta['short_description']=$summary; $meta['short_description_origin']='ai';
            $meta['short_description_job']=['state'=>$summary===''?'insufficient':'completed'];
            $current->update(['metadata'=>$meta]);
        });
    }
    public function failed(?\Throwable $error): void
    {
        foreach(array_keys($this->versions) as $id)DB::transaction(function()use($id){
            $record=SourceRecord::lockForUpdate()->find($id);
            if(!$record || ($record->metadata['short_description_job']['token']??null)!==$this->token)return;
            $record->update(['metadata'=>[...$record->metadata,'short_description_job'=>['state'=>'failed']]]);
        });
    }
}
