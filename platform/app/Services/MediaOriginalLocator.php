<?php
namespace App\Services;
use App\Models\Media;
use Illuminate\Support\Facades\Storage;
class MediaOriginalLocator
{
    public function path(array $location): string
    {
        $disk=Storage::disk($location['disk']);
        if(config('filesystems.disks.'.$location['disk'].'.driver')!=='local')return $disk->path($location['path']);
        \App\Services\Importing\ImportPath::entry($location['path']);
        $root=$disk->path('');
        $candidate=rtrim($root,'/\\').DIRECTORY_SEPARATOR.$location['path'];
        if(is_link($candidate))throw new \RuntimeException('Original links are not supported.');
        return \App\Services\Importing\ImportPath::resolve($root,$candidate);
    }
    public function find(Media $media): ?array
    {
        $locations=[['disk'=>$media->disk,'path'=>$media->path]];
        foreach($media->originals as $original)$locations[]=['disk'=>$original->disk,'path'=>$original->path];
        foreach($locations as $location) {
            $disk=Storage::disk($location['disk']);
            if(config('filesystems.disks.'.$location['disk'].'.driver')==='local') {
                // Flysystem rejects benign Unicode format characters in original Takeout filenames.
                // Exact native lookup is limited to the configured private root, never a normalized alias.
                try {
                    if(is_file($this->path($location)))return $location;
                } catch(\RuntimeException) { continue; }
            } elseif($disk->exists($location['path']))return $location;
        }
        return null;
    }
}
