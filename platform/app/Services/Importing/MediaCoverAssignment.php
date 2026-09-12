<?php

namespace App\Services\Importing;

use App\Models\Media;
use App\Models\SourceRecord;
use App\Services\Audit;
use Illuminate\Support\Facades\DB;

class MediaCoverAssignment
{
    public function assign(Media $image, SourceRecord $record): void
    {
        DB::transaction(function () use ($image, $record) {
            $image = Media::lockForUpdate()->findOrFail($image->id);
            $record = SourceRecord::lockForUpdate()->findOrFail($record->id);
            abort_unless($image->kind === 'image' && str_starts_with($image->mime, 'image/'), 422, __('imports.cover_image_required'));
            abort_unless(in_array($record->kind, ['video', 'short'], true), 422, __('imports.cover_video_required'));
            $meta = $record->metadata ?? [];
            $primary = Media::whereIn('id', app(ImportedContentPresentation::class)->mediaIds($meta))->where('kind', 'video')->first();
            abort_if($primary && $image->parent_id && $image->parent_id !== $primary->id, 409, __('imports.cover_parent_conflict'));
            $meta['cover_media_id'] = $image->id;
            $meta['media_ids'] = array_values(array_unique([...($meta['media_ids'] ?? []), $image->id]));
            $record->update(['metadata' => $meta]);
            if ($primary) $primary->update(['metadata'=>[...($primary->metadata ?? []),'cover_media_id'=>$image->id]]);
            $image->update(['asset_role' => 'thumbnail', 'parent_id' => $primary?->id ?? $image->parent_id]);
            $image->usages()->firstOrCreate(['used_as'=>'thumbnail','subject_type'=>SourceRecord::class,'subject_id'=>(string) $record->id]);
            app(Audit::class)->record('media.cover.assigned', $image->id, ['record_id'=>$record->id]);
        });
    }
}
