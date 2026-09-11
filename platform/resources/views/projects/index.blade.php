@extends('layouts.app')
@section('title',__('ui.projects'))
@section('content')
<div class="page-heading"><div><p class="eyebrow">{{ __('ui.workspace') }}</p><h1>{{ __('ui.projects') }}</h1></div><a class="button" href="{{ route('projects.create') }}">+ {{ __('ui.new_project') }}</a></div>
<form class="filters"><label class="search-label"><span class="sr-only">{{ __('ui.search') }}</span><input name="q" type="search" value="{{ request('q') }}" placeholder="{{ __('ui.search') }}"></label><button class="button secondary">{{ __('ui.search') }}</button></form>
<div class="project-grid">@forelse($projects as $project)<a class="panel project-card" href="{{ route('projects.show',$project) }}"><span class="pill">{{ __('ui.project_'.$project->status) }}</span><h2>{{ $project->title }}</h2><p class="muted">{{ \Illuminate\Support\Str::limit($project->description,140) }}</p><progress value="{{ $project->done_count }}" max="{{ max(1,$project->tasks_count) }}"></progress><div class="project-meta"><span>{{ $project->done_count }} / {{ $project->tasks_count }} {{ __('ui.tasks') }}</span><span>{{ $project->due_date?->format('d.m.Y') }}</span></div></a>@empty<section class="panel empty"><h2>{{ __('ui.no_projects') }}</h2><p>{{ __('ui.project_hint') }}</p></section>@endforelse</div>{{ $projects->links() }}
@endsection
