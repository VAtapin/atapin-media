@extends('public.layout',['title'=>$record?->title??__('public.no_data')])

@section('content')
<div class="public-detail-layout public-article-detail">
<article>
<nav class="public-breadcrumb">
<a href="/">{{ __('public.nav_start') }}</a> › <a href="/beitraege">{{ __('public.nav_beitraege') }}</a> › {{ $record?->title??__('public.no_data') }}</nav>
@if($card['image']??null)<img class="public-article-cover" src="{{ $card['image'] }}" alt="">
@else
@include('public.empty',['emptyClass'=>'public-article-cover'])
@endif<p class="public-eyebrow">{{ $card['tags'][0]??'—' }}</p>
<h1>{{ $record?->title??'—' }}</h1>
<p class="public-article-intro">{{ $card['excerpt']??__('public.no_data') }}</p>
<p class="public-record-meta">{{ $card['author']??'—' }} · {{ $card['meta']??'—' }}</p>
<div class="public-action-row">
@if($pdf=$assets->firstWhere('mime','application/pdf'))<a class="public-button" href="{{ route('public.media',[$record,$pdf]) }}" download>{{ __('public.pdf_download') }} ↓</a>
@else<button class="public-button" disabled>{{ __('public.pdf_download') }}</button>
@endif<button class="public-button public-button-secondary" data-read-aloud @disabled(!$record)>{{ __('public.read_aloud') }}</button>
<button class="public-button public-button-secondary" data-share>{{ __('public.share') }}</button>
@include('public.state-button',['subject'=>$record,'action'=>'bookmark','label'=>__('public.bookmark')])</div>
<div class="public-tags">
@foreach($card['tags']??[] as $tag)<a href="{{ route('public.beitraege',['tag'=>$tag]) }}">{{ $tag }}</a>
@endforeach</div>
<div class="public-document public-article-body" data-read-text>{{ $record?->body??__('public.no_data') }}</div>
@include('public.comments')</article>
<aside class="public-right-sidebar">
@include('public.quote')
@include('public.cards',['heading'=>__('public.related_articles'),'cards'=>$related,'panelClass'=>'public-related','listingUrl'=>'/beitraege'])
@include('public.book-widget')
@include('public.cards',['heading'=>__('public.related_video'),'cards'=>$relatedVideo?[$relatedVideo]:[],'panelClass'=>'public-related','listingUrl'=>'/videos'])</aside>
</div>

@endsection
