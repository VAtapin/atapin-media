@extends('public.layout',['title'=>__('public.section_'.$section)])
@section('content')
@if(in_array($section,['ueber-uns','unsere-mission'],true))
@include('public.hero',['section'=>$section,'featured'=>null,'assets'=>collect(),'record'=>null,'poll'=>null])
@endif
<div class="public-page">
@if(!in_array($section,['ueber-uns','unsere-mission'],true))<h1>{{ __('public.section_'.$section) }}</h1>@endif
@if(in_array($section,['videos','beitraege','search']))
<form class="public-catalog-search" method="get"><label for="catalog-q">{{ __('ui.search') }}</label><input id="catalog-q" type="search" name="q" maxlength="120" value="{{ request('q') }}"><button class="public-button">{{ __('ui.search') }}</button></form>
<div class="public-catalog">@forelse($items as $item)<article class="public-panel"><a href="{{ $item['url'] }}">@include('public.picture',['image'=>$item['image']])<h2>{{ $item['title'] }}</h2><p>{{ $item['excerpt'] }}</p><small>{{ $item['meta'] }}</small></a></article>@empty<p class="public-empty">{{ __('public.no_published_content') }}</p>@endforelse</div>
<nav class="public-pagination" aria-label="{{ __('public.pagination') }}">@if($items->previousPageUrl())<a href="{{ $items->previousPageUrl() }}">← {{ __('public.previous') }}</a>@endif @if($items->nextPageUrl())<a href="{{ $items->nextPageUrl() }}">{{ __('public.next') }} →</a>@endif</nav>
@elseif($section==='kontakt')
@if($orderBook??null)<h2>{{ __('public.order_context') }}: {{ $orderBook->title }}</h2>@endif
<form class="public-contact-form public-panel" method="post" action="{{ route('public.contact-submit') }}">@csrf
<label>{{ __('public.contact_name') }}<input name="name" required maxlength="120" autocomplete="name" value="{{ old('name',auth()->user()?->name) }}"></label>
<label>{{ __('public.email_placeholder') }}<input name="email" type="email" required maxlength="255" autocomplete="email" value="{{ old('email',auth()->user()?->email) }}"></label>
<label>{{ __('public.contact_subject') }}<input name="subject" required maxlength="200" value="{{ old('subject',($orderBook??null)?__('public.order_context').': '.$orderBook->title:'') }}"></label>
<label>{{ __('public.contact_body') }}<textarea name="body" required minlength="10" maxlength="10000" rows="7">{{ old('body') }}</textarea></label>
<input name="website" tabindex="-1" autocomplete="off" aria-hidden="true" hidden>
<label class="public-consent"><input name="privacy" type="checkbox" value="1" required> {{ __('public.contact_privacy') }} <a href="/datenschutz">{{ __('public.privacy') }}</a></label>
<button class="public-button">{{ __('public.send') }} →</button>
</form>
@if(!empty($contactEmail))<p><a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a></p>@endif
@elseif(!empty($document))<div class="public-document">{{ $document }}</div>
@else<p class="public-empty">{{ __('public.section_pending') }}</p>@endif
@if(in_array($section,['ueber-uns','unsere-mission']))<nav class="public-hero-actions"><a class="public-button" href="{{ $section==='ueber-uns'?'/unsere-mission':'/ueber-uns' }}">{{ __('public.section_'.($section==='ueber-uns'?'unsere-mission':'ueber-uns')) }} →</a><a class="public-button public-button-secondary" href="/kontakt">{{ __('public.contact') }} →</a></nav>@endif
</div>
@endsection
