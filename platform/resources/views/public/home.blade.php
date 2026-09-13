<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $siteName }}</title>
<meta name="description" content="{{ $description }}">
<link rel="stylesheet" href="/assets/fonts/fonts.css">
<link rel="stylesheet" href="/assets/brand/ui-kit.css">
<link rel="stylesheet" href="/assets/public.css">
<link rel="stylesheet" href="/assets/public-pages.css?v=3">
<link rel="icon" href="/favicon.png">
<script src="/assets/public.js?v=2" defer>
</script>
</head>
<body class="public-site public-home">

@include('public.header',['section'=>'start'])
<main>
    <section class="public-hero">

@if(!empty($heroImage))<img class="public-hero-background" src="{{ $heroImage }}" alt="">
@endif
        <div class="public-hero-inner">
            <div class="public-hero-copy">
<p class="public-eyebrow">{{ __('public.hero_eyebrow') }}</p>
<h1>{{ __('public.hero_title_line1') }}<br>{{ __('public.hero_title_line2') }}</h1>
<p class="public-hero-intro">{{ __('public.hero_intro') }}</p>
<div class="public-hero-actions">
<a class="public-button" href="/videos">
@include('public.icon',['name'=>'video']) {{ __('public.discover') }} <span>→</span>
</a>
<a class="public-button public-button-secondary" href="/ueber-uns">{{ __('public.more_about') }} <span>→</span>
</a>
</div>
<blockquote>
<p>{{ __('public.hero_quote') }}</p>
<cite>{{ __('public.hero_quote_source') }}</cite>
</blockquote>
</div>

@if($featured)
            <a class="public-feature" href="{{ $featured['url'] }}">
@include('public.picture',['art'=>$featured['art']??null,'image'=>$featured['image']??null,'pictureClass'=>'public-feature-picture'])<div class="public-feature-content">
<span class="public-feature-label">{{ __('public.featured_video') }}</span>
<h2>{{ $featured['title'] }}</h2>
<p>{{ $featured['excerpt']??'' }}</p>
<div class="public-feature-author">{{ $featured['author']??'' }}<small>{{ $featured['meta']??'' }}</small>
</div>
</div>
<span class="public-play" aria-label="{{ __('public.play') }}">▶</span>
</a>

@else
            <div class="public-feature public-feature-empty">
@include('public.empty',['url'=>'/videos/vorschau','hint'=>__('public.featured_video')])</div>

@endif
        </div>
    </section>
    <nav class="public-section-cards" aria-label="{{ __('public.sections') }}">
@foreach(['video'=>'videos','article'=>'beitraege','book'=>'buecher','live'=>'live','podcast'=>'podcast','community'=>'community'] as $icon=>$key)<a href="{{ config('public_ui.navigation.'.$key.'.path') }}">
<span class="public-section-icon">
@include('public.icon',['name'=>$icon])</span>
<div>
<h2>{{ __('public.card_'.$key) }}</h2>
<p>{{ __('public.card_'.$key.'_hint') }}</p>
</div>
<span class="public-section-arrow">→</span>
</a>
@endforeach</nav>
    <div class="public-grid public-home-grid">

@include('public.latest',['heading'=>__('public.latest_videos'),'listingUrl'=>'/videos','allLabel'=>__('public.all_videos'),'items'=>$videos])

@include('public.latest',['heading'=>__('public.latest_articles'),'listingUrl'=>'/beitraege','allLabel'=>__('public.all_articles'),'items'=>$articles])
        <section class="public-panel">
<div class="public-panel-heading">
<h2>{{ __('public.book_recommendation') }}</h2>
<a href="/buecher">{{ __('public.all_books') }} <span>→</span>
</a>
</div>
@if($book)<div class="public-book-feature">
<a href="{{ $book['url'] }}">
@include('public.picture',['art'=>$book['art']??null,'image'=>$book['image']??null])</a>
<div>
<span class="public-book-label">{{ __('public.book') }}</span>
<h3>{{ $book['title'] }}</h3>
<p>{{ $book['subtitle']??'' }}</p>
<p class="public-book-description">{{ $book['description']??'' }}</p>
@if(!empty($book['price']))<strong>{{ $book['price'] }}</strong>
@endif<a class="public-button" href="{{ $book['url'] }}">{{ __('public.view_now') }} →</a>
</div>
</div>
@else
@include('public.empty',['url'=>'/buecher/vorschau','hint'=>__('public.no_content')])
@endif</section>
        <section class="public-panel">
<div class="public-panel-heading">
<h2>{{ __('public.next_live') }}</h2>
<a href="/live">{{ __('public.all_live') }} <span>→</span>
</a>
</div>
@if($live)<a href="{{ $live['url'] }}">
@include('public.picture',['art'=>$live['art']??null,'image'=>$live['image']??null])</a>
<h3 class="public-live-title">{{ $live['title'] }}</h3>
<p class="public-live-excerpt">{{ $live['excerpt']??'' }}</p>
<div class="public-live-meta">
<span>{{ $live['date']??'' }}</span>
<span>{{ $live['viewers']??'' }}</span>
</div>
<a class="public-button public-live-cta" href="{{ $live['url'] }}">{{ __('public.join_live') }} →</a>
@else
@include('public.empty',['url'=>'/live','hint'=>__('public.no_live')])
@endif</section>
    </div>
    @include('public.newsletter',['section'=>'start'])
</main>

@include('public.footer')
</body>
</html>
