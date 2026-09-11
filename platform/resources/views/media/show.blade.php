@extends('layouts.app')
@section('title', __('ui.media_library'))
@section('content')
<a class="back-link" href="{{ route('media.index') }}">← {{ __('ui.media_library') }}</a><div class="page-heading"><div><p class="eyebrow">{{ __('ui.kind_'.$media->kind) }}</p><h1>{{ $media->title }}</h1></div><a class="button" href="{{ route('media.download',$media) }}">{{ __('ui.download_original') }}</a></div>
<div class="detail-grid"><section class="panel preview">
@if(in_array($media->mime,['image/jpeg','image/png','image/webp','image/gif']))<img src="{{ route('media.preview',$media) }}" alt="{{ $media->title }}">
@elseif(in_array($media->mime,['video/mp4','video/webm']))<video controls preload="metadata" src="{{ route('media.preview',$media) }}"></video>
@elseif(in_array($media->mime,['audio/mpeg','audio/ogg']))<audio controls preload="metadata" src="{{ route('media.preview',$media) }}"></audio>
@else<div class="empty">@include('components.icon',['name'=>'media'])<p>{{ __('ui.preview_unavailable') }}</p></div>@endif
</section><section class="panel"><h2>{{ __('ui.file_details') }}</h2><dl><dt>{{ __('ui.original_name') }}</dt><dd>{{ $media->original_name }}</dd><dt>{{ __('ui.type') }}</dt><dd>{{ $media->mime }}</dd><dt>{{ __('ui.size') }}</dt><dd>{{ $media->formattedSize() }}</dd><dt>{{ __('ui.source') }}</dt><dd>{{ $media->source }}</dd><dt>{{ __('ui.date') }}</dt><dd>{{ $media->created_at->timezone(config('platform.timezone'))->format('d.m.Y H:i') }}</dd></dl>
@can('media.edit')<form method="post" action="{{ route('media.update',$media) }}">@csrf @method('PATCH')<label>{{ __('ui.title') }}<input name="title" value="{{ old('title',$media->title) }}" required maxlength="255"></label><button class="button secondary">{{ __('ui.save') }}</button></form>@endcan</section></div>
@endsection
