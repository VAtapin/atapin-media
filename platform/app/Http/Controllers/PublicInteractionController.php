<?php
namespace App\Http\Controllers;
use App\Models\{SourceRecord,Product};
use App\Services\{PublicContent,PublicBooks,PublicParticipation};
use Illuminate\Http\Request;

class PublicInteractionController extends Controller
{
    public function record(Request $request,SourceRecord $record,PublicContent $content,PublicParticipation $participation)
    {
        \Illuminate\Support\Facades\Gate::authorize('public.participate');
        abort_unless($content->visible($record),404);
        $data=$this->data($request);
        if(in_array($data['action'],['comment','chat'])){
            abort_unless(in_array($record->kind,['video','short','post']),422);
            if($data['action']==='chat')abort_unless($content->section($record)==='live',422);
            $request->validate(['body'=>'required|string|min:2|max:5000']);$participation->comment($request->user(),$record,$data['body'],$data['action']==='chat'?'live_chat':'comment');
            return $this->result($request,__('public.comment_pending'));
        }
        $participation->save($request->user(),$record,$data);return $this->result($request,__('public.saved'));
    }
    public function book(Request $request,Product $product,PublicBooks $books,PublicParticipation $participation)
    {
        abort_unless($books->query()->whereKey($product->id)->exists(),404);
        $participation->save($request->user(),$product,$this->data($request));return $this->result($request,__('public.saved'));
    }
    private function data(Request $request): array
    {
        return $request->validate(['action'=>'required|in:bookmark,like,reminder,progress,vote,comment,chat','enabled'=>'sometimes|boolean',
            'option'=>'required_if:action,vote|integer|min:0|max:100','position'=>'required_if:action,progress|integer|min:0|max:10000000','body'=>'nullable|string|max:5000']);
    }
    private function result(Request $request,string $message)
    {
        return $request->expectsJson()?response()->json(['message'=>$message]):back()->with('public_status',$message);
    }
}
