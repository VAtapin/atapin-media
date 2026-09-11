<?php
namespace App\Http\Controllers;
use App\Models\Project;
use App\Models\User;
use App\Services\Workflow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class ProjectController extends Controller
{
    public function index(Request $r)
    {
        $data=$r->validate(['q'=>'nullable|string|max:200']);
        $projects=Project::withCount(['tasks','tasks as done_count'=>fn($q)=>$q->where('status','done')])
            ->when($data['q']??null,fn($q,$term)=>$q->where('title','like','%'.$term.'%'))->latest('updated_at')->paginate(20)->withQueryString();
        return view('projects.index',compact('projects'));
    }
    public function create() { return view('projects.form',['project'=>new Project]); }
    public function store(Request $r, Workflow $workflow)
    {
        $project=$workflow->saveProject($this->data($r)+['user_id'=>$r->user()->id]);
        return redirect()->route('projects.show',$project)->with('status',__('ui.saved'));
    }
    public function show(Project $project)
    {
        $project->load(['tasks.assignee','owner']);
        return view('projects.show',['project'=>$project,'users'=>User::orderBy('name')->get(['id','name'])]);
    }
    public function edit(Project $project) { return view('projects.form',compact('project')); }
    public function update(Request $r, Project $project, Workflow $workflow)
    {
        $workflow->saveProject($this->data($r),$project);
        return redirect()->route('projects.show',$project)->with('status',__('ui.saved'));
    }
    private function data(Request $r): array
    {
        return $r->validate(['title'=>'required|string|max:255','description'=>'nullable|string|max:20000',
            'status'=>['required',Rule::in(Project::STATES)],'due_date'=>'nullable|date_format:Y-m-d']);
    }
}
