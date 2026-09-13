@extends('public.layout',['title'=>__('public.section_videos')])

@section('content')

@include('public.hero')
<div class="public-wide">
@include('public.filters')<div class="public-video-overview">
<div>
@include('public.cards',['heading'=>__('public.latest_videos'),'cards'=>$items,'paginator'=>$items,'style'=>'video','panelClass'=>'public-video-grid','listingUrl'=>'/videos','emptyUrl'=>'/videos/vorschau'])</div>
<aside>
@include('public.cards',['heading'=>__('public.popular_videos'),'cards'=>$popular,'style'=>'video','panelClass'=>'public-ranked','listingUrl'=>'/videos?sort=popular'])</aside>
<aside class="public-right-sidebar">
@include('public.live-widget')
@include('public.quote')</aside>
<div class="public-overview-series">
@include('public.series')</div>
</div>
@include('public.newsletter')</div>

@endsection
