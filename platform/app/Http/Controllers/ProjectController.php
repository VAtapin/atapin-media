<?php
namespace App\Http\Controllers;
use App\Models\Project;
use App\Services\Workflow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
        return response()->json($query->paginate(30));
    }

    public function show(Project $project)
    {
        return response()->json(['project'=>$project->load('owner:id,name')->loadCount(['tasks','tasks as done_tasks_count'=>fn($q)=>$q->where('status','done')]),
            'tasks'=>$project->tasks()->with('assignee:id,name')->orderBy('due_date')->paginate(30, ['*'], 'tasks_page'),
            'records'=>$project->records()->latest()->paginate(30, ['id','title','kind','status'], 'records_page'),
            'products'=>$project->products()->latest()->paginate(30, ['id','title','status'], 'products_page'),
            'timeline'=>\App\Models\AuditEvent::where(function($query)use($project){
                $query->where(fn($q)=>$q->where('action','project.saved')->where('subject',(string)$project->id))
                    ->orWhere(fn($q)=>$q->where('action','task.saved')->whereIn('subject',$project->tasks()->select('id')));
            })->latest('id')->paginate(20,['id','action','created_at'],'timeline_page')]);
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
