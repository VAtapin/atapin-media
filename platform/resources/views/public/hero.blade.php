@php
$section=$section??'start';
$isHome=$section==='start';
$heroTitle=$isHome?__('public.hero_title_line1')."\n".__('public.hero_title_line2'):__('public.heading_'.$section);
$sideQuote=$isHome?__('public.hero_side_quote'):__('public.hero_side_quote_'.$section);
$emptyUrl=match($section){
    'start'=>'/videos/vorschau',
    'videos'=>'/videos/vorschau',
    'beitraege'=>'/beitraege/vorschau',
    'buecher'=>'/buecher/vorschau',
    'podcast'=>'/podcast',
    'live'=>'/live',
    default=>'#catalog',
};
$emptyHint=match($section){
    'live'=>__('public.no_live'),
    'ueber-uns','unsere-mission'=>__('public.section_pending'),
    default=>__('public.no_content'),
};
@endphp
<section class="public-overview-hero public-overview-hero-{{ $section }}" style="--hero-image:url('{{ $heroImage }}')">
<img class="public-overview-hero-background" src="{{ $heroImage }}" alt="" aria-hidden="true">
<div class="public-overview-hero-inner">
<div class="public-overview-copy">
<p class="public-eyebrow">{{ $isHome?__('public.hero_eyebrow'):__('public.eyebrow_'.$section) }}</p>
<h1>{{ $heroTitle }}</h1>
<p class="public-overview-intro">{{ $isHome?__('public.hero_intro'):__('public.intro_'.$section) }}</p>
<div class="public-overview-actions">
<a class="public-button" href="{{ $isHome?'/videos':'#catalog' }}">
@if($isHome)
@include('public.icon',['name'=>'video'])
{{ __('public.discover') }}
@else
{{ __('public.discover_'.$section) }}
@endif
<span>→</span></a>
<a class="public-button public-button-secondary" href="{{ $section==='community'?'/ueber-uns':'/unsere-mission' }}">{{ __('public.more_about') }} <span>→</span></a>
</div>
<blockquote><p>{{ __('public.hero_quote') }}</p><cite>{{ __('public.hero_quote_source') }}</cite></blockquote>
</div>
<div class="public-overview-feature">
@if($isHome && ($featured??null))
<a class="public-feature-link-wrap" href="{{ $featured['url'] }}">
@include('public.picture',['art'=>$featured['art']??null,'image'=>$featured['image']??null,'pictureClass'=>'public-feature-picture'])
<div class="public-feature-content">
<span class="public-feature-label">{{ __('public.featured_video') }}</span>
<h2>{{ $featured['title'] }}</h2>
<p>{{ $featured['excerpt']??'' }}</p>
<div class="public-feature-author">{{ $featured['author']??'' }}<small>{{ $featured['meta']??'' }}</small></div>
</div>
<span class="public-play" aria-label="{{ __('public.play') }}">▶</span>
</a>
@elseif($section==='community')
@include('public.poll',['poll'=>$poll])
@elseif($featured??null)
@include('public.picture',['image'=>$featured['image']])
<div class="public-feature-content">
<span class="public-feature-label">{{ __('public.featured_'.$section) }}</span>
<h2><a href="{{ $featured['url'] }}">{{ $featured['title'] }}</a></h2>
<p>{{ $featured['excerpt'] }}</p>
<div class="public-feature-author">{{ $featured['author']?:'—' }}<small>{{ $featured['meta']?:'—' }}</small></div>
</div>
@if($section==='podcast'&&($audio=$assets->firstWhere('kind','audio')))
<audio controls preload="metadata" src="{{ $audio->publicUrl() ?? route('public.media',[$record,$audio]) }}"></audio>
@else
<a class="public-feature-link" href="{{ $featured['url'] }}">{{ __('public.view_now') }} →</a>
@endif
@else
@include('public.empty',['url'=>$emptyUrl,'hint'=>$emptyHint,'emptyClass'=>'public-overview-empty-slot'])
@endif
</div>
</div>
<aside class="public-hero-side-copy"><p>{{ $sideQuote }}</p></aside>
</section>
