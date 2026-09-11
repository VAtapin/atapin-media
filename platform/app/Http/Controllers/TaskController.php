<?php
namespace App\Http\Controllers;
use App\Models\Task;
use App\Models\Project;
use App\Models\User;
use App\Services\Workflow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class TaskController extends Controller
{
    public function index(Request $r)
    {
        $data=$r->validate(['view'=>'nullable|in:board,list,mine','status'=>['nullable',Rule::in(Task::STATES)]]);
        $view=$data['view']??'board';
        $query=Task::with(['project','assignee'])->when($view==='mine',fn($q)=>$q->where('assigned_to',$r->user()->id))
            ->when($data['status']??null,fn($q,$status)=>$q->where('status',$status))->orderByRaw('due_date IS NULL')->orderBy('due_date')->latest();
        // Each column is bounded separately, with a list link for larger queues.
        $columns=[];
        if ($view==='board') foreach(Task::STATES as $status) $columns[$status]=(clone $query)->where('status',$status)->limit(50)->get();
        return view('tasks.index',['tasks'=>$view==='board'?null:$query->paginate(40)->withQueryString(),
            'columns'=>$columns,'view'=>$view,'projects'=>Project::orderBy('title')->get(['id','title']),
            'users'=>User::orderBy('name')->get(['id','name'])]);
    }
    public function store(Request $r,Workflow $workflow)
    {
        $workflow->saveTask($this->data($r)); return back()->with('status',__('ui.saved'));
    }
    public function update(Request $r,Task $task,Workflow $workflow)
    {
        $workflow->saveTask($this->data($r,true),$task); return back()->with('status',__('ui.saved'));
    }
    private function data(Request $r,bool $partial=false):array
    {
        return $r->validate(['title'=>($partial?'sometimes|':'').'required|string|max:255',
            'description'=>'nullable|string|max:20000','status'=>[$partial?'sometimes':'required',Rule::in(Task::STATES)],
            'project_id'=>'nullable|integer|exists:projects,id','assigned_to'=>'nullable|integer|exists:users,id',
            'due_date'=>'nullable|date_format:Y-m-d']);
    }
}
