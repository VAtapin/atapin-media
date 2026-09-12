<?php

namespace App\Services\Importing;

use App\Models\Media;
use App\Models\MediaClassification;
use App\Models\SourceRecord;
use App\Models\Tag;
use App\Services\Audit;
use Illuminate\Support\Facades\DB;

class UndoMediaClassification
{
    public function undo(Media $media, MediaClassification $classification): void
    {
        DB::transaction(function () use ($media, $classification) {
            $media = Media::lockForUpdate()->findOrFail($media->id);
            $classification = MediaClassification::lockForUpdate()->findOrFail($classification->id);
            abort_unless($classification->media_id === $media->id, 404);
            $changes = $classification->applied_changes ?? [];
            $state = app(ContentState::class);
            abort_unless($classification->status === 'applied' && isset($changes['before'], $changes['after_version'])
                && $state->version($media) === $changes['after_version'], 409, __('imports.undo_conflict'));
            $record = isset($changes['managed_record_id']) ? SourceRecord::lockForUpdate()->find($changes['managed_record_id']) : null;
            if ($record) abort_unless($state->version($record) === ($changes['managed_record_version'] ?? ''), 409, __('imports.undo_conflict'));
            $tags = Tag::whereIn('name', $changes['before']['tags'])->pluck('id')->all();
            abort_unless(count($tags) === count($changes['before']['tags']), 409, __('imports.undo_conflict'));
            $media->update($changes['before']['fields']);
            $media->tags()->sync($tags);
            if ($record) {
                if ($changes['before_record'] ?? null) $record->update($changes['before_record']['fields']);
                else $record->update(['metadata' => [...($record->metadata ?? []), 'library_only' => true]]);
            }
            $classification->update(['status' => 'undone']);
            app(Audit::class)->record('media.classification.undone', $media->id, ['classification_id' => $classification->id]);
        });
    }
}
