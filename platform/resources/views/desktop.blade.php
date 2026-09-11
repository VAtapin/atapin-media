@extends('layouts.app')
@section('title', __('ui.desktop'))
@section('content')
<div class="page-heading"><div><p class="eyebrow">{{ now()->locale(app()->getLocale())->translatedFormat('l, d. F Y') }}</p><h1>{{ __('ui.welcome',['name'=>auth()->user()->name]) }}</h1><p class="muted">{{ __('ui.desktop_intro') }}</p></div>@can('media.upload')<a class="button" href="{{ route('media.index') }}#upload">@include('components.icon',['name'=>'upload']) {{ __('ui.upload_files') }}</a>@endcan</div>
@if($project)<section class="panel continue-panel"><div><p class="eyebrow">{{ __('ui.continue') }}</p><h2>{{ $project->title }}</h2><p class="muted">{{ __('ui.project_'.$project->status) }} @if($project->due_date) · {{ $project->due_date->format('d.m.Y') }} @endif</p></div><a class="button" href="{{ route('projects.show',$project) }}">{{ __('ui.open_project') }} →</a></section>@endif
<section class="stats">
@if($count !== null)<article class="stat"><span>{{ __('ui.media_library') }}</span><strong>{{ number_format($count,0,',','.') }}</strong><small>{{ __('ui.files') }}</small></article>
<article class="stat"><span>{{ __('ui.storage') }}</span><strong>{{ number_format($bytes / 1073741824,2,',','.') }} <small>GB</small></strong><small>{{ __('ui.registered_originals') }}</small></article>@endif
@if($queued !== null)<article class="stat"><span>{{ __('ui.processing') }}</span><strong>{{ $queued }}</strong><small>{{ __('ui.jobs_waiting') }}</small></article><article class="stat"><span>{{ __('ui.failed_jobs') }}</span><strong>{{ $failed }}</strong><small>{{ __('ui.jobs_need_attention') }}</small></article>@endif
</section>
@can('media.view')<section class="panel"><div class="panel-heading"><h2>{{ __('ui.recent_files') }}</h2><a href="{{ route('media.index') }}">{{ __('ui.view_all') }} →</a></div>
@if($media->isEmpty())<div class="empty">@include('components.icon',['name'=>'media'])<h3>{{ __('ui.no_media') }}</h3><p>{{ __('ui.media_empty_hint') }}</p>@can('media.upload')<a class="button secondary" href="{{ route('media.index') }}#upload">{{ __('ui.upload_files') }}</a>@endcan</div>
@else<div class="media-grid">@foreach($media as $item)@include('media.card')@endforeach</div>@endif
</section>@endcan
@endsection
