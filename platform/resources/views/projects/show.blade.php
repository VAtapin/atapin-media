@extends('layouts.app')
@section('title',__('ui.projects'))
@section('content')
<a class="back-link" href="{{ route('projects.index') }}">← {{ __('ui.projects') }}</a><div class="page-heading"><div><p class="eyebrow">{{ __('ui.project_'.$project->status) }}</p><h1>{{ $project->title }}</h1></div><a class="button secondary" href="{{ route('projects.edit',$project) }}">{{ __('ui.edit') }}</a></div>
<section class="panel"><ol class="phases">@foreach(\App\Models\Project::STATES as $state)<li @class(['current'=>$state===$project->status])>{{ __('ui.project_'.$state) }}</li>@endforeach</ol><p class="prose">{{ $project->description }}</p><p class="muted">{{ $project->owner?->name }} @if($project->due_date) · {{ __('ui.due_date') }}: {{ $project->due_date->format('d.m.Y') }} @endif</p></section>
<section class="panel"><h2>{{ __('ui.tasks') }}</h2>@forelse($project->tasks as $task)@include('tasks.card')@empty<p class="muted">{{ __('ui.no_tasks') }}</p>@endforelse</section>
<section class="panel"><h2>{{ __('ui.new_task') }}</h2>@include('tasks.form',['fixedProject'=>$project])</section>
@endsection
