<?php
namespace App\Http\Controllers;
use App\Models\Media;
class MediaTechnicalController extends Controller
{
    public function store(Media $media)
    {
        abort_unless(in_array($media->kind,['video','audio','image']),422);
        dispatch(new \App\Jobs\ProbeMedia($media->id));return response()->json(['status'=>'queued']);
    }
}
