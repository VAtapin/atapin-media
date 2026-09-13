<?php

namespace App\Services\Publishing;

use App\Models\Media;
use App\Models\SourceRecord;
use App\Services\MediaOriginalLocator;
use App\Services\Importing\LocalMediaLinks;

class MediaResolver
{
    public function __construct(private readonly MediaOriginalLocator $locator)
    {
    }

    public function assets(SourceRecord $record): \Illuminate\Support\Collection
    {
        return Media::whereIn('id', app(LocalMediaLinks::class)->ids($record))->get()
            ->filter(fn (Media $media) => $this->locator->find($media));
    }

    public function video(SourceRecord $record): ?array
    {
        $media = $this->assets($record)->first(fn (Media $item) => $item->kind === 'video');
        $location = $media ? $this->locator->find($media) : null;
        return $media && $location ? ['media' => $media, 'path' => $this->locator->path($location)] : null;
    }

    public function image(SourceRecord $record): ?array
    {
        $assets = $this->assets($record);
        $cover = $record->metadata['cover_media_id'] ?? null;
        $media = ($cover ? $assets->firstWhere('id', $cover) : null)
            ?? $assets->first(fn (Media $item) => $item->kind === 'image');
        $location = $media ? $this->locator->find($media) : null;
        return $media && $location ? ['media' => $media, 'path' => $this->locator->path($location)] : null;
    }
}
