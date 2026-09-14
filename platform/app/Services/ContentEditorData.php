<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Validation\Rule;

class ContentEditorData
{
    public static function rules(): array
    {
        return ['workflow_stage'=>'sometimes|in:idea,script,production,review,approved','slug'=>'nullable|string|max:180|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/','locale'=>'nullable|in:de,en','episode_number'=>'nullable|integer|min:1|max:100000','season'=>'nullable|integer|min:1|max:1000','body_format'=>'sometimes|in:plain,html','platform_metadata'=>'sometimes|array:youtube,facebook,instagram,telegram,x',
            'platform_metadata.*'=>'array:title,body,cover_media_id,hashtags','platform_metadata.*.title'=>'nullable|string|max:255','platform_metadata.*.body'=>'nullable|string|max:10000',
            'platform_metadata.*.cover_media_id'=>['nullable','uuid',Rule::exists('media','id')->whereNull('archived_at')->whereIn('mime',['image/jpeg','image/png','image/webp'])],
            'platform_metadata.*.hashtags'=>'sometimes|array|max:30','platform_metadata.*.hashtags.*'=>'string|max:80|regex:/^#?[\p{L}\p{N}_]+$/u',
            'public_published_at'=>'nullable|date_format:Y-m-d|before_or_equal:today',
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
        if(array_key_exists('public_published_at',$data)){
            if($data['public_published_at'])$metadata['public_published_at']=$data['public_published_at'];
            else unset($metadata['public_published_at']);
        }
        foreach (['workflow_stage','slug','locale','episode_number','season','body_format','platform_metadata','author','seo_title','seo_description','transcript','guest','external_podcast_url','cover_media_id'] as $key) if (array_key_exists($key,$data)) $metadata[$key] = $data[$key];
        if(array_key_exists('additional_media_ids',$data)||array_key_exists('cover_media_id',$data)) $metadata['media_ids'] = array_values(array_unique([...($metadata['media_ids'] ?? []),...($data['additional_media_ids'] ?? []),...(!empty($data['cover_media_id'])?[$data['cover_media_id']]:[])]));
        if(isset($data['platform_metadata']))foreach($data['platform_metadata'] as $platform)if(!empty($platform['cover_media_id']))$metadata['media_ids']=array_values(array_unique([...($metadata['media_ids']??[]),$platform['cover_media_id']]));
        return $metadata;
    }
}
