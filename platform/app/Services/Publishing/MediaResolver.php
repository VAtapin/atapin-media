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
        return Media::whereIn('id', app(LocalMediaLinks::class)->ids($record))->whereNull('archived_at')->get()
            ->filter(fn (Media $media) => $this->locator->find($media));
    }

    public function video(SourceRecord $record): ?array
    {
        $media = $this->assets($record)->first(fn (Media $item) => $item->kind === 'video');
        $location = $media ? $this->locator->find($media) : null;
        return $media && $location ? ['media' => $media, 'path' => $this->locator->path($location)] : null;
    }

    public function image(SourceRecord $record, ?string $provider = null): ?array
    {
        $assets = $this->assets($record);
        $specific=$provider ? ($record->metadata['platform_metadata'][$provider]['cover_media_id']??null) : null;
        $cover = $specific ?? ($record->metadata['cover_media_id'] ?? null);
        $media = ($cover ? $assets->first(fn($item)=>$item->id===$cover&&$item->kind==='image'&&!$item->archived_at) : null)
            ?? $assets->first(fn (Media $item) => $item->kind === 'image');
        if($specific && (!$media || $media->id!==$specific))throw new \RuntimeException('The selected platform cover is unavailable.');
        $location = $media ? $this->locator->find($media) : null;
        return $media && $location ? ['media' => $media, 'path' => $this->locator->path($location)] : null;
    }

    public function audio(SourceRecord $record): ?array
    {
        $media = $this->assets($record)->first(fn (Media $item) => $item->kind === 'audio');
        $location = $media ? $this->locator->find($media) : null;
        return $media && $location ? ['media' => $media, 'path' => $this->locator->path($location)] : null;
    }
}
