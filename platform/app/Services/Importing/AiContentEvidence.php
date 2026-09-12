<?php

namespace App\Services\Importing;

use App\Models\Media;
use App\Models\SourceRecord;
use Illuminate\Support\Facades\Storage;

class AiContentEvidence
{
    public function build($item): array
    {
        $evidence = ['title' => $item->title, 'kind' => $item->kind, 'source' => $item->source];
        $parts = []; $image = null; $ids = [];
        if ($item instanceof Media) {
            $evidence['original_name'] = $item->original_name; $evidence['mime'] = $item->mime;
            if (str_starts_with($item->mime, 'text/') && $item->bytes <= 1024 * 1024) $parts[] = $this->text($item);
            if (in_array($item->mime, ['image/jpeg','image/png','image/webp'], true) && $item->bytes <= 10 * 1024 * 1024)
                $image = 'data:'.$item->mime.';base64,'.base64_encode(Storage::disk($item->disk)->get($item->path));
            $relatedIds = array_filter([$item->id, $item->parent_id]);
            $records = SourceRecord::where(function ($query) use ($relatedIds) {
                foreach ($relatedIds as $id) foreach (['media_ids','images','media->video','media->thumbnail','media->subtitles'] as $key) $query->orWhereJsonContains('metadata->'.$key, $id);
            })->limit(3)->get();
            $ids = [$item->id, ...$item->assets()->pluck('id')->all()];
        } else {
            $records = collect([$item]);
            if (isset($item->metadata['poll'])) $evidence['poll'] = mb_substr(json_encode($item->metadata['poll'], JSON_THROW_ON_ERROR), 0, 4000);
        }
        if (! empty($item->metadata['summary'])) $parts[] = mb_substr($item->metadata['summary'], 0, 4000);
        if (! empty($item->metadata['description'])) $parts[] = mb_substr($item->metadata['description'], 0, 4000);
        foreach ($records as $record) {
            $description = $record->metadata['original_description'] ?? '';
            if ($description !== '') $parts[] = mb_substr($record->title."\n".$description,0,8000);
            if (! empty($record->body) && $record->body !== $description) $parts[] = mb_substr($record->title."\n".$record->body, 0, 8000);
            $ids = array_merge($ids, app(ImportedContentPresentation::class)->mediaIds($record->metadata ?? []));
        }
        foreach (Media::whereIn('id', array_unique($ids))->get() as $media) {
            if ($media->bytes <= 1024 * 1024 && in_array(strtolower(pathinfo($media->original_name, PATHINFO_EXTENSION)), ['srt','vtt'], true)) {
                $subtitle = $this->text($media);
                $subtitle = preg_replace('/^(WEBVTT.*|\d+|.*-->.*)$/m', '', $subtitle);
                $parts[] = mb_substr(trim(strip_tags($subtitle)), 0, 6000);
                break;
            }
        }
        $evidence['body'] = mb_substr(implode("\n\n", array_filter($parts)), 0, 12000);
        return ['evidence' => $evidence, 'image' => $image,
            'sufficient' => $image !== null || trim($evidence['body']) !== '' || ! empty($evidence['poll'])];
    }

    private function text(Media $media): string
    {
        $stream = Storage::disk($media->disk)->readStream($media->path);
        if (! is_resource($stream)) return '';
        try {return mb_substr(fread($stream, 65536) ?: '', 0, 12000);}
        finally {fclose($stream);}
    }
}
