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
        $data = $request->validate(['q'=>'nullable|string|max:120', 'status'=>['nullable', Rule::in(Project::STATES)], 'page'=>'nullable|integer|min:1']);
        $query = Project::with('owner:id,name')->withCount(['tasks', 'tasks as open_tasks_count'=>fn($q)=>$q->where('status','!=','done'), 'records', 'products'])->latest('updated_at');
        if ($data['q'] ?? '') $query->where('title', 'like', '%'.$data['q'].'%');
        if ($data['status'] ?? '') $query->where('status', $data['status']);
        return response()->json($query->paginate(30));
    }

    public function show(Project $project)
    {
        return response()->json(['project'=>$project->load('owner:id,name'),
            'tasks'=>$project->tasks()->with('assignee:id,name')->orderBy('due_date')->paginate(30, ['*'], 'tasks_page'),
            'records'=>$project->records()->latest()->paginate(30, ['id','title','kind','status'], 'records_page'),
            'products'=>$project->products()->latest()->paginate(30, ['id','title','status'], 'products_page')]);
    }

    public function store(Request $request, Workflow $workflow)
    {
        $project = $workflow->saveProject($this->data($request) + ['user_id'=>$request->user()->id]);
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
            'status'=>['required',Rule::in(Project::STATES)],'due_date'=>'nullable|date_format:Y-m-d']);
    }
}
