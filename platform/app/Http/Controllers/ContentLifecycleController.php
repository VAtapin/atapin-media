<?php
namespace App\Http\Controllers;
use App\Models\SourceRecord;
use App\Services\Audit;
use App\Services\Importing\ContentLifecycle;
use App\Services\Importing\ContentAssets;
use Illuminate\Http\Request;
class ContentLifecycleController extends Controller
{
    public function destroy(Request $request,SourceRecord $record,ContentLifecycle $lifecycle,Audit $audit)
    {
        $request->validate(['confirmation'=>'required|in:DELETE']);
        $lifecycle->delete($record);$audit->record('content.trashed',(string)$record->id);
        return response()->json(['status'=>'deleted']);
    }
    public function restore(SourceRecord $record,ContentLifecycle $lifecycle,Audit $audit)
    {
        abort_unless($record->trashed(),409);$lifecycle->restore($record);$audit->record('content.restored',(string)$record->id);
        return response()->json(['status'=>'restored']);
    }
    public function assets(Request $request,SourceRecord $record,ContentAssets $assets,Audit $audit)
    {
        $data=$request->validate(['action'=>'required|in:attach,replace,detach','role'=>'required|in:video,cover,attachment',
            'media_id'=>'required_unless:action,detach|nullable|uuid|exists:media,id','old_media_id'=>'required_if:action,detach|nullable|uuid|exists:media,id']);
        $assets->change($record,$data);$audit->record('content.assets_changed',(string)$record->id,\Illuminate\Support\Arr::only($data,['action','role','media_id','old_media_id']));
        return response()->json(['status'=>'saved']);
    }
}
