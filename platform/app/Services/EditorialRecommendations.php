<?php
namespace App\Services;
use App\Models\{User,SourceRecord,Task,Project};
/** Permission-scoped, bounded facts; recommendations never execute changes. */
class EditorialRecommendations
{
    public function context(User $user):array
    {
        abort_unless($user->hasPermission('content.edit'),403);
        $content=SourceRecord::whereIn('kind',['video','short','post'])->where('source','!=','catalog-reset')->where(fn($q)=>$q->whereNull('metadata->public_published')->orWhere('metadata->public_published',false))->where(fn($q)=>$q->whereNull('metadata->archive_data')->orWhere('metadata->archive_data',false))->where(fn($q)=>$q->whereNull('metadata->library_only')->orWhere('metadata->library_only',false))->orderBy('updated_at')->limit(31)->get(['id','title','kind','status','updated_at','metadata']);
        $data=['observed_at'=>now()->toIso8601String(),'timezone'=>app(Settings::class)->get('system_timezone',config('platform.timezone')),'content'=>$content->take(30)->map(fn($r)=>['id'=>$r->id,'title'=>mb_substr($r->title,0,255),'kind'=>$r->kind,'status'=>$r->status,'workflow_stage'=>$r->metadata['workflow_stage']??null,'updated_at'=>$r->updated_at])->all(),'content_limited'=>$content->count()>30];
        if($user->hasPermission('projects.manage')){
            $tasks=Task::where('status','!=','done')->orderBy('due_date')->limit(31)->get(['id','title','status','priority','due_date','due_time','project_id']);$projects=Project::whereNotIn('status',['published','archived'])->orderBy('due_date')->limit(31)->get(['id','title','status','due_date','next_action']);
            $data['tasks']=$tasks->take(30)->toArray();$data['projects']=$projects->take(30)->toArray();$data['tasks_limited']=$tasks->count()>30;$data['projects_limited']=$projects->count()>30;
        }
        return $data;
    }
}
