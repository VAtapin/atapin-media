@extends('public.layout',['title'=>$record?->title??__('public.no_data'),'layoutMode'=>'detail'])

@section('content')
@php
    $imageAssets = ($assets ?? collect())->filter(fn ($asset) => $asset->kind === 'image');
    $coverAsset = $imageAssets->first(fn ($asset) => $asset->id === ($record?->metadata['cover_media_id'] ?? null))
        ?? $imageAssets->first(fn ($asset) => $asset->asset_role === 'thumbnail')
        ?? $imageAssets->first();
    $lightboxGroup = 'article-'.($record?->id ?? 'preview');
@endphp
<div class="public-detail-layout public-article-detail">
<article>
<nav class="public-breadcrumb">
<a href="/">{{ __('public.nav_start') }}</a> › <a href="/beitraege">{{ __('public.nav_beitraege') }}</a> › {{ $record?->title??__('public.no_data') }}</nav>
@if($card['image']??null)<button type="button" class="public-lightbox-trigger public-article-cover-trigger" data-image-lightbox data-image-lightbox-src="{{ $card['image'] }}" data-image-lightbox-alt="{{ $record?->title ?? '' }}" data-image-lightbox-group="{{ $lightboxGroup }}" aria-label="{{ __('public.image_open') }}"><img class="public-article-cover" src="{{ $card['image'] }}" alt="{{ $record?->title ?? '' }}"></button>
@else
@include('public.empty',['emptyClass'=>'public-article-cover'])
@endif<p class="public-eyebrow">{{ $card['tags'][0]??'—' }}</p>
<h1>{{ $record?->title??'—' }}</h1>
<p class="public-article-intro">{{ $card['excerpt']??__('public.no_data') }}</p>
<p class="public-record-meta">{{ $card['author']??'—' }} · {{ $card['meta']??'—' }}</p>
<div class="public-action-row">
@if($pdf=$assets->firstWhere('mime','application/pdf'))<a class="public-button" href="{{ $pdf->publicUrl() ?? route('public.media',[$record,$pdf]) }}" download>{{ __('public.pdf_download') }} ↓</a>
@else<button class="public-button" disabled>{{ __('public.pdf_download') }}</button>
@endif<button class="public-button public-button-secondary" data-read-aloud @disabled(!$record)>{{ __('public.read_aloud') }}</button>
<button class="public-button public-button-secondary" data-share>{{ __('public.share') }}</button>
@include('public.state-button',['subject'=>$record,'action'=>'bookmark','label'=>__('public.bookmark')])</div>
<div class="public-tags">
@foreach($card['tags']??[] as $tag)<a href="{{ route('public.beitraege',['tag'=>$tag]) }}">{{ $tag }}</a>
@endforeach</div>
@php($galleryAssets = $imageAssets->filter(fn ($asset) => $asset->id !== $coverAsset?->id))
@if($galleryAssets->isNotEmpty())
<section class="public-article-media-gallery" aria-label="{{ __('public.article_images') }}">
@foreach($galleryAssets as $asset)
@php($imageUrl = $asset->publicUrl() ?? route('public.media',[$record,$asset]))
<figure class="public-article-media-item"><button type="button" class="public-lightbox-trigger" data-image-lightbox data-image-lightbox-src="{{ $imageUrl }}" data-image-lightbox-alt="{{ $asset->title ?: ($record?->title ?? '') }}" data-image-lightbox-group="{{ $lightboxGroup }}" aria-label="{{ __('public.image_open') }}"><img src="{{ $imageUrl }}" alt="{{ $asset->title ?: ($record?->title ?? '') }}" loading="lazy"></button>@if($asset->title)<figcaption>{{ $asset->title }}</figcaption>@endif</figure>
@endforeach
</section>
@endif
<div class="public-document public-article-body" data-read-text>{{ $record?->body??__('public.no_data') }}</div>
@include('public.comments')</article>
<aside class="public-right-sidebar">
@include('public.quote')
@include('public.cards',['heading'=>__('public.related_articles'),'cards'=>$related,'panelClass'=>'public-related','listingUrl'=>'/beitraege'])
@include('public.book-widget')
@include('public.cards',['heading'=>__('public.related_video'),'cards'=>$relatedVideo?[$relatedVideo]:[],'panelClass'=>'public-related','listingUrl'=>'/videos'])</aside>
</div>
<dialog class="public-image-lightbox" data-image-lightbox-dialog aria-label="{{ __('public.image_viewer') }}">
<div class="public-image-lightbox-inner">
<button type="button" class="public-image-lightbox-close" data-image-lightbox-close aria-label="{{ __('public.image_close') }}">×</button>
<button type="button" class="public-image-lightbox-nav public-image-lightbox-prev" data-image-lightbox-prev aria-label="{{ __('public.image_previous') }}">‹</button>
<figure class="public-image-lightbox-figure"><img data-image-lightbox-image alt=""><figcaption data-image-lightbox-caption></figcaption></figure>
<button type="button" class="public-image-lightbox-nav public-image-lightbox-next" data-image-lightbox-next aria-label="{{ __('public.image_next') }}">›</button>
</div>
</dialog>

@endsection
