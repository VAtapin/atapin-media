<?php

namespace App\Services\Importing;

use App\Models\Media;
use App\Models\MediaOriginal;
use Illuminate\Support\Facades\DB;

class ImportedMediaRegistry
{
    public function register(array $attributes): Media
    {
        return \Illuminate\Support\Facades\Cache::lock('media-register:'.$attributes['sha256'],30)->block(15, fn () => DB::transaction(function () use ($attributes) {
            $identity = hash('sha256', $attributes['disk'].'|'.$attributes['path'].'|'.$attributes['sha256']);
            $original = MediaOriginal::where('identity',$identity)->first();
            if ($original) return Media::findOrFail($original->media_id);
            $media = Media::where('source',$attributes['source'])->where('source_id',$attributes['source_id'])->lockForUpdate()->first();
            if ($media && $media->sha256 && $media->sha256 !== $attributes['sha256']) throw new \RuntimeException(__('imports.original_changed'));
            $media ??= Media::where('sha256',$attributes['sha256'])->where('bytes',$attributes['bytes'])->where('mime',$attributes['mime'])->lockForUpdate()->first();
            $media ??= Media::create($attributes);
            if (! $media->sha256) $media->update(['sha256'=>$attributes['sha256']]);
            MediaOriginal::firstOrCreate(['identity'=>$identity], [
                'media_id'=>$media->id, 'disk'=>$attributes['disk'],'path'=>$attributes['path'],
                'source'=>$attributes['source'],'source_id'=>$attributes['source_id'], 'original_name'=>$attributes['original_name'],
                'sha256'=>$attributes['sha256'],'bytes'=>$attributes['bytes'],
            ]);
            return $media;
        }));
    }
}
