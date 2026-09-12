<?php
namespace App\Services;
use App\Models\Media;
use Illuminate\Support\Facades\Storage;
class MediaOriginalLocator
{
    public function find(Media $media): ?array
    {
        $locations=[['disk'=>$media->disk,'path'=>$media->path]];
        foreach($media->originals as $original)$locations[]=['disk'=>$original->disk,'path'=>$original->path];
        foreach($locations as $location)if(Storage::disk($location['disk'])->exists($location['path']))return $location;
        return null;
    }
}
