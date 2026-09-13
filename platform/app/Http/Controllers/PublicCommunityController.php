<?php
namespace App\Http\Controllers;
use App\Models\SourceRecord;
use App\Services\{Audit,PublicContent,Settings};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class PublicCommunityController extends Controller {
    public function store(Request $request){
        $data=$request->validate(['title'=>'required|string|min:3|max:255','body'=>'required|string|min:5|max:5000','type'=>'required|in:question,discussion']);
        $record=SourceRecord::create(['source'=>'website','source_id'=>'community:'.Str::uuid(),'kind'=>'post','status'=>'needs_attention','title'=>$data['title'],'body'=>$data['body'],'metadata'=>['public_section'=>'community','public_published'=>false,'website_community'=>true,'community_type'=>$data['type'],'author'=>$request->user()->name,'author_user_id'=>$request->user()->id]]);
        app(Audit::class)->record('community.submitted',(string)$record->id);
        return back()->with('public_status',__('public.comment_pending'));
    }
    private function pending(){return SourceRecord::where('status','needs_attention')->where(fn($q)=>$q->where('metadata->website_community',true)->orWhere('metadata->website_comment',true))->where(fn($q)=>$q->whereNull('metadata->moderation_rejected')->orWhere('metadata->moderation_rejected',false));}
    public function index(){return view('public.community-admin',['title'=>__('public.community_moderation'),'section'=>'community','siteName'=>app(Settings::class)->get('site_name',config('platform.brand')),'description'=>'','entries'=>$this->pending()->latest()->paginate(30)]);}
    public function moderate(Request $request,SourceRecord $record,PublicContent $content,Audit $audit){
        abort_unless(($record->metadata['website_community']??false)||($record->metadata['website_comment']??false),404);
        $data=$request->validate(['decision'=>'required|in:publish,reject']);
        if($data['decision']==='publish'&&in_array($record->kind,['comment','live_chat']))abort_unless($content->query()->where('source',$record->source)->where('source_id',$record->metadata['parent_source_id']??'')->whereIn('kind',['post','video','short'])->exists(),422);
        DB::transaction(function()use($record,$data){$fresh=SourceRecord::lockForUpdate()->findOrFail($record->id);$publish=$data['decision']==='publish';$fresh->update(['status'=>$publish?'ready':'needs_attention','metadata'=>[...$fresh->metadata,'public_published'=>$publish,'moderation_rejected'=>!$publish,'public_published_at'=>$publish?now()->toIso8601String():null]]);});
        $audit->record('community.moderated',(string)$record->id);return back()->with('public_status',__('public.saved'));
    }
}
