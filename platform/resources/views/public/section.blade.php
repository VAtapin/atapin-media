@extends('public.layout',['title'=>__('public.section_'.$section)])
@section('content')
<h1>{{ __('public.section_'.$section) }}</h1>
@if(in_array($section,['videos','beitraege','search']))
<form class="public-catalog-search" method="get"><label for="catalog-q">{{ __('ui.search') }}</label><input id="catalog-q" type="search" name="q" maxlength="120" value="{{ request('q') }}"><button class="public-button">{{ __('ui.search') }}</button></form>
<div class="public-catalog">@forelse($items as $item)<article class="public-panel"><a href="{{ $item['url'] }}">@include('public.picture',['image'=>$item['image']])<h2>{{ $item['title'] }}</h2><p>{{ $item['excerpt'] }}</p><small>{{ $item['meta'] }}</small></a></article>@empty<p class="public-empty">{{ __('public.no_published_content') }}</p>@endforelse</div>
<nav class="public-pagination" aria-label="{{ __('public.pagination') }}">@if($items->previousPageUrl())<a href="{{ $items->previousPageUrl() }}">← {{ __('public.previous') }}</a>@endif @if($items->nextPageUrl())<a href="{{ $items->nextPageUrl() }}">{{ __('public.next') }} →</a>@endif</nav>
@elseif(!empty($document))<div class="public-document">{{ $document }}</div>
@elseif($section==='kontakt' && !empty($contactEmail))<a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>
@else<p class="public-empty">{{ __('public.section_pending') }}</p>@endif
@endsection
