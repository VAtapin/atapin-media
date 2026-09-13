@php($video=$assets->where('kind','video')->sortByDesc('created_at')->first())@php($audio=$assets->firstWhere('kind','audio'))
@php($isLiveEvent=$record && ($record->metadata['public_section']??null)==='live')
@php($isEndedLive=$isLiveEvent && ($record->metadata['live_status']??null)==='ended')
@php($liveStatus=$isLiveEvent?($record->metadata['live_status']??null):null)
@php($liveStatusLabel=$liveStatus?__('public.live_'.$liveStatus):null)
@php($liveStart=$isLiveEvent?($record->metadata['starts_at']??null):null)
@php($liveDate=$liveStart?\Illuminate\Support\Carbon::parse($liveStart)->timezone(config('app.timezone'))->format('d.m.Y H:i'):null)
@php($liveFallbackImage=$card['image']??($heroImage??config('public_ui.hero_image')))

@if($record && ($record->metadata['live_stream_enabled']??false)&&($record->metadata['live_status']??'')==='live')
<div class="public-live-player-shell" data-live-player data-hls-url="/_live/live/index.m3u8?cookieCheck=1">
<iframe class="public-main-player" src="/_live/live/?cookieCheck=1" title="{{ $record->title }}" allow="autoplay; fullscreen" allowfullscreen hidden></iframe>
<div class="public-live-player-fallback" data-live-player-fallback><img src="{{ $liveFallbackImage }}" alt="{{ $record->title }}">@include('public.live-poster')</div>
</div>
@elseif($isEndedLive && $video)
<video class="public-main-player" controls preload="none" poster="{{ $card['image']??$heroImage??config('public_ui.hero_image') }}" src="{{ $video->publicUrl() ?? route('public.media',[$record,$video]) }}"></video>
@elseif($isLiveEvent)
<div class="public-live-player-fallback public-live-player-fallback-static"><img src="{{ $liveFallbackImage }}" alt="{{ $record->title }}">@include('public.live-poster')</div>
@elseif($record && ($video||$audio))

@if($video)<video class="public-main-player" controls preload="none"
data-view-url="{{ route('public.record-view',$record) }}" poster="{{ $card['image']??$heroImage??config('public_ui.hero_image') }}"
src="{{ $video->publicUrl() ?? route('public.media',[$record,$video]) }}" @auth data-progress-url="{{ route('public.record-state',$record) }}" data-resume="{{ $states['progress']['position']??0 }}" @endauth>
</video>
@else<audio class="public-main-audio" controls preload="metadata" src="{{ $audio->publicUrl() ?? route('public.media',[$record,$audio]) }}" @auth data-progress-url="{{ route('public.record-state',$record) }}" data-resume="{{ $states['progress']['position']??0 }}" @endauth>
</audio>
@endif

@else
@include('public.empty',['emptyClass'=>'public-empty-player','hint'=>__('public.no_local_playback')])
@endif
