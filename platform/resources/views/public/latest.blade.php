<section class="public-panel"><div class="public-panel-heading"><h2>{{ $heading }}</h2><a href="{{ $listingUrl }}">{{ $allLabel }} <span>→</span></a></div>
@forelse($items as $item)
<article class="public-latest-item"><a href="{{ $item['url'] }}" tabindex="-1" aria-hidden="true">@include('public.picture',['art'=>$item['art']??null,'image'=>$item['image']??null])</a><div><h3><a href="{{ $item['url'] }}">{{ $item['title'] }}</a></h3><p>{{ $item['author']??'' }}</p><small>{{ $item['meta']??'' }}</small></div></article>
@empty
<p class="public-empty">{{ __('public.no_content') }}</p>
@endforelse
</section>
