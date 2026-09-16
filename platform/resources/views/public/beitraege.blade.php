@extends('public.layout',['title'=>__('public.section_beitraege')])
@push('publicStyles')<link rel="stylesheet" href="/assets/public-book-shelf.css?v=7">@endpush
@push('publicScripts')<script src="/assets/public-book-shelf.js?v=6" defer></script>@endpush

@section('content')

@include('public.hero')<div class="public-wide">
@include('public.filters')
@include('public.section-book-shelf')
<div class="public-post-overview">
<div>
@include('public.cards',['heading'=>__('public.latest_articles'),'cards'=>$items,'paginator'=>$items,'style'=>'post','panelClass'=>'public-post-grid','listingUrl'=>'/beitraege','emptyUrl'=>'/beitraege/vorschau'])</div>
<aside>
@include('public.cards',['heading'=>__('public.popular_articles'),'cards'=>$popular,'style'=>'video','panelClass'=>'public-ranked','listingUrl'=>'/beitraege?sort=popular'])</aside>
<aside class="public-right-sidebar">
@include('public.topics')
@include('public.book-widget')</aside>
</div>
@include('public.newsletter')</div>

@endsection
