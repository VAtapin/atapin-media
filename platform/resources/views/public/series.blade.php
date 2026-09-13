<section class="public-panel public-series-panel" id="series">
<div class="public-panel-heading">
<h2>{{ __('public.series_'.$section) }}</h2>
<a href="#catalog">{{ __('public.show_all') }} →</a>
</div>
<div class="public-series">
@forelse($series as $item)<a href="{{ $item['url'] }}">
<strong>{{ $item['title'] }}</strong>
<small>{{ $item['count'] }} {{ __('public.episodes') }}</small>
<span>→</span>
</a>
@empty
@include('public.empty')
@endforelse</div>
</section>
