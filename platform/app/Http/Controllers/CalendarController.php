<?php

namespace App\Http\Controllers;

use App\Models\{Project, Task, PublicationSchedule, SourceRecord};
use App\Services\EditorialPlanning;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Gate::allows('projects.manage')||Gate::allows('content.publish'),403);
        $data = $request->validate(['start'=>'required|date_format:Y-m-d','end'=>'required|date_format:Y-m-d|after_or_equal:start',
            'project_id'=>'nullable|integer|exists:projects,id','type'=>'nullable|in:project,task,publication,live','provider'=>'nullable|string|max:32']);
        $timezone=app(\App\Services\Settings::class)->get('system_timezone',config('platform.timezone'));
        $start = Carbon::parse($data['start'],$timezone)->startOfDay(); $end = Carbon::parse($data['end'],$timezone)->endOfDay();
        abort_if($start->diffInDays($end) > 100,422);
        $project = $data['project_id'] ?? null; $events = collect();
        if (Gate::allows('projects.manage')) {
            $projects = Project::whereBetween('due_date',[$data['start'],$data['end']])->when($project,fn($q)=>$q->whereKey($project))->limit(500)->get();
            $tasks = Task::with('project:id,title')->whereBetween('due_date',[$data['start'],$data['end']])->when($project,fn($q)=>$q->where('project_id',$project))->limit(1000)->get();
            foreach ($projects as $row) $events->push(['id'=>'project:'.$row->id,'title'=>$row->title,'date'=>$row->due_date->toDateString(),'type'=>'project','status'=>$row->status,'app'=>'projects','subject_id'=>$row->id]);
            foreach ($tasks as $row) $events->push(['id'=>'task:'.$row->id,'title'=>$row->title,'date'=>$row->due_date->toDateString(),'time'=>$row->due_time,'type'=>'task','status'=>$row->status,'app'=>'tasks','subject_id'=>$row->id]);
        }
        if (Gate::allows('content.publish')) {
            $rows = PublicationSchedule::with('record:id,title,project_id')->whereBetween('publish_at',[$start->copy()->utc(),$end->copy()->utc()])->when($project,fn($q)=>$q->whereHas('record',fn($q)=>$q->where('project_id',$project)))->limit(1000)->get();
            foreach ($rows as $row) if ($row->record) { $local=Carbon::parse($row->getRawOriginal('publish_at'),'UTC')->timezone($timezone);$events->push(['id'=>'schedule:'.$row->id,'title'=>$row->record->title,'date'=>$local->toDateString(),'time'=>$local->format('H:i'),'type'=>'publication','status'=>$row->status,'providers'=>$row->providers,'app'=>'publishing','subject_id'=>$row->source_record_id,'schedule_id'=>$row->id]); }
            $live = SourceRecord::where('metadata->public_section','live')->when($project,fn($q)=>$q->where('project_id',$project))->whereNotNull('metadata->starts_at')->limit(1000)->get();
            foreach ($live as $row) {
                try { $time = Carbon::parse($row->metadata['starts_at'],$timezone)->timezone($timezone); } catch (\Throwable) { continue; }
                if ($time->between($start,$end)) $events->push(['id'=>'live:'.$row->id,'title'=>$row->title,'date'=>$time->toDateString(),'time'=>$time->format('H:i'),'type'=>'live','status'=>$row->metadata['live_status']??'scheduled','app'=>'live-studio','subject_id'=>$row->id]);
            }
        }
        if ($data['type'] ?? '') $events = $events->where('type',$data['type']);
        if ($data['provider'] ?? '') $events = $events->filter(fn($event)=>in_array($data['provider'],$event['providers']??[],true));
        return response()->json(['data'=>$events->sortBy(fn($row)=>$row['date'].' '.($row['time']??'00:00'))->values(),'timezone'=>$timezone,'limit'=>1000,'limited'=>($projects??collect())->count()>=500||($tasks??collect())->count()>=1000||($rows??collect())->count()>=1000||($live??collect())->count()>=1000]);
    }

    public function store(Request $request, EditorialPlanning $planning)
    {
        $data = $request->validate(['record_id'=>'required|integer|exists:source_records,id','publish_at'=>'required|date|after:now',
            'providers'=>'required|array|min:1|max:6','providers.*'=>'required|string|max:32']);
        $schedule = $planning->schedule(SourceRecord::findOrFail($data['record_id']),$request->user(),$data['providers'],$data['publish_at']);
        return response()->json(['status'=>'saved','id'=>$schedule->id]);
    }

    public function cancel(PublicationSchedule $schedule)
    {
        abort_unless(PublicationSchedule::whereKey($schedule->id)->whereIn('status',['scheduled','queued'])->update(['status'=>'cancelled']),409);
        app(\App\Services\Audit::class)->record('publication.schedule_cancelled',(string)$schedule->id);
        return response()->json(['status'=>'cancelled']);
    }
}
