@php($video=$assets->firstWhere('kind','video'))@php($audio=$assets->firstWhere('kind','audio'))
@php($isLiveEvent=$record && ($record->metadata['public_section']??null)==='live')
@php($liveFallbackImage=$card['image']??($heroImage??config('public_ui.hero_image')))

@if($record && ($record->metadata['live_stream_enabled']??false)&&($record->metadata['live_status']??'')==='live')
<div class="public-live-player-shell" data-live-player data-hls-url="/_live/live-{{ $record->id }}/index.m3u8?cookieCheck=1">
<iframe class="public-main-player" src="/_live/live-{{ $record->id }}/?cookieCheck=1" title="{{ $record->title }}" allow="autoplay; fullscreen" allowfullscreen hidden></iframe>
<div class="public-live-player-fallback" data-live-player-fallback><img src="{{ $liveFallbackImage }}" alt="{{ $record->title }}"><div><strong>{{ __('public.live_connection_pending') }}</strong><span>{{ __('public.live_player_waiting') }}</span></div></div>
</div>
@elseif($isLiveEvent)
<div class="public-live-player-fallback public-live-player-fallback-static"><img src="{{ $liveFallbackImage }}" alt="{{ $record->title }}"><div><strong>{{ ($record->metadata['live_status']??null)==='ended'?__('public.live_finished'):__('public.live_player_waiting') }}</strong><span>{{ __('public.live_poster_hint') }}</span></div></div>
@elseif($record && ($video||$audio))

@if($video)<video class="public-main-player" controls preload="metadata"
@if($card['image']??null)poster="{{ $card['image'] }}"
@endif src="{{ route('public.media',[$record,$video]) }}" @auth data-progress-url="{{ route('public.record-state',$record) }}" data-resume="{{ $states['progress']['position']??0 }}" @endauth>
</video>
@else<audio class="public-main-audio" controls preload="metadata" src="{{ route('public.media',[$record,$audio]) }}" @auth data-progress-url="{{ route('public.record-state',$record) }}" data-resume="{{ $states['progress']['position']??0 }}" @endauth>
</audio>
@endif

@else
@include('public.empty',['emptyClass'=>'public-empty-player','hint'=>__('public.no_local_playback')])
@endif
