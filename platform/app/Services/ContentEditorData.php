<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Validation\Rule;

class ContentEditorData
{
    public static function rules(): array
    {
        return ['body_format'=>'sometimes|in:plain,html','platform_metadata'=>'sometimes|array:youtube,facebook,instagram,telegram,x',
            'platform_metadata.*'=>'array:title,body','platform_metadata.*.title'=>'nullable|string|max:255','platform_metadata.*.body'=>'nullable|string|max:10000',
            'project_id'=>'nullable|integer|exists:projects,id','author'=>'nullable|string|max:255',
            'seo_title'=>'nullable|string|max:255','seo_description'=>'nullable|string|max:500',
            'transcript'=>'nullable|string|max:100000','guest'=>'nullable|string|max:255',
            'external_podcast_url'=>'nullable|url:http,https|max:1000',
            'taxonomy_term_ids'=>'sometimes|array|max:30','taxonomy_term_ids.*'=>'integer|distinct|exists:taxonomy_terms,id',
            'cover_media_id'=>['nullable','uuid',Rule::exists('media','id')->whereNull('archived_at')->whereIn('mime',['image/jpeg','image/png','image/webp','image/gif'])],
            'additional_media_ids'=>'sometimes|array|max:50','additional_media_ids.*'=>['uuid','distinct',Rule::exists('media','id')->whereNull('archived_at')]];
    }

    public static function metadata(array $metadata, array $data): array
    {
        foreach (['body_format','platform_metadata','author','seo_title','seo_description','transcript','guest','external_podcast_url','cover_media_id'] as $key) if (array_key_exists($key,$data)) $metadata[$key] = $data[$key];
        if(array_key_exists('additional_media_ids',$data)||array_key_exists('cover_media_id',$data)) $metadata['media_ids'] = array_values(array_unique([...($metadata['media_ids'] ?? []),...($data['additional_media_ids'] ?? []),...(!empty($data['cover_media_id'])?[$data['cover_media_id']]:[])]));
        return $metadata;
    }
}
