<?php
namespace App\Services\Importing;
use App\Models\Media;
use App\Models\SourceRecord;
use App\Models\Tag;
use App\Services\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContentAssignment
{
    public function media(Media $media, array $data, string $origin = 'manual'): void
    {
        DB::transaction(function () use ($media, $data, $origin) {
            $metadata = $media->metadata ?? [];
            $metadata['target_profile'] = $data['target_profile'] ?? $metadata['target_profile'] ?? 'media_library';
            if (isset($data['summary'])) $metadata['summary'] = $data['summary'];
            $media->update(['title' => $data['title'] ?? $media->title, 'status' => $data['status'] ?? 'ready',
                'metadata' => $metadata, 'classification_origin' => $origin, 'classified_at' => now()]);
            if ($origin === 'manual') $media->update(['classification_confidence' => null, 'classification_version' => null]);
            if (isset($data['tags'])) {
                $ids = [];
                foreach ($data['tags'] as $name) {
                    $name = trim($name); if ($name === '') continue;
                    $ids[] = Tag::firstOrCreate(['name' => $name], ['slug' => Str::slug($name).'-'.substr(hash('sha256', $name), 0, 12)])->id;
                }
                $media->tags()->sync($ids);
            }
            $kind = match ($metadata['target_profile']) { 'videos' => 'video', 'shorts' => 'short', 'posts' => 'post', default => null };
            if ($kind) {
                $existing = SourceRecord::where(function ($query) use ($media) {
                    foreach (['media_ids', 'images', 'media->video', 'media->thumbnail', 'media->subtitles'] as $key) $query->orWhereJsonContains('metadata->'.$key, $media->id);
                });
                $existing = $existing->first();
                $record = $existing ?? SourceRecord::firstOrCreate(['source' => $media->source ?? 'upload', 'source_id' => 'media:'.$media->id],
                    ['kind' => $kind, 'title' => $media->title, 'body' => $metadata['summary'] ?? '', 'metadata' => ['media_ids' => [$media->id]], 'status' => 'unsorted']);
                if (! $existing || $origin === 'manual') $record->update(['kind' => $kind, 'title' => $media->title]);
                $media->usages()->firstOrCreate(['used_as' => 'original', 'subject_type' => SourceRecord::class, 'subject_id' => (string) $record->id]);
            }
            app(Audit::class)->record('media.assigned', $media->id, ['origin' => $origin, 'target_profile' => $metadata['target_profile']]);
        });
    }

    public function record(SourceRecord $record, array $data, string $origin = 'manual'): void
    {
        $metadata = $record->metadata;
        $metadata['classification_origin'] = $origin;
        if ($origin === 'manual') unset($metadata['classification']);
        if (isset($data['summary'])) $metadata['summary'] = $data['summary'];
        if (isset($data['tags'])) $metadata['tags'] = $data['tags'];
        $record->update(['title' => $data['title'] ?? $record->title, 'body' => array_key_exists('body', $data) ? ($data['body'] ?? '') : $record->body,
            'kind' => $data['kind'] ?? $record->kind, 'status' => $data['status'] ?? 'ready', 'metadata' => $metadata]);
        app(Audit::class)->record('content.assigned', (string) $record->id, ['origin' => $origin]);
    }
}
