<?php
namespace App\Http\Controllers;
use App\Models\SourceRecord;
use App\Services\{Audit,PublicParticipation};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Gate};
class PollWorkspaceController extends Controller
{
    public function index(Request $request)
    {
        $data=$request->validate(['q'=>'nullable|string|max:120','page'=>'nullable|integer|min:1']);
        return response()->json(SourceRecord::where('kind','poll')->when($data['q']??null,fn($q,$value)=>$q->where('title','like','%'.$value.'%'))->latest()->paginate(30));
    }
    public function show(SourceRecord $record)
    {
        abort_unless($record->kind==='poll',404);
        $participation=app(PublicParticipation::class);
        return response()->json(['poll'=>$record,'votes'=>$participation->states($record)->where('action','vote')->count()]);
    }
    public function store(Request $request) { return $this->save($request); }
    public function update(Request $request,SourceRecord $record) { abort_unless($record->kind==='poll',404);return $this->save($request,$record); }
    private function save(Request $request,?SourceRecord $record=null)
    {
        $data=$request->validate(['title'=>'required|string|max:255','body'=>'nullable|string|max:10000','options'=>'required|array|min:2|max:20','options.*'=>'required|string|max:255|distinct',
            'starts_at'=>'nullable|date','ends_at'=>'nullable|date|after:starts_at','active'=>'required|boolean','multiple'=>'required|boolean','audience'=>'required|in:registered,subscriber','results'=>'required|in:always,after_vote,after_close,hidden',
            'placements'=>'required|array|max:3','placements.*'=>'required|in:community,live,buecher|distinct','public_published'=>'required|boolean']);
        if($data['public_published']||($record?->metadata['public_published']??false))Gate::authorize('content.publish');
        $record=DB::transaction(function()use($record,$data){
            $record=$record?SourceRecord::lockForUpdate()->findOrFail($record->id):new SourceRecord(['source'=>'manual','source_id'=>(string)\Illuminate\Support\Str::uuid(),'kind'=>'poll']);
            $old=array_column($record->metadata['poll']['options']??[],'text');
            abort_if($record->exists && $old!==$data['options'] && app(PublicParticipation::class)->states($record)->where('action','vote')->exists(),422,__('workspaces.poll_options_locked'));
            $poll=collect($data)->except(['title','body','public_published'])->all();
            $poll['options']=array_map(fn($text)=>['text'=>$text],$data['options']);
            $record->fill(['title'=>$data['title'],'body'=>$data['body']??'','status'=>'ready','metadata'=>[...($record->metadata??[]),'public_section'=>'community','public_published'=>$data['public_published'],'poll'=>$poll]])->save();
            app(Audit::class)->record('poll.saved',(string)$record->id);return $record;
        });
        return response()->json(['id'=>$record->id,'status'=>'saved']);
    }
}
