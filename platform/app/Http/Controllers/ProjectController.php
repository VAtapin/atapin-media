<?php
namespace App\Http\Controllers;
use App\Models\Project;
use App\Services\Workflow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class ProjectController extends Controller
{
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