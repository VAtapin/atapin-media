@extends('layouts.app')
@section('title',__('ui.tasks'))
@section('content')
<div class="page-heading"><div><p class="eyebrow">{{ __('ui.workspace') }}</p><h1>{{ __('ui.tasks') }}</h1></div><a class="button" href="#new-task">+ {{ __('ui.new_task') }}</a></div>
<nav class="tabs">@foreach(['board','list','mine'] as $tab)<a @class(['selected'=>$view===$tab]) href="{{ route('tasks.index',['view'=>$tab]) }}">{{ __('ui.view_'.$tab) }}</a>@endforeach</nav>
@if($view==='board')<div class="task-board">@foreach($columns as $state=>$column)<section class="task-column"><h2>{{ __('ui.task_'.$state) }} <small>{{ $column->count() }}{{ $column->count()===50?'+':'' }}</small></h2>@forelse($column as $task)@include('tasks.card')@empty<p class="muted">{{ __('ui.no_tasks') }}</p>@endforelse
@if($column->count()===50)<a href="{{ route('tasks.index',['view'=>'list','status'=>$state]) }}">{{ __('ui.view_all') }}</a>@endif</section>@endforeach</div>
@else<section class="panel">@forelse($tasks as $task)@include('tasks.card')@empty<p class="muted">{{ __('ui.no_tasks') }}</p>@endforelse</section>{{ $tasks->links() }}@endif
<section class="panel form-panel" id="new-task"><h2>{{ __('ui.new_task') }}</h2>@include('tasks.form')</section>
@endsection

