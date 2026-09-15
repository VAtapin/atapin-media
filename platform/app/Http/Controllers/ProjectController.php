<?php
namespace App\Http\Controllers;
use App\Models\Project;
use App\Services\{Audit,Workflow};
use App\Services\MediaLibrary;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;
class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['q'=>'nullable|string|max:120', 'status'=>['nullable', Rule::in(Project::STATES)], 'type'=>['nullable',Rule::in(Project::TYPES)],'user_id'=>'nullable|integer|exists:users,id','due_before'=>'nullable|date_format:Y-m-d','page'=>'nullable|integer|min:1']);
        $query = Project::with('owner:id,name')->withCount(['tasks', 'tasks as open_tasks_count'=>fn($q)=>$q->where('status','!=','done'), 'records', 'products'])->latest('updated_at');
        if ($data['q'] ?? '') $query->where('title', 'like', '%'.$data['q'].'%');
        if ($data['status'] ?? '') $query->where('status', $data['status']);
        foreach (['type','user_id'] as $key) if ($data[$key] ?? null) $query->where($key,$data[$key]);
        if ($data['due_before'] ?? null) $query->whereDate('due_date','<=',$data['due_before']);
        $page = $query->paginate(30);
        $covers = \App\Models\Media::whereIn('id', $page->getCollection()->pluck('cover_media_id')->filter())->get()->keyBy('id');
        return response()->json($page->through(fn (Project $project) => [...$project->toArray(), 'cover_url' => $covers->get($project->cover_media_id)?->previewUrl()]));
    }

    public function show(Project $project)
    {
        $project->load('owner:id,name')->loadCount(['tasks','tasks as done_tasks_count'=>fn($q)=>$q->where('status','done')]);
        $cover = $project->cover_media_id ? \App\Models\Media::find($project->cover_media_id) : null;
        return response()->json(['project'=>[...$project->toArray(),'cover_url'=>$cover?->previewUrl()],
            'tasks'=>$project->tasks()->with('assignee:id,name')->orderBy('due_date')->paginate(30, ['*'], 'tasks_page'),
            'records'=>$project->records()->latest()->paginate(30, ['id','title','kind','status'], 'records_page'),
            'products'=>$project->products()->latest()->paginate(30, ['id','title','status'], 'products_page'),
            'timeline'=>\App\Models\AuditEvent::where(function($query)use($project){
                $query->where(fn($q)=>$q->where('action','project.saved')->where('subject',(string)$project->id))
                    ->orWhere(fn($q)=>$q->where('action','task.saved')->where(fn($q)=>$q->whereIn('subject',$project->tasks()->select('id'))->orWhere('context->project_id',$project->id)->orWhere('context->previous_project_id',$project->id)));
            })->latest('id')->paginate(20,['id','action','context','created_at'],'timeline_page')->through(fn($event)=>[
                'id'=>$event->id,'action'=>$event->action,'created_at'=>$event->created_at,
                'previous_status'=>$event->context['previous_status']??null,'status'=>$event->context['status']??null,
            ])]);
    }

    public function store(Request $request, Workflow $workflow)
    {
        $data=$this->data($request);$data['user_id']??=$request->user()->id;
        $project = $workflow->saveProject($data);
        if ($request->expectsJson()) return response()->json(['status'=>'saved','project_id'=>$project->id]);
        return back()->with('status', __('ui.saved'));
    }
    public function update(Request $request, Project $project, Workflow $workflow)
    {
        $workflow->saveProject($this->data($request), $project);
        if ($request->expectsJson()) return response()->json(['status'=>'saved','project_id'=>$project->id]);
        return back()->with('status', __('ui.saved'));
    }

    public function destroy(Request $request, Project $project, Audit $audit)
    {
        $request->validate(['confirmation'=>'required|in:DELETE']);
        $id = (string) $project->id;
        $project->delete();
        $audit->record('project.deleted', $id);
        return response()->json(['status'=>'deleted']);
    }

    public function cover(Request $request, Project $project, MediaLibrary $library)
    {
        Gate::authorize('media.upload');
        $request->validate(['file'=>'required|image|mimes:jpg,jpeg,png,webp,gif|max:10240']);
        $media = $library->upload($request->file('file'), $request->user()->id);
        $project->update(['cover_media_id'=>$media->id]);
        return response()->json(['status'=>'saved','media_id'=>$media->id,'cover_url'=>$media->previewUrl()]);
    }
    private function data(Request $request): array
    {
        return $request->validate(['title'=>'required|string|max:255','description'=>'nullable|string|max:20000',
            'status'=>['required',Rule::in(Project::STATES)],'due_date'=>'nullable|date_format:Y-m-d',
            'type'=>['sometimes','required',Rule::in(Project::TYPES)],'user_id'=>'nullable|integer|exists:users,id',
            'start_date'=>'nullable|date_format:Y-m-d','next_action'=>'nullable|string|max:255',
            'tags'=>'nullable|array|max:50','tags.*'=>'required|string|max:100',
            'team_ids'=>'nullable|array|max:50','team_ids.*'=>'integer|distinct|exists:users,id',
            'cover_media_id'=>['nullable','uuid',Rule::exists('media','id')->whereNull('archived_at')->where('kind','image')]]);
    }
}
