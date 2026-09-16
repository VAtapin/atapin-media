<?php
namespace App\Http\Controllers;
use App\Models\{SourceRecord,PublicAiChatRequest};
use App\Services\PublicAiChat;
use Illuminate\Http\Request;
class PublicAiChatController extends Controller
{
    public function store(Request $request,SourceRecord $record,PublicAiChat $chat)
    {
        $data=$request->validate(['question'=>'required|string|min:2|max:1000','consent'=>'required|accepted']);
        $entry=$chat->submit($request->user(),$record,preg_replace('/^\s*@Assistent\s*/iu','',$data['question']));
        if($entry->status==='failed')return response()->json(['status'=>'failed','message'=>__('public.ai_chat_error')],503);
        return response()->json(['status'=>'completed','answer'=>$entry->answer])->header('Cache-Control','private, no-store');
    }
    public function show(Request $request,PublicAiChatRequest $entry)
    {
        abort_unless($entry->user_id===$request->user()->id,404);
        return response()->json(['status'=>$entry->status,'answer'=>$entry->status==='completed'?$entry->answer:null])->header('Cache-Control','private, no-store');
    }
}
