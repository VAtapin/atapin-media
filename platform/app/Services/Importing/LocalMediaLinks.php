<?php
namespace App\Services\Importing;

use App\Models\Media;
use App\Models\SourceRecord;
use Illuminate\Support\Facades\DB;

class LocalMediaLinks
{
    public function ids(SourceRecord $record): array
    {
        $ids = app(ImportedContentPresentation::class)->mediaIds($record->metadata ?? []);
        $ids = [...$ids, ...Media::whereHas('usages', fn ($q) => $q->where('subject_type',SourceRecord::class)->where('subject_id',(string)$record->id))->pluck('id')->all()];
        if ($record->source === 'youtube' && preg_match('/^[A-Za-z0-9_-]{11}$/D',$record->source_id)) {
            $id = $record->source_id;
            $ids = [...$ids, ...Media::where('metadata->youtube_id',$id)->pluck('id')->all()];
            // Filter again literally: SQL LIKE treats underscores in IDs as wildcards.
            $ids = [...$ids, ...Media::where('source','youtube')->where('source_id','like',$id.':%')->get(['id','source_id'])
                ->filter(fn ($media) => str_starts_with($media->source_id,$id.':'))->pluck('id')->all()];
        }
        return array_values(array_unique($ids));
    }

    public function repair(SourceRecord $record, ?Media $chosen = null): void
    {
        DB::transaction(function () use ($record,$chosen) {
            $record = SourceRecord::lockForUpdate()->findOrFail($record->id);
            if ($chosen) abort_unless($chosen->kind === 'video' && in_array($record->kind,['video','short']),422);
            $ids = $this->ids($record); if ($chosen) $ids[]=$chosen->id;
            $media = Media::whereIn('id',array_unique($ids))->get();
            $metadata = $record->metadata ?? [];
            $metadata['media_ids'] = array_values(array_unique([...($metadata['media_ids']??[]),...$media->pluck('id')->all()]));
            $primary = $chosen ?? $media->firstWhere('kind','video');
            foreach ($media as $asset) {
                $role = $asset->kind === 'video' ? 'video' : ($asset->asset_role ?? $asset->metadata['role'] ?? null);
                if (in_array($role,['video','thumbnail','subtitles'])) {
                    $metadata['media'][$role] = array_values(array_unique([...($metadata['media'][$role]??[]),$asset->id]));
                    if ($primary && in_array($role,['thumbnail','subtitles']) && !$asset->parent_id)
                        $asset->update(['parent_id'=>$primary->id,'asset_role'=>$role]);
                }
                $asset->usages()->firstOrCreate(['subject_type'=>SourceRecord::class,'subject_id'=>(string)$record->id,'used_as'=>$role ?? 'attachment']);
            }
            if ($primary && isset($metadata['cover_media_id']) && $media->contains('id',$metadata['cover_media_id']))
                $primary->update(['metadata'=>[...($primary->metadata ?? []),'cover_media_id'=>$metadata['cover_media_id']]]);
            $record->update(['metadata'=>$metadata]);
        });
    }
}
