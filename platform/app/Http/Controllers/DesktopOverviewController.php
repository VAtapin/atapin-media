<?php
namespace App\Http\Controllers;
use App\Models\{Project,Task,SourceRecord,Media,PublicationSchedule,NewsletterSubscription};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Gate};
class DesktopOverviewController extends Controller
{
    public function __invoke(Request $request)
    {
        $widgets=[];
        if(Gate::allows('projects.manage')){
            $widgets['projects']=['app'=>'projects','items'=>Project::withCount(['tasks','tasks as done_tasks_count'=>fn($q)=>$q->where('status','done')])->where('status','!=','published')->latest('updated_at')->limit(6)->get()];
            $widgets['tasks']=['app'=>'tasks','items'=>Task::where('assigned_to',$request->user()->id)->where('status','!=','done')->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')->orderBy('due_date')->limit(6)->get()];
        }
        if(Gate::allows('media.view')){
            $widgets['media']=['app'=>'media','count'=>Media::whereNull('archived_at')->count(),'bytes'=>Media::whereNull('archived_at')->sum('bytes'),'items'=>Media::whereNull('archived_at')->latest()->limit(6)->get(['id','title','kind','status','created_at'])];
        }
        if(Gate::allows('content.edit')){
            foreach(['videos'=>['video','short'],'posts'=>['post']] as $app=>$kinds)$widgets[$app]=['app'=>$app,'items'=>SourceRecord::whereIn('kind',$kinds)->where('source','!=','catalog-reset')->latest('updated_at')->limit(6)->get(['id','title','kind','status','updated_at'])];
        }
        if(Gate::allows('content.publish')){
            $widgets['calendar']=['app'=>'calendar','items'=>PublicationSchedule::with('record:id,title')->whereIn('status',['scheduled','queued'])->orderBy('publish_at')->limit(6)->get()->map(fn($schedule)=>['id'=>$schedule->id,'title'=>$schedule->record?->title,'due_date'=>$schedule->publish_at->format('Y-m-d H:i'),'status'=>$schedule->status])];
            $widgets['live-studio']=['app'=>'live-studio','items'=>SourceRecord::where('metadata->public_section','live')->whereIn('metadata->live_status',['scheduled','live'])->latest('updated_at')->limit(6)->get(['id','title','status'])];
        }
        if(Gate::allows('community.moderate'))$widgets['community']=['app'=>'community','count'=>SourceRecord::whereIn('kind',['comment','live_chat'])->whereNull('metadata->inbox_read_at')->where('source','!=','catalog-reset')->count()];
        if(Gate::allows('subscribers.manage'))$widgets['newsletter']=['app'=>'newsletter','count'=>NewsletterSubscription::where('status','active')->whereNotNull('confirmed_at')->whereNotNull('consented_at')->count()];
        if(Gate::allows('settings.manage'))$widgets['settings']=['app'=>'settings','count'=>DB::table('failed_jobs')->count(),'queued'=>DB::table('jobs')->count()];
        return response()->json(['widgets'=>$widgets])->header('Cache-Control','no-store');
    }
}
