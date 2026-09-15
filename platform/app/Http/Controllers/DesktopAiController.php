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
        $data=$request->validate(['page'=>'nullable|integer|min:1','q'=>'nullable|string|max:255','status'=>'nullable|in:queued,processing,completed,failed,applied','purpose'=>'nullable|string|max:24']);
        $query=DesktopAiRequest::with('record:id,title')->where('user_id',$request->user()->id);
        if(!empty($data['q']))$query->where(function($q)use($data){$q->where('question','like','%'.$data['q'].'%')->orWhereHas('record',fn($record)=>$record->where('title','like','%'.$data['q'].'%'));});
        if(!empty($data['status']))$query->where('status',$data['status']);
        if(!empty($data['purpose']))$query->where('purpose',$data['purpose']);
        $base=DesktopAiRequest::where('user_id',$request->user()->id);$today=(clone $base)->whereDate('created_at',now()->toDateString());
        $stats=['total'=>(clone $base)->count(),'today'=>$today->count(),'completed'=>(clone $base)->where('status','completed')->count(),'failed'=>(clone $base)->where('status','failed')->count(),'active'=>(clone $base)->whereIn('status',['queued','processing'])->count(),'tokens'=>(int)((clone $base)->sum('total_tokens')),'cost_micros'=>(clone $base)->sum('estimated_cost_micros'),'cost_known'=>(clone $base)->whereNotNull('estimated_cost_micros')->exists()];
        return response()->json(['requests'=>$query->latest()->paginate(20)->through(function($entry){$entry->setAttribute('proposal_version',$entry->proposalVersion());return $entry;}),'stats'=>$stats,'knowledge'=>['entries'=>count(app(\App\Services\AdminKnowledgeBase::class)->entries())],'available'=>app(ContentShortDescriptions::class)->available(),'model'=>app(Settings::class)->get('ai_model')]);
    }
    public function store(Request $request, DesktopAi $service)
    {
        $data = $request->validate(['question'=>'required|string|min:2|max:4000','purpose'=>'required|in:admin_help,chat,title,summary,seo,social,reply,hashtags,youtube_description,bible_references,shorten,lengthen,tone,transform,ideas,prioritize,structure',
            'source_record_id'=>'nullable|integer|exists:source_records,id','product_id'=>'nullable|integer|exists:products,id','context'=>'nullable|array:provider','context.provider'=>'nullable|in:youtube,facebook,instagram,telegram,x']);
        abort_if(!empty($data['product_id'])&&!empty($data['source_record_id']),422);
        if(in_array($data['purpose'],['prioritize','admin_help'],true))abort_if(!empty($data['product_id'])||!empty($data['source_record_id'])||!empty($data['context']['provider']),422);
        if($data['purpose']==='structure')abort_unless(!empty($data['source_record_id'])&&!empty($data['product_id']),422);
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
            'proposal'=>'required|array:answer,title,short_description,seo_title,seo_description,social_text,structured_body',
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
