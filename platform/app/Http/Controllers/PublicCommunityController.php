<?php
namespace App\Http\Controllers;
use App\Models\SourceRecord;
use App\Services\{Audit,PublicCommunityModeration,PublicCommunitySubmission};
use Illuminate\Http\Request;
class PublicCommunityController extends Controller {
    public function store(Request $request, PublicCommunitySubmission $submission){
        $data=$request->validate(['title'=>'required|string|min:3|max:255','body'=>'required|string|min:5|max:5000','type'=>'required|in:question,discussion']);
        $submission->post($request->user(), $data);
        return back()->with('public_status',__('public.message_sent'));
    }
    public function index(){return redirect('/desktop?open=community',303);}
    public function moderate(Request $request,SourceRecord $record,PublicCommunityModeration $moderation,Audit $audit){
        $data=$request->validate(['decision'=>'required|in:publish,reject']);
        $moderation->decide($record, $data['decision'], (int) $request->user()->id);
        $audit->record('community.moderated',(string)$record->id,['decision'=>$data['decision']]);
        return $request->expectsJson()?response()->json(['status'=>'saved']):redirect('/desktop?open=community',303)->with('public_status',__('public.saved'));
    }
}
