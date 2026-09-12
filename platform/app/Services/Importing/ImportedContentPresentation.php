<?php

namespace App\Services\Importing;

use App\Models\Media;
use App\Models\SourceRecord;
use Illuminate\Support\Facades\Storage;

class ImportedContentPresentation
{
    public function references(SourceRecord $record): array
    {
        $references=$record->metadata['references']??[];
        if($parent=$record->metadata['parent_source_id']??null)$references[]=['type'=>'parent','id'=>$parent,'text'=>__('imports.parent')];
        if(($reply=$record->metadata['parent_comment_id']??null)&&$parent)$references[]=['type'=>'parent','id'=>'comment:'.$parent.':'.$reply,'text'=>__('imports.kind_comment')];
        return array_map(function($reference)use($record){
            $id=$reference['id']??null;$type=$reference['type']??'';$url=null;
            if(is_string($id)) {
                if($type==='playlistLink') {
                    $target=\App\Models\Collection::where('source',$record->source)->where('source_id',$id)->first();
                    if($target)$url=route('content.playlist',$target);
                } else {
                    if(in_array($type,['channelLink','mention'],true))$id='channel:'.$id;
                    $target=SourceRecord::where('source',$record->source)->where('source_id',$id)->first();
                    if($target)$url=route('content.show',$target);
                }
            }
            // External URLs remain raw provenance only; never a navigation fallback.
            return ['type'=>$type,'text'=>$reference['text']??$id??'', 'detail_url'=>$url,'missing'=>is_string($id)&&!$url];
        },$references);
    }
    public function mediaIds(array $metadata): array
    {
        $ids = array_merge($metadata['media_ids'] ?? [], $metadata['images'] ?? []);
        if (isset($metadata['cover_media_id'])) $ids[] = $metadata['cover_media_id'];
        foreach ($metadata['media'] ?? [] as $group) if (is_array($group)) $ids = array_merge($ids, $group);
        return array_values(array_unique(array_filter($ids, 'is_string')));
    }

    public function assets(SourceRecord $record): \Illuminate\Support\Collection
    {
        return Media::whereIn('id', app(LocalMediaLinks::class)->ids($record))->get()->map(function ($media) {
            $available = (bool)app(\App\Services\MediaOriginalLocator::class)->find($media);
            return ['id' => $media->id, 'title' => $media->title, 'kind' => $media->kind, 'mime' => $media->mime,
                'role' => $media->asset_role, 'available' => $available, 'bytes' => $media->bytes,
                'download_url' => $available ? route('media.download', $media) : null,
                'preview_url' => $available && in_array($media->mime, ['image/jpeg','image/png','image/webp','image/gif','audio/mpeg','audio/ogg','video/mp4','video/webm','application/pdf'], true) ? route('media.preview', $media) : null];
        });
    }

    public function externalUrl(array $metadata, string $source, ?string $id, string $kind): ?string
    {
        foreach ([$metadata['url'] ?? null, $metadata['webpage_url'] ?? null, $metadata['raw']['webpage_url'] ?? null, $metadata['raw']['url'] ?? null] as $url) {
            if (is_string($url) && filter_var($url, FILTER_VALIDATE_URL) && parse_url($url, PHP_URL_SCHEME) === 'https'
                && ! parse_url($url, PHP_URL_USER) && ! parse_url($url, PHP_URL_PASS)) return $url;
        }
        if ($source !== 'youtube' || ! $id) return null;
        if ($kind === 'playlist' && preg_match('/^[A-Za-z0-9_-]+$/', $id)) return 'https://www.youtube.com/playlist?list='.rawurlencode($id);
        if (in_array($kind, ['video', 'short'], true) && preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) return 'https://www.youtube.com/watch?v='.rawurlencode($id);
        if (in_array($kind, ['post', 'poll'], true) && preg_match('/^Ug[A-Za-z0-9_-]+$/', $id)) return 'https://www.youtube.com/post/'.rawurlencode($id);
        return null;
    }
}
