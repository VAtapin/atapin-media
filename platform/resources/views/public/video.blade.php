@extends('public.layout',['title'=>$record?->title??__('public.no_data'),'layoutMode'=>'detail'])

@section('content')
<div class="public-detail-layout public-video-detail">
<div>
<nav class="public-breadcrumb">
<a href="/">{{ __('public.nav_start') }}</a>
<span>›</span>
<a href="/videos">{{ __('public.nav_videos') }}</a>
<span>›</span>
<span>{{ $record?->title??__('public.no_data') }}</span>
</nav>
@include('public.player')<div class="public-title-actions">
<h1>{{ $record?->title??'—' }}</h1>
<div class="public-action-row">
<button class="public-button public-button-secondary" data-share>{{ __('public.share') }}</button>
@include('public.state-button',['subject'=>$record,'action'=>'bookmark','label'=>__('public.watch_later')])
@if($video=$assets->firstWhere('kind','video'))<a class="public-button public-button-secondary" href="{{ route('public.media',[$record,$video]) }}" download="{{ $video->original_name }}">{{ __('public.download') }} ↓</a>
@else<button class="public-button public-button-secondary" disabled>{{ __('public.download') }}</button>
@endif</div>
</div>
<p class="public-record-meta">{{ $card['author']??'—' }} · {{ $card['meta']??'—' }}</p>
<p class="public-record-intro">{{ $card['excerpt']??__('public.no_data') }}</p>
<div class="public-tags">
@foreach($card['tags']??[] as $tag)<a href="{{ route('public.videos',['tag'=>$tag]) }}">{{ $tag }}</a>
@endforeach</div>
@include('public.video-tabs')</div>
<aside class="public-right-sidebar">
@include('public.cards',['heading'=>__('public.next_videos'),'cards'=>$related,'panelClass'=>'public-related','listingUrl'=>'/videos'])
@include('public.live-widget')<a class="public-panel public-side-cta" href="/community">
@include('public.icon',['name'=>'community']) {{ __('public.heading_community') }} →</a>
@include('public.newsletter',['compact'=>true])</aside>
</div>

@endsection
