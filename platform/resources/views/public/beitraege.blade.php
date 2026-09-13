@extends('public.layout',['title'=>__('public.section_beitraege')])

@section('content')

@include('public.hero')<div class="public-wide">
@include('public.filters')<div class="public-post-overview">
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
