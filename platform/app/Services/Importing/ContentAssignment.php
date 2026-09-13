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
            // A classified file is not a reconstructed post/video. Importers own complete content records.
            if ($origin === 'ai') $metadata['target_profile'] = 'media_library';
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
            $managed = SourceRecord::withTrashed()->where('source', $media->source ?? 'upload')->where('source_id', 'media:'.$media->id)->first();
            abort_if($kind && $managed?->trashed(),422,__('imports.trash_restore_first'));
            $canonical = $media->kind === 'video' && !$media->parent_id ? SourceRecord::where(function ($query) use ($media) {
                foreach(['media_ids','media->video'] as $key)$query->orWhereJsonContains('metadata->'.$key,$media->id);
            })->first() : null;
            if(!$kind && $canonical && $origin==='manual') $canonical->update(['metadata'=>[...($canonical->metadata??[]),'library_only'=>true]]);
            if (! $kind && $managed && $origin === 'manual') $managed->update(['metadata' => [...($managed->metadata ?? []), 'library_only' => true]]);
            if ($kind) {
                $existing = SourceRecord::where(function ($query) use ($media) {
                    foreach (['media_ids', 'images', 'media->video', 'media->thumbnail', 'media->subtitles'] as $key) $query->orWhereJsonContains('metadata->'.$key, $media->id);
                });
                $existing = $managed ?? ($media->kind === 'video' && ! $media->parent_id ? $existing->first() : null);
                $record = $existing ?? SourceRecord::firstOrCreate(['source' => $media->source ?? 'upload', 'source_id' => 'media:'.$media->id],
                    ['kind' => $kind, 'title' => $media->title, 'body' => $metadata['summary'] ?? '', 'metadata' => ['media_ids' => [$media->id]], 'status' => 'unsorted']);
                if (! $existing || $origin === 'manual') $record->update(['kind' => $kind, ...($record->source_id === 'media:'.$media->id ? ['title'=>$media->title] : [])]);
                if($origin==='manual')$record->update(['metadata'=>[...($record->metadata??[]),'library_only'=>false]]);
                if ($record->source_id === 'media:'.$media->id && ($origin === 'manual' || $media->status === 'ready'))
                    $record->update(['metadata' => [...($record->metadata ?? []), 'library_only' => false]]);
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
        if ($origin === 'manual' && isset($data['target_profile'])) $metadata['library_only'] = $data['target_profile']==='media_library';
        elseif ($origin === 'manual' && !isset($metadata['library_only'])) $metadata['library_only']=false;
        $kind=match($data['target_profile']??'') {'videos'=>'video','shorts'=>'short','posts'=>'post','polls'=>'poll','comments'=>'comment',default=>$data['kind']??$record->kind};
        if($origin==='ai' && ($metadata['takeout']??false))$kind=$record->kind;
        if($origin==='manual'&&array_key_exists('public_published',$data)) {
            abort_if($data['public_published']&&(!in_array($kind,['video','short','post','poll','comment','live_chat'],true)||($data['status']??$record->status)!=='ready'||($metadata['archive_data']??false)||($metadata['library_only']??false)),422,__('imports.public_ready_required'));
            $metadata['public_published']=(bool)$data['public_published'];
            if($data['public_published'])$metadata['public_published_at']??=now()->toIso8601String();
        }
        if($origin==='manual'&&array_key_exists('public_homepage',$data)) {
            abort_if($data['public_homepage']&&!in_array($kind,['video','short'],true),422,__('imports.public_homepage_video_only'));
            $metadata['public_homepage']=(bool)$data['public_homepage'];
        }
        if(!in_array($kind,['video','short'],true))unset($metadata['public_homepage']);
        if($origin==='manual'&&isset($data['public_section']))$metadata['public_section']=$data['public_section'];
        if (isset($data['summary'])) $metadata['summary'] = $data['summary'];
        if (isset($data['tags'])) $metadata['tags'] = $data['tags'];
        $record->update(['title' => $data['title'] ?? $record->title, 'body' => array_key_exists('body', $data) ? ($data['body'] ?? '') : $record->body,
            'kind' => $kind, 'status' => $data['status'] ?? $record->status, 'metadata' => $metadata]);
        if (in_array($kind, ['video','short'], true) && (($metadata['public_published'] ?? false) || ($metadata['public_homepage'] ?? false))) {
            foreach (app(LocalMediaLinks::class)->ids($record) as $mediaId) {
                if (Media::whereKey($mediaId)->where('kind','video')->exists())
                    \App\Jobs\PreparePublicVideo::dispatch((string)$mediaId)->afterCommit();
            }
        }
        app(Audit::class)->record('content.assigned', (string) $record->id, ['origin' => $origin]);
    }
}
