<?php
namespace App\Http\Controllers;
use App\Models\Media;
use App\Models\SourceRecord;
use Illuminate\Http\Request;
use App\Services\Importing\LocalMediaLinks;
class LocalMediaLinkController extends Controller
{
    public function repair()
    {
        dispatch(new \App\Jobs\RepairLocalMediaLinks);
        return response()->json(['status'=>'queued']);
    }
    public function attach(Request $request, SourceRecord $record, LocalMediaLinks $links)
    {
        $data=$request->validate(['media_id'=>'required|uuid|exists:media,id']);
        $links->repair($record,Media::findOrFail($data['media_id']));
        return response()->json(['status'=>'saved']);
    }
}
