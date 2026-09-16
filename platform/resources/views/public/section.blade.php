@extends('public.layout',['title'=>__('public.section_'.$section)])
@section('content')
@if(in_array($section,['ueber-uns','unsere-mission'],true))
@include('public.hero',['section'=>$section,'featured'=>null,'assets'=>collect(),'record'=>null,'poll'=>null])
@endif
<div class="public-page">
@if($section==='ueber-uns')
<section class="public-about-page" id="about-content">
<div class="public-about-lead public-panel"><p class="public-eyebrow">{{ __('public.about_eyebrow') }}</p><h2>{{ __('public.about_intro_title') }}</h2><p>{{ __('public.about_intro') }}</p></div>
@if(!empty($document))<div class="public-about-custom public-panel"><div class="public-document">@include('public.rich-content',['content'=>$document])</div></div>@endif
<div class="public-about-story public-panel"><p class="public-eyebrow">{{ __('public.about_story_eyebrow') }}</p><h2>{{ __('public.about_story_title') }}</h2><p>{{ __('public.about_story') }}</p></div>
<div class="public-about-values"><div class="public-panel-heading"><h2>{{ __('public.about_values_title') }}</h2></div><div class="public-about-value-grid">@foreach(__('public.about_values') as $value)<article class="public-panel"><span class="public-about-value-mark" aria-hidden="true">◇</span><h3>{{ $value['title'] }}</h3><p>{{ $value['text'] }}</p></article>@endforeach</div></div>
<div class="public-about-invitation public-panel"><h2>{{ __('public.about_invitation_title') }}</h2><p>{{ __('public.about_invitation') }}</p><nav class="public-hero-actions"><a class="public-button" href="/videos">{{ __('public.about_explore_videos') }} →</a><a class="public-button public-button-secondary" href="/beitraege">{{ __('public.about_explore_articles') }} →</a><a class="public-button public-button-secondary" href="/kontakt">{{ __('public.contact') }} →</a></nav></div>
</section>
@else
@if(!in_array($section,['ueber-uns','unsere-mission'],true))<h1>{{ __('public.section_'.$section) }}</h1>@endif
@if(in_array($section,['videos','beitraege','search']))
<form class="public-catalog-search" method="get"><label for="catalog-q">{{ __('ui.search') }}</label><input id="catalog-q" type="search" name="q" maxlength="120" value="{{ request('q') }}"><button class="public-button">{{ __('ui.search') }}</button></form>
@if($section==='search'&&($searchBooks->isNotEmpty()||$searchTerms->isNotEmpty()))
@if($searchBooks->isNotEmpty())<section><h2>{{ __('public.section_buecher') }}</h2><div class="public-catalog">@foreach($searchBooks as $item)<article class="public-panel"><a href="{{ $item['url'] }}">@include('public.picture',['image'=>$item['image']])<h3>{{ $item['title'] }}</h3><p>{{ $item['excerpt'] }}</p></a></article>@endforeach</div><a href="{{ route('public.buecher',['q'=>request('q')]) }}">{{ __('public.all_buecher') }} →</a></section>@endif
@if($searchTerms->isNotEmpty())<section><h2>{{ __('public.topics') }} / {{ __('public.categories') }}</h2><div class="public-catalog">@foreach($searchTerms as $term)<article class="public-panel"><a href="{{ $term['url'] }}"><h3>{{ $term['name'] }}</h3><p>{{ $term['description'] }}</p></a></article>@endforeach</div><a href="{{ route('public.categories') }}">{{ __('public.home_topics_all') }} →</a></section>@endif
@endif
<div class="public-catalog">@forelse($items as $item)<article class="public-panel"><a href="{{ $item['url'] }}">@include('public.picture',['image'=>$item['image']])<h2>{{ $item['title'] }}</h2><p>{{ $item['excerpt'] }}</p><small>{{ $item['meta'] }}</small></a></article>@empty@if($section!=='search'||($searchBooks->isEmpty()&&$searchTerms->isEmpty()))<p class="public-empty">{{ __('public.no_published_content') }}</p>@endif@endforelse</div>
<nav class="public-pagination" aria-label="{{ __('public.pagination') }}">@if($items->previousPageUrl())<a href="{{ $items->previousPageUrl() }}">← {{ __('public.previous') }}</a>@endif @if($items->nextPageUrl())<a href="{{ $items->nextPageUrl() }}">{{ __('public.next') }} →</a>@endif</nav>
@elseif($section==='kontakt')
@if($orderBook??null)<h2>{{ __('public.order_context') }}: {{ $orderBook->title }}</h2>@endif
<form class="public-contact-form public-panel" method="post" action="{{ route('public.contact-submit') }}" data-public-ajax="contact">@csrf
<label>{{ __('public.contact_name') }}<input name="name" required maxlength="120" autocomplete="name" value="{{ old('name',auth()->user()?->name) }}"></label>
<label>{{ __('public.email_placeholder') }}<input name="email" type="email" required maxlength="255" autocomplete="email" value="{{ old('email',auth()->user()?->email) }}"></label>
<label>{{ __('public.contact_subject') }}<input name="subject" required maxlength="200" value="{{ old('subject',($orderBook??null)?__('public.order_context').': '.$orderBook->title:'') }}"></label>
<label>{{ __('public.contact_body') }}<textarea name="body" required minlength="10" maxlength="10000" rows="7">{{ old('body') }}</textarea></label>
<input name="website" tabindex="-1" autocomplete="off" aria-hidden="true" hidden>
<label class="public-consent"><input name="privacy" type="checkbox" value="1" required> {{ __('public.contact_privacy') }} <a href="/datenschutz">{{ __('public.privacy') }}</a></label>
<button class="public-button">{{ __('public.send') }} →</button>
</form>
@if(!empty($contactEmail))<p><a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a></p>@endif
@elseif(!empty($document))<div class="public-document">@include('public.rich-content',['content'=>$document])</div>
@else<p class="public-empty">{{ __('public.section_pending') }}</p>@endif
@if(in_array($section,['unsere-mission']))<nav class="public-hero-actions"><a class="public-button" href="/ueber-uns">{{ __('public.section_ueber-uns') }} →</a><a class="public-button public-button-secondary" href="/kontakt">{{ __('public.contact') }} →</a></nav>@endif
@endif
</div>
@endsection
