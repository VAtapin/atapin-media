<?php
namespace App\Http\Controllers;
use App\Models\Project;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
class CalendarController extends Controller
{
    public function __invoke(Request $r)
    {
        $data=$r->validate(['month'=>'nullable|date_format:Y-m','view'=>'nullable|in:month,week,list','date'=>'nullable|date_format:Y-m-d']);
        $view=$data['view']??'month';
        $month=CarbonImmutable::createFromFormat('!Y-m',$data['month']??now()->format('Y-m'));
        $start=$view==='week'?CarbonImmutable::parse($data['date']??now()->toDateString())->startOfWeek():$month->startOfMonth()->startOfWeek();
        $end=$view==='week'?$start->endOfWeek():$month->endOfMonth()->endOfWeek();
        $tasks=Task::with('project')->whereBetween('due_date',[$start->toDateString(),$end->toDateString()])->orderBy('due_date')->get();
        $projects=Project::whereBetween('due_date',[$start->toDateString(),$end->toDateString()])->orderBy('due_date')->get();
        return view('calendar',compact('month','start','end','tasks','projects','view'));
    }
}
