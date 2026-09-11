@extends('layouts.app')
@section('title',__('ui.calendar'))
@section('content')
<div class="page-heading"><div><p class="eyebrow">{{ __('ui.workspace') }}</p><h1>{{ __('ui.calendar') }}</h1></div><form class="filters"><label><span class="sr-only">{{ __('ui.month') }}</span><input type="month" name="month" value="{{ $month->format('Y-m') }}"></label><button class="button secondary">{{ __('ui.show') }}</button></form></div>
<nav class="tabs">@foreach(['month','week','list'] as $tab)<a @class(['selected'=>$view===$tab]) href="{{ route('calendar',['view'=>$tab,'month'=>$month->format('Y-m')]) }}">{{ __('ui.calendar_'.$tab) }}</a>@endforeach</nav>
<div class="panel-heading"><a href="{{ route('calendar',['view'=>$view,'month'=>$month->subMonth()->format('Y-m'),'date'=>$start->subWeek()->toDateString()]) }}">← {{ __('ui.previous') }}</a><h2>{{ $view==='week'?$start->format('d.m.').' – '.$end->format('d.m.Y'):$month->locale(app()->getLocale())->translatedFormat('F Y') }}</h2><a href="{{ route('calendar',['view'=>$view,'month'=>$month->addMonth()->format('Y-m'),'date'=>$start->addWeek()->toDateString()]) }}">{{ __('ui.next') }} →</a></div>
<div @class(['calendar-grid'=>$view!=='list','calendar-list'=>$view==='list'])>
@for($day=$start;$day<=$end;$day=$day->addDay())
@php($dayTasks=$tasks->filter(fn($task)=>$task->due_date->isSameDay($day)))
@php($dayProjects=$projects->filter(fn($project)=>$project->due_date->isSameDay($day)))
@if($view!=='list'||$dayTasks->isNotEmpty()||$dayProjects->isNotEmpty())<section @class(['calendar-day','today'=>$day->isToday(),'outside'=>$day->month!==$month->month])><h3>{{ $day->locale(app()->getLocale())->translatedFormat('D, d.m.') }}</h3>@foreach($dayProjects as $project)<a class="calendar-event project-event" href="{{ route('projects.show',$project) }}">{{ $project->title }}</a>@endforeach @foreach($dayTasks as $task)<a class="calendar-event" href="{{ route('tasks.index',['view'=>'list','status'=>$task->status]) }}">{{ $task->title }}</a>@endforeach</section>@endif
@endfor</div>
@if($view==='list'&&$tasks->isEmpty()&&$projects->isEmpty())<section class="panel empty">{{ __('ui.no_dates') }}</section>@endif
@endsection
