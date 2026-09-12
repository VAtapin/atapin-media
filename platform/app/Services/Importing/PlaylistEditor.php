<?php
namespace App\Services\Importing;
use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\SourceRecord;
use Illuminate\Support\Facades\DB;
class PlaylistEditor
{
    public function update(Collection $collection,array $data): void
    {
        DB::transaction(function () use ($collection,$data) {
            $collection=Collection::lockForUpdate()->findOrFail($collection->id);
            $collection->update(['title'=>$data['title'],'description'=>$data['description']??'', 'metadata'=>[...($collection->metadata??[]),'manual_playlist'=>true]]);
            app(\App\Services\Audit::class)->record('playlist.edited',(string)$collection->id);
        });
    }
    public function member(Collection $collection,array $data,?CollectionItem $item=null): void
    {
        DB::transaction(function () use ($collection,$data,$item) {
            $collection=Collection::lockForUpdate()->findOrFail($collection->id);
            $maximum=(int)$collection->items()->max('position');
            if($data['action']==='add') {
                abort_if($collection->items()->count()>=100000,422);
                $record=SourceRecord::findOrFail($data['record_id']);abort_unless(in_array($record->kind,['video','short']),422);
                $exists=$collection->items()->where(function ($q) use ($record,$collection) {
                    $q->where('source_record_id',$record->id);
                    if($record->source===$collection->source) $q->orWhere('source_id',$record->source_id);
                })->exists();
                if(!$exists)$collection->items()->create(['position'=>$maximum+1,'source_record_id'=>$record->id,'source_id'=>$record->source_id,'title'=>$record->title]);
            } else {
                $item=CollectionItem::lockForUpdate()->findOrFail($item?->id);abort_unless($item->collection_id===$collection->id,404);
                if($data['action']==='remove') $item->delete();
                else {
                    $up=$data['action']==='up';
                    $neighbor=$collection->items()->reorder()->where('position',$up?'<':'>',$item->position)->orderBy('position',$up?'desc':'asc')->lockForUpdate()->first();
                    if($neighbor){$position=$item->position;$item->update(['position'=>$maximum+1]);$neighborPosition=$neighbor->position;$neighbor->update(['position'=>$position]);$item->update(['position'=>$neighborPosition]);}
                }
            }
            $collection->update(['metadata'=>[...($collection->metadata??[]),'manual_playlist'=>true]]);
            app(\App\Services\Audit::class)->record('playlist.member.changed',(string)$collection->id,['action'=>$data['action']]);
        });
    }
}
