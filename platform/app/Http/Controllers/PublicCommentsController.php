<?php
namespace App\Http\Controllers;
use App\Models\SourceRecord;
use App\Services\PublicContent;
use Illuminate\Http\Request;
class PublicCommentsController extends Controller
{
    public function index(Request $request,SourceRecord $record,PublicContent $content)
    {
        abort_unless($content->visible($record),404);
        $request->validate(['page'=>'sometimes|integer|min:1']);
        $comments=$content->childrenForViewer($record,'comment',$request->user(),$request->session()->getId())->latest('id')->paginate(30);
        return response()->json(['html'=>view('public.comment-list',compact('comments'))->render(),'total'=>$comments->total()])->header('Cache-Control','private, no-store');
    }
}
