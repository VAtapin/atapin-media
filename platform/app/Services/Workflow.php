<?php
namespace App\Services;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
class Workflow
{
    public function saveProject(array $data, ?Project $project=null): Project
    {
        return DB::transaction(function() use($data,$project) {
            $project ??= new Project; $project->fill($data); $project->save();
            app(Audit::class)->record('project.saved',(string)$project->id); return $project;
        });
    }
    public function saveTask(array $data, ?Task $task=null): Task
    {
        return DB::transaction(function() use($data,$task) {
            $task ??= new Task; $task->fill($data); $task->save(); $task->project?->touch();
            app(Audit::class)->record('task.saved',(string)$task->id,['status'=>$task->status]); return $task;
        });
    }
}
