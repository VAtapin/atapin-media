<section class="public-category-hero public-category-hero-{{ $section }}" style="--hero-image:url('{{ $heroImage }}')">
<div class="public-category-hero-inner">
<div class="public-category-copy">
<p class="public-eyebrow">{{ __('public.eyebrow_'.$section) }}</p>
<h1>{{ __('public.heading_'.$section) }}</h1>
<p>{{ __('public.intro_'.$section) }}</p>
<div class="public-hero-actions">
<a class="public-button" href="#catalog">{{ __('public.discover_'.$section) }} →</a>
<a class="public-button public-button-secondary" href="{{ $section==='community'?'/ueber-uns':'/unsere-mission' }}">{{ __('public.more_about') }} →</a>
</div>
<blockquote>{{ __('public.hero_quote') }}<cite>{{ __('public.hero_quote_source') }}</cite>
</blockquote>
</div>

@if($section==='community')<div class="public-feature public-hero-poll">
@include('public.poll',['poll'=>$poll])</div>

@elseif($featured)<div class="public-feature public-category-feature">
@include('public.picture',['image'=>$featured['image']])<div class="public-feature-content">
<span class="public-feature-label">{{ __('public.featured_'.$section) }}</span>
<h2>
<a href="{{ $featured['url'] }}">{{ $featured['title'] }}</a>
</h2>
<p>{{ $featured['excerpt'] }}</p>
<div class="public-feature-author">{{ $featured['author']?:'—' }}<small>{{ $featured['meta']?:'—' }}</small>
</div>
</div>

@if($section==='podcast' && ($audio=$assets->firstWhere('kind','audio')))<audio controls preload="metadata" src="{{ route('public.media',[$record,$audio]) }}">
</audio>
@else<a class="public-feature-link" href="{{ $featured['url'] }}">{{ __('public.view_now') }} →</a>
@endif</div>

@else<div class="public-feature public-category-feature public-feature-empty">
@include('public.empty',['url'=>match($section){'videos'=>'/videos/vorschau','beitraege'=>'/beitraege/vorschau','buecher'=>'/buecher/vorschau',default=>'#catalog'},'hint'=>__('public.featured_'.$section)])</div>
@endif
</div>
</section>
