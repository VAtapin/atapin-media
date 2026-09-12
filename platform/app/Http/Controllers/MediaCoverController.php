<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\SourceRecord;
use App\Services\Importing\MediaCoverAssignment;
use Illuminate\Http\Request;

class MediaCoverController extends Controller
{
    public function store(Request $request, Media $media, MediaCoverAssignment $assignment)
    {
        $data = $request->validate(['record_id'=>'required|integer|exists:source_records,id']);
        $assignment->assign($media, SourceRecord::findOrFail($data['record_id']));
        return response()->json(['status'=>'saved']);
    }
}
