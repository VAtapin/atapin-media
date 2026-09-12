<?php

namespace App\Services\Importing;

use App\Models\Media;

class ContentState
{
    public function snapshot($item): array
    {
        $keys = $item instanceof Media ? ['title','status','metadata','classification_origin','classification_confidence','classification_version','classified_at'] : ['title','body','kind','status','metadata'];
        $fields = [];
        foreach ($keys as $key) $fields[$key] = $key === 'metadata' ? ($item->metadata ?? []) : $item->getRawOriginal($key);
        $context = [];
        foreach ($item instanceof Media ? ['kind','mime','bytes','sha256','parent_id','archived_at'] : ['source','source_id'] as $key) $context[$key] = $item->getRawOriginal($key);
        return ['fields' => $fields, 'tags' => $item instanceof Media ? $item->tags()->orderBy('name')->pluck('name')->all() : [], 'context' => $context];
    }

    public function version($item): string
    {
        return hash('sha256', json_encode($this->snapshot($item), JSON_THROW_ON_ERROR));
    }
}
