<?php
namespace App\Http\Controllers;
use App\Models\Collection;
use App\Models\CollectionItem;
use App\Services\Importing\PlaylistEditor;
use Illuminate\Http\Request;
class PlaylistEditorController extends Controller
{
    public function update(Request $request,Collection $collection,PlaylistEditor $editor)
    {
        $editor->update($collection,$request->validate(['title'=>'required|string|max:255','description'=>'nullable|string|max:10000']));
        return response()->json(['status'=>'saved']);
    }
    public function add(Request $request,Collection $collection,PlaylistEditor $editor)
    {
        $data=$request->validate(['record_id'=>'required|integer|exists:source_records,id']);$editor->member($collection,[...$data,'action'=>'add']);return response()->json(['status'=>'saved']);
    }
    public function member(Request $request,Collection $collection,CollectionItem $item,PlaylistEditor $editor)
    {
        $editor->member($collection,$request->validate(['action'=>'required|in:up,down,remove']),$item);return response()->json(['status'=>'saved']);
    }
}
