<?php

namespace App\Http\Controllers;

use App\Models\DesktopAiRequest;
use App\Services\{ContentShortDescriptions, DesktopAi, Settings};
use Illuminate\Http\Request;

class DesktopAiController extends Controller
{
    public function show(Request $request, DesktopAiRequest $entry)
    {
        abort_unless($entry->user_id===$request->user()->id,404);
        $entry->load('record:id,title');$entry->setAttribute('proposal_version',$entry->proposalVersion());
        return response()->json($entry)->header('Cache-Control','no-store');
    }
    public function index(Request $request)
    {
        $request->validate(['page'=>'nullable|integer|min:1']);
        return response()->json(['requests'=>DesktopAiRequest::with('record:id,title')->where('user_id',$request->user()->id)->latest()->paginate(20)->through(function($entry){$entry->setAttribute('proposal_version',$entry->proposalVersion());return $entry;}),
            'available'=>app(ContentShortDescriptions::class)->available(),'model'=>app(Settings::class)->get('ai_model'),'daily_limit'=>50]);
    }
    public function store(Request $request, DesktopAi $service)
    {
        $data = $request->validate(['question'=>'required|string|min:2|max:4000','purpose'=>'required|in:chat,title,summary,seo,social,reply,hashtags,youtube_description,bible_references,shorten,lengthen,tone,transform,ideas',
            'source_record_id'=>'nullable|integer|exists:source_records,id','product_id'=>'nullable|integer|exists:products,id','context'=>'nullable|array:provider','context.provider'=>'nullable|in:youtube,facebook,instagram,telegram,x']);
        abort_if(!empty($data['product_id'])&&!empty($data['source_record_id']),422);
        if(!empty($data['product_id']))\Illuminate\Support\Facades\Gate::authorize('shop.manage');
        if (in_array($data['purpose'],['title','summary','seo'],true)) abort_unless(!empty($data['source_record_id'])||!empty($data['product_id']),422);
        $entry = $service->submit($request->user(),$data);
        return response()->json(['id'=>$entry->id,'status'=>'queued'],202);
    }
    public function apply(Request $request, DesktopAiRequest $entry, DesktopAi $service)
    {
        abort_unless($entry->user_id === $request->user()->id,404);
        $data=$request->validate(['proposal_version'=>'nullable|string|size:64']);
        $service->apply($entry,$data['proposal_version']??null);
        return response()->json(['status'=>'applied']);
    }
    public function update(Request $request, DesktopAiRequest $entry)
    {
        abort_unless($entry->user_id === $request->user()->id,404);
        $data=$request->validate(['proposal_version'=>'required|string|size:64',
            'proposal'=>'required|array:answer,title,short_description,seo_title,seo_description,social_text',
            'proposal.answer'=>'required|string|max:20000','proposal.title'=>'present|nullable|string|max:255',
            'proposal.short_description'=>'present|nullable|string|max:300','proposal.seo_title'=>'present|nullable|string|max:255',
            'proposal.seo_description'=>'present|nullable|string|max:500','proposal.social_text'=>'present|nullable|string|max:10000']);
        $version=\Illuminate\Support\Facades\DB::transaction(function()use($entry,$data){
            $entry=DesktopAiRequest::lockForUpdate()->findOrFail($entry->id);
            abort_unless($entry->status==='completed'&&hash_equals($entry->proposalVersion(),$data['proposal_version']),409,__('workspaces.ai_proposal_stale'));
            $proposal=array_map(fn($value)=>$value??'', $data['proposal']);
            $entry->update(['proposal'=>$proposal,'answer'=>$proposal['answer']]);
            app(\App\Services\Audit::class)->record('ai.suggestion_edited',(string)$entry->id);
            return $entry->proposalVersion();
        });
        return response()->json(['status'=>'saved','proposal_version'=>$version]);
    }
}
