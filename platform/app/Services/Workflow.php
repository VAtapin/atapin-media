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
            $task ??= new Task;
            $projectId=array_key_exists('project_id',$data)?$data['project_id']:$task->project_id;
            $recordId=array_key_exists('source_record_id',$data)?$data['source_record_id']:$task->source_record_id;
            if ($recordId) {
                $record=\App\Models\SourceRecord::findOrFail($recordId);
                abort_if($projectId && $record->project_id && (int)$record->project_id!==(int)$projectId,422,__('workspaces.task_project_conflict'));
            }
            $task->fill($data); $task->save(); $task->project?->touch();
            app(Audit::class)->record('task.saved',(string)$task->id,['status'=>$task->status]); return $task;
        });
    }
}
