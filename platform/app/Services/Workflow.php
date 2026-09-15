<?php
namespace App\Services;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Carbon;
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
            $recurrence = array_key_exists('recurrence',$data) ? $data['recurrence'] : ($task->recurrence ?: 'once');
            $recurrenceInterval = array_key_exists('recurrence_interval',$data) ? $data['recurrence_interval'] : ($task->recurrence_interval ?: 1);
            $recurrenceUnit = array_key_exists('recurrence_unit',$data) ? $data['recurrence_unit'] : $task->recurrence_unit;
            $recurrenceUntil = array_key_exists('recurrence_until',$data) ? $data['recurrence_until'] : $task->recurrence_until;
            abort_if($recurrence !== 'once' && !$date,422,__('ui.task_recurrence_requires_date'));
            abort_if($recurrence === 'custom' && (!$recurrenceInterval || $recurrenceInterval < 1),422,__('ui.task_recurrence_custom_interval'));
            abort_if($recurrence === 'custom' && !in_array($recurrenceUnit,Task::RECURRENCE_UNITS,true),422,__('ui.task_recurrence_custom_unit'));
            abort_if($recurrence !== 'once' && $date && $recurrenceUntil && Carbon::parse($recurrenceUntil)->lt(Carbon::parse($date)),422,__('ui.task_recurrence_until_invalid'));
            if ($recurrence === 'once') {
                $data['recurrence_interval'] = 1;
                $data['recurrence_unit'] = null;
                $data['recurrence_until'] = null;
            } elseif ($recurrence !== 'custom') {
                $data['recurrence_interval'] = 1;
                $data['recurrence_unit'] = null;
            } else {
                $data['recurrence_interval'] = $recurrenceInterval;
            }
            $task->fill($data); $task->save(); $task->project?->touch();
            app(Audit::class)->record('task.saved',(string)$task->id,['status'=>$task->status,'previous_status'=>$previous,'project_id'=>$task->project_id,'previous_project_id'=>$previousProject]); return $task;
        });
    }
}
