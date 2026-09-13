@extends('public.layout',['title'=>__('public.section_buecher')])

@section('announcement')
<div class="public-announcement">
@if($featured)<a href="{{ $featured['url'] }}">{{ __('public.new_in_library') }}: {{ $featured['title'] }} →</a>
@else ◇ {{ __('public.no_data') }} · {{ __('public.new_in_library') }}
@endif</div>
@endsection
@section('content')
@include('public.hero')<div class="public-wide">
<section class="public-panel public-book-poll">
@include('public.poll')</section>
@include('public.filters')<div class="public-books-overview">
<div class="public-books-main">
<div class="public-books-top">
@include('public.cards',['heading'=>__('public.book_recommendation'),'cards'=>$items->take(3),'style'=>'book','emptyUrl'=>'/buecher/vorschau'])
@include('public.cards',['heading'=>__('public.new_in_library'),'cards'=>$items->slice(3,2),'style'=>'book','emptyUrl'=>'/buecher/vorschau'])</div>
@include('public.cards',['heading'=>__('public.popular_books'),'cards'=>$popular,'style'=>'book','panelClass'=>'public-book-ranked','listingUrl'=>'/buecher?sort=popular'])
@include('public.pagination')</div>
<aside class="public-right-sidebar">
<section class="public-panel">
<div class="public-panel-heading">
<h2>{{ __('public.reading_progress') }}</h2>
<a href="#reading">{{ __('public.bookshelf') }} →</a>
</div>
<div id="reading">
@forelse($readingBooks as $readingBook)
<p><a href="{{ $readingBook['url'] }}">{{ $readingBook['title'] }} →</a></p><p>{{ $readingBook['progress']===null?'◇ '. __('public.no_data'):min(100,$readingBook['progress']).'%' }}</p>
@empty
@include('public.empty',['hint'=>__('public.account_progress_hint')])
@endforelse
</div></section>
@include('public.cards',['heading'=>__('public.book_meets_video'),'cards'=>$relatedVideo?[$relatedVideo]:[],'listingUrl'=>'/videos','panelClass'=>'public-video-widget'])</aside>
</div>
@include('public.newsletter')</div>

@endsection
