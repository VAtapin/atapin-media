<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\Media;
use App\Services\Importing\ContentAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MediaOrganization
{
    public function apply(array $data): int
    {
        return DB::transaction(function () use ($data) {
            $files = Media::whereIn('id', $data['ids'])->lockForUpdate()->get();
            if ($files->count() !== count($data['ids'])) throw ValidationException::withMessages(['ids' => __('imports.selection_missing')]);
            $collection = isset($data['collection_id']) ? Collection::where('source', 'local')->lockForUpdate()->findOrFail($data['collection_id']) : null;
            foreach ($files as $media) {
                if (isset($data['status']) || isset($data['target_profile']) || ! empty($data['add_tags'])) {
                    $tags = array_values(array_unique([...$media->tags()->pluck('name')->all(), ...($data['add_tags'] ?? [])]));
                    if (count($tags) > 30) throw ValidationException::withMessages(['add_tags' => __('imports.too_many_tags')]);
                    app(ContentAssignment::class)->media($media, ['title' => $media->title,
                        'status' => $data['status'] ?? $media->status, 'target_profile' => $data['target_profile'] ?? ($media->metadata['target_profile'] ?? 'media_library'),
                        'tags' => $tags]);
                }
                if (array_key_exists('archived', $data)) $media->update(['archived_at' => $data['archived'] ? now() : null]);
                if ($collection && ! $collection->media()->where('media.id', $media->id)->exists()) {
                    $collection->media()->attach($media->id, ['position' => ((int) $collection->media()->max('collection_media.position')) + 1]);
                }
                app(Audit::class)->record('media.organized', $media->id, ['archived' => $data['archived'] ?? null, 'collection_id' => $collection?->id]);
            }
            return $files->count();
        });
    }

    public function move(Collection $collection, Media $media, string $direction): void
    {
        DB::transaction(function () use ($collection, $media, $direction) {
            $collection = Collection::where('source', 'local')->lockForUpdate()->findOrFail($collection->id);
            $current = $collection->media()->where('media.id', $media->id)->firstOrFail();
            $position = $current->pivot->position;
            $other = $collection->media()->where('collection_media.position', $direction === 'up' ? '<' : '>', $position)
                ->orderBy('collection_media.position', $direction === 'up' ? 'desc' : 'asc')->first();
            if (! $other) return;
            $collection->media()->updateExistingPivot($media->id, ['position' => $other->pivot->position]);
            $collection->media()->updateExistingPivot($other->id, ['position' => $position]);
            app(Audit::class)->record('collection.reordered', (string) $collection->id);
        });
    }
}
