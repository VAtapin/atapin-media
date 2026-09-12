<?php
namespace App\Services\Importing;
use App\Models\SourceRecord;
use App\Models\Media;
use App\Models\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class CatalogReset
{
    public function candidates()
    {
        $images=Media::where('kind','image')->pluck('id')->map(fn($id)=>'media:'.$id);
        return SourceRecord::where(fn($q)=>$q->whereIn('source',['youtube','youtube-service'])->where(fn($q)=>$q->whereNull('metadata->takeout')->orWhere('metadata->takeout',false)))
            ->orWhere(fn($q)=>$q->where('source','!=','catalog-reset')->where('kind','post')->whereIn('source_id',$images));
    }
    private function playlists() {return Collection::whereIn('source',['youtube','youtube-service'])->where(fn($q)=>$q->whereNull('metadata->takeout')->orWhere('metadata->takeout',false));}
    public function preview(): array
    {
        $resets=SourceRecord::where('source','catalog-reset')->latest()->get()->pluck('metadata')
            ->concat(Collection::where('source','catalog-reset')->latest()->get()->pluck('metadata'))
            ->map(fn($metadata)=>$metadata['catalog_reset']??null)->filter()->unique('id')->values();
        return ['records'=>$this->candidates()->count(),'playlists'=>$this->playlists()->count(),'resets'=>$resets];
    }
    public function reset(): array
    {
        return DB::transaction(function() {
            $active=\App\Models\ImportRun::whereIn('status',['queued','running','stop_requested'])->where('source','!=','local-video-check')->exists();
            abort_if($active,409,__('imports.reset_busy'));
            $id=(string)Str::uuid(); $count=0;
            foreach($this->candidates()->lockForUpdate()->get() as $record) {
                $record->update(['source'=>'catalog-reset','source_id'=>(string)$record->id,'metadata'=>[...($record->metadata??[]),
                    'catalog_reset'=>['id'=>$id,'source'=>$record->source,'source_id'=>$record->source_id,'kind'=>$record->kind,'library_only'=>$record->metadata['library_only']??null,'at'=>now()->toIso8601String()],'library_only'=>true]]);$count++;
            }
            $playlists=$this->playlists()->lockForUpdate()->get();
            foreach($playlists as $collection)$collection->update(['source'=>'catalog-reset','source_id'=>(string)$collection->id,
                'metadata'=>[...($collection->metadata??[]),'catalog_reset'=>['id'=>$id,'source'=>$collection->source,'source_id'=>$collection->source_id]]]);
            return ['reset_id'=>$id,'records'=>$count,'playlists'=>$playlists->count()];
        });
    }
    public function restore(string $id): void
    {
        DB::transaction(function()use($id) {
            foreach([SourceRecord::class,Collection::class] as $class)foreach($class::where('source','catalog-reset')->where('metadata->catalog_reset->id',$id)->lockForUpdate()->get() as $record) {
                $saved=$record->metadata['catalog_reset'];
                abort_if($class::where('source',$saved['source'])->where('source_id',$saved['source_id'])->exists(),409,__('imports.reset_conflict'));
                $metadata=$record->metadata;unset($metadata['catalog_reset']);
                if($class===SourceRecord::class){if($saved['library_only']===null)unset($metadata['library_only']);else $metadata['library_only']=$saved['library_only'];}
                $record->update(['source'=>$saved['source'],'source_id'=>$saved['source_id'],'metadata'=>$metadata]);
            }
        });
    }
}
