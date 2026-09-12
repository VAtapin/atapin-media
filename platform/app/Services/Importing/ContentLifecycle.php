<?php
namespace App\Services\Importing;
use App\Models\SourceRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContentLifecycle
{
    public function delete(SourceRecord $record): void
    {
        DB::transaction(function()use($record){
            $record=SourceRecord::lockForUpdate()->findOrFail($record->id);
            $batch=(string)Str::uuid();
            $record->update(['metadata'=>[...($record->metadata??[]),'trash_batch'=>$batch]]);
            foreach(SourceRecord::where('source',$record->source)->where('metadata->parent_source_id',$record->source_id)->whereIn('kind',['comment','poll'])->lockForUpdate()->get() as $child){
                $child->update(['metadata'=>[...($child->metadata??[]),'trash_parent'=>$record->id,'trash_batch'=>$batch]]);$child->delete();
            }
            $record->delete();
        });
    }
    public function restore(SourceRecord $record): void
    {
        DB::transaction(function()use($record){
            $record=SourceRecord::onlyTrashed()->lockForUpdate()->findOrFail($record->id);
            foreach(SourceRecord::onlyTrashed()->where('metadata->trash_parent',$record->id)->where('metadata->trash_batch',$record->metadata['trash_batch']??'')->lockForUpdate()->get() as $child){
                $metadata=$child->metadata;unset($metadata['trash_parent'],$metadata['trash_batch']);$child->update(['metadata'=>$metadata]);$child->restore();
            }
            $metadata=$record->metadata;unset($metadata['trash_batch'],$metadata['trash_parent']);$record->update(['metadata'=>$metadata]);$record->restore();
        });
    }
}
