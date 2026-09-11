@extends('layouts.app')
@section('title',__('ui.projects'))
@section('content')
<a class="back-link" href="{{ route('projects.index') }}">← {{ __('ui.projects') }}</a><div class="page-heading"><h1>{{ __($project->exists?'ui.edit_project':'ui.new_project') }}</h1></div><section class="panel form-panel"><form method="post" action="{{ $project->exists?route('projects.update',$project):route('projects.store') }}">@csrf @if($project->exists)@method('PUT')@endif
<label>{{ __('ui.title') }}<input name="title" value="{{ old('title',$project->title) }}" maxlength="255" required></label>
<label>{{ __('ui.description') }}<textarea name="description" rows="6" maxlength="20000">{{ old('description',$project->description) }}</textarea></label>
<div class="form-row"><label>{{ __('ui.phase') }}<select name="status">@foreach(\App\Models\Project::STATES as $state)<option value="{{ $state }}" @selected(old('status',$project->status)===$state)>{{ __('ui.project_'.$state) }}</option>@endforeach</select></label>
<label>{{ __('ui.due_date') }}<input type="date" name="due_date" value="{{ old('due_date',$project->due_date?->format('Y-m-d')) }}"></label></div><button class="button">{{ __('ui.save') }}</button></form></section>
@endsection
