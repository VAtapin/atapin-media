<?php
namespace App\Http\Controllers;
use App\Models\Task;
use App\Services\Workflow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class TaskController extends Controller
{
    public function show(Task $task){return response()->json($task->load('project:id,title','assignee:id,name','record:id,title,kind'));}
    public function index(Request $request)
    {
        $data = $request->validate(['q'=>'nullable|string|max:120', 'status'=>['nullable', Rule::in(Task::STATES)],
            'project_id'=>'nullable|integer|exists:projects,id','assigned_to'=>'nullable|integer|exists:users,id','priority'=>['nullable',Rule::in(Task::PRIORITIES)],'content_type'=>['nullable',Rule::in(['video','short','post','poll'])],'due_before'=>'nullable|date_format:Y-m-d','due_after'=>'nullable|date_format:Y-m-d', 'mine'=>'nullable|boolean', 'page'=>'nullable|integer|min:1']);
        $query = Task::with(['project:id,title', 'assignee:id,name'])->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')->orderBy('due_date')->latest('id');
        if ($data['q'] ?? '') $query->where('title', 'like', '%'.$data['q'].'%');
        if ($data['status'] ?? '') $query->where('status', $data['status']);
        if ($data['project_id'] ?? null) $query->where('project_id', $data['project_id']);
        if ($data['mine'] ?? false) $query->where('assigned_to', $request->user()->id);
        foreach (['assigned_to','priority'] as $key) if ($data[$key] ?? null) $query->where($key,$data[$key]);
        if ($data['content_type'] ?? null) $query->whereHas('record',fn($q)=>$q->where('kind',$data['content_type']));
        if ($data['due_before'] ?? null) $query->whereDate('due_date','<=',$data['due_before']);
        if ($data['due_after'] ?? null) $query->whereDate('due_date','>=',$data['due_after']);
        return response()->json($query->paginate(50));
    }

    public function store(Request $request, Workflow $workflow)
    {
        $task = $workflow->saveTask($this->data($request));
        if ($request->expectsJson()) return response()->json(['status'=>'saved','task_id'=>$task->id]);
        return back()->with('status', __('ui.saved'));
    }
    public function update(Request $request, Task $task, Workflow $workflow)
    {
        $workflow->saveTask($this->data($request, true), $task);
        if ($request->expectsJson()) return response()->json(['status'=>'saved','task_id'=>$task->id]);
        return back()->with('status', __('ui.saved'));
    }
    private function data(Request $request, bool $partial=false): array
    {
        return $request->validate(['title'=>($partial?'sometimes|':'').'required|string|max:255',
            'description'=>'nullable|string|max:20000','status'=>[$partial?'sometimes':'required',Rule::in(Task::STATES)],
            'project_id'=>'nullable|integer|exists:projects,id','assigned_to'=>'nullable|integer|exists:users,id',
            'due_date'=>'nullable|date_format:Y-m-d','priority'=>['sometimes','required',Rule::in(Task::PRIORITIES)],
            'source_record_id'=>'nullable|integer|exists:source_records,id',
            'tags'=>'nullable|array|max:50','tags.*'=>'required|string|max:100',
            'checklist'=>'nullable|array|max:100','checklist.*.text'=>'required|string|max:255','checklist.*.done'=>'required|boolean']);
    }
}
