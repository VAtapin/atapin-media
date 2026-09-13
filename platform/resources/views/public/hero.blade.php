@php($isHome=($section??'')==='start')
<section class="public-overview-hero {{ $isHome?'public-hero':'public-category-hero' }} public-overview-hero-{{ $section }} public-category-hero-{{ $section }}" style="--hero-image:url('{{ $heroImage }}')">
@if($isHome&&$heroImage)<img class="public-hero-background" src="{{ $heroImage }}" alt="">@endif
<div class="public-overview-hero-inner {{ $isHome?'public-hero-inner':'public-category-hero-inner' }}">
<div class="public-overview-copy {{ $isHome?'public-hero-copy':'public-category-copy' }}">
<p class="public-eyebrow">{{ $isHome?__('public.hero_eyebrow'):__('public.eyebrow_'.$section) }}</p>
<h1>@if($isHome){{ __('public.hero_title_line1') }}<br>{{ __('public.hero_title_line2') }}@else{{ __('public.heading_'.$section) }}@endif</h1>
<p class="public-overview-intro {{ $isHome?'public-hero-intro':'' }}">{{ $isHome?__('public.hero_intro'):__('public.intro_'.$section) }}</p>
<div class="public-overview-actions {{ $isHome?'public-hero-actions':'' }}">
<a class="public-button" href="{{ $isHome?'/videos':'#catalog' }}">@if($isHome)@include('public.icon',['name'=>'video']){{ __('public.discover') }}@else{{ __('public.discover_'.$section) }}@endif <span>→</span></a>
<a class="public-button public-button-secondary" href="{{ $section==='community'?'/ueber-uns':'/unsere-mission' }}">{{ __('public.more_about') }} <span>→</span></a>
</div>
<blockquote><p>{{ __('public.hero_quote') }}</p><cite>{{ __('public.hero_quote_source') }}</cite></blockquote>
</div>
<div class="public-overview-feature {{ $isHome?'public-feature':'public-feature public-category-feature' }}">
@if($isHome)
@if($featured??null)<a class="public-feature-link-wrap" href="{{ $featured['url'] }}">@include('public.picture',['art'=>$featured['art']??null,'image'=>$featured['image']??null,'pictureClass'=>'public-feature-picture'])<div class="public-feature-content"><span class="public-feature-label">{{ __('public.featured_video') }}</span><h2>{{ $featured['title'] }}</h2><p>{{ $featured['excerpt']??'' }}</p><div class="public-feature-author">{{ $featured['author']??'' }}<small>{{ $featured['meta']??'' }}</small></div></div><span class="public-play" aria-label="{{ __('public.play') }}">▶</span></a>@else@include('public.empty',['url'=>'/videos/vorschau','hint'=>__('public.featured_video')])@endif
@elseif($section==='community')
@include('public.poll',['poll'=>$poll])
@elseif($featured??null)
@include('public.picture',['image'=>$featured['image']])<div class="public-feature-content"><span class="public-feature-label">{{ __('public.featured_'.$section) }}</span><h2><a href="{{ $featured['url'] }}">{{ $featured['title'] }}</a></h2><p>{{ $featured['excerpt'] }}</p><div class="public-feature-author">{{ $featured['author']?:'—' }}<small>{{ $featured['meta']?:'—' }}</small></div></div>@if($section==='podcast'&&($audio=$assets->firstWhere('kind','audio')))<audio controls preload="metadata" src="{{ $audio->publicUrl() ?? route('public.media',[$record,$audio]) }}"></audio>@else<a class="public-feature-link" href="{{ $featured['url'] }}">{{ __('public.view_now') }} →</a>@endif
@else@include('public.empty',['url'=>match($section){'videos'=>'/videos/vorschau','beitraege'=>'/beitraege/vorschau','buecher'=>'/buecher/vorschau',default=>'#catalog'},'hint'=>__('public.featured_'.$section)])
@endif
</div>
@if($isHome)<aside class="public-hero-side-copy"><p>{{ __('public.hero_side_quote') }}</p></aside>@endif
</div>
</section>
