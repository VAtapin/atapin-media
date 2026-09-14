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
            $project = $project ? Project::lockForUpdate()->findOrFail($project->id) : new Project;
            $previous = $project->status;
            $project->fill($data); $project->save();
            app(Audit::class)->record('project.saved',(string)$project->id,['previous_status'=>$previous,'status'=>$project->status]); return $project;
        });
    }
    public function saveTask(array $data, ?Task $task=null): Task
    {
        return DB::transaction(function() use($data,$task) {
            $task = $task ? Task::lockForUpdate()->findOrFail($task->id) : new Task;
            $previous = $task->status;
            $previousProject = $task->project_id;
            $projectId=array_key_exists('project_id',$data)?$data['project_id']:$task->project_id;
            $recordId=array_key_exists('source_record_id',$data)?$data['source_record_id']:$task->source_record_id;
            if ($recordId) {
                $record=\App\Models\SourceRecord::findOrFail($recordId);
                abort_if($projectId && $record->project_id && (int)$record->project_id!==(int)$projectId,422,__('workspaces.task_project_conflict'));
            }
            $date = array_key_exists('due_date',$data) ? $data['due_date'] : $task->due_date;
            abort_if(!empty($data['due_time']) && !$date,422,__('workspaces.deadline_date_required'));
            if (!$date) $data['due_time'] = null;
            $task->fill($data); $task->save(); $task->project?->touch();
            app(Audit::class)->record('task.saved',(string)$task->id,['status'=>$task->status,'previous_status'=>$previous,'project_id'=>$task->project_id,'previous_project_id'=>$previousProject]); return $task;
        });
    }
}
