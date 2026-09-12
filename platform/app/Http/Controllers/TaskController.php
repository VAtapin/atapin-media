<?php
namespace App\Http\Controllers;
use App\Models\Task;
use App\Services\Workflow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class TaskController extends Controller
{
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
            'due_date'=>'nullable|date_format:Y-m-d']);
    }
}