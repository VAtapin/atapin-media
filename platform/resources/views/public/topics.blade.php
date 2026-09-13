<section class="public-panel">
<div class="public-panel-heading">
<h2>{{ __('public.topics') }}</h2>
<a href="#catalog">{{ __('public.show_all') }} →</a>
</div>
<nav class="public-topic-list">
@forelse($topics as $tag=>$count)<a href="{{ route('public.'.$section,['tag'=>$tag]) }}">
<span class="public-gold">◇</span> {{ $tag }} <small>{{ $count }}</small>
<span>→</span>
</a>
@empty
@include('public.empty')
@endforelse</nav>
</section>
