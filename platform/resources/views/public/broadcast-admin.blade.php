@extends('public.layout',['title'=>__('public.broadcast_manage'),'siteName'=>app(\App\Services\Settings::class)->get('site_name',config('platform.brand')),'description'=>__('public.broadcast_manage')])
@section('content')
<section class="public-panel">
<h1>{{ __('public.broadcast_manage') }}</h1>
@if($events)@foreach($events as $event)<p><a href="{{ route('public.broadcast-admin-show',$event) }}">{{ $event->title }}</a></p>@endforeach{{ $events->links() }}@endif
<form method="post" action="{{ $record?route('public.broadcast-update',$record):route('public.broadcast-create') }}">@csrf
<label>{{ __('ui.title') }}<input name="title" required maxlength="255" value="{{ $record?->title }}"></label>
<label>{{ __('public.description') }}<textarea name="body">{{ $record?->body }}</textarea></label>
<label>{{ __('public.schedule') }}<input name="starts_at" type="datetime-local" value="{{ !empty($record?->metadata['starts_at'])?\Illuminate\Support\Carbon::parse($record->metadata['starts_at'])->format('Y-m-d\TH:i'):'' }}"></label>
<label><input type="checkbox" name="published" value="1" @checked($record?->metadata['public_published']??false)>{{ __('public.broadcast_publish') }}</label>
<label><input type="checkbox" name="enabled" value="1" @checked($record?->metadata['live_stream_enabled']??false)>{{ __('public.broadcast_enable') }}</label>
@if($record)<label><input type="checkbox" name="rotate_key" value="1">{{ __('public.broadcast_rotate') }}</label>@endif
<button class="public-button">{{ __('ui.save') }}</button>
</form>
@if($record)<p>{{ __('public.broadcast_obs') }}</p><code>rtmp://127.0.0.1:1935/live-{{ $record->id }}?user=publisher&amp;pass={{ $key }}</code><p>{{ __('public.broadcast_security') }}</p><a href="/live?event={{ $record->id }}">{{ __('public.broadcast_preview') }}</a>@endif
</section>
@endsection
