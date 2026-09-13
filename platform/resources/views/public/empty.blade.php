@if(!empty($url))<a href="{{ $url }}" class="public-empty-slot {{ $emptyClass??'' }}">@else<div class="public-empty-slot {{ $emptyClass??'' }}">@endif
<span class="public-empty-symbol" aria-hidden="true">◇</span><span>{{ __('public.no_data') }}</span>@if(!empty($hint))<small>{{ $hint }}</small>@endif
@if(!empty($url))<span aria-hidden="true">→</span></a>@else</div>@endif
