@php($video=($record?->metadata['public_section']??null)==='podcast'?null:$assets->where('kind','video')->sortByDesc('created_at')->first())@php($audio=$assets->firstWhere('kind','audio'))
@php($isLiveEvent=$record && ($record->metadata['public_section']??null)==='live')
@php($isEndedLive=$isLiveEvent && ($record->metadata['live_status']??null)==='ended')
@php($liveStatus=$isLiveEvent?($record->metadata['live_status']??null):null)
@php($liveStatusLabel=$liveStatus?__('public.live_'.$liveStatus):null)
@php($liveStart=$isLiveEvent?($record->metadata['starts_at']??null):null)
@php($liveDate=$liveStart?\Illuminate\Support\Carbon::parse($liveStart)->timezone(config('app.timezone'))->format('d.m.Y H:i'):null)
@php($liveFallbackImage=$card['image']??($heroImage??config('public_ui.hero_image')))
@php($videoPoster=$card['image']??($heroImage??config('public_ui.hero_image')))

@if($record && ($record->metadata['live_stream_enabled']??false)&&($record->metadata['live_status']??'')==='live')
<div class="public-live-player-shell" data-live-player data-hls-url="/_live/live/index.m3u8?cookieCheck=1">
<iframe class="public-main-player" data-src="/_live/live/?cookieCheck=1" title="{{ $record->title }}" allow="autoplay; fullscreen" allowfullscreen hidden></iframe>
<div class="public-live-player-fallback" data-live-player-fallback><img src="{{ $liveFallbackImage }}" alt="{{ $record->title }}">@include('public.live-poster')</div>
</div>
@elseif($isEndedLive && $video)
<div class="public-video-player-shell" style="--video-poster:url('{{ $videoPoster }}')"><video class="public-main-player" controls preload="none" poster="{{ $videoPoster }}" src="{{ $video->publicUrl() ?? route('public.media',[$record,$video]) }}"></video></div>
@elseif($isLiveEvent)
<div class="public-live-player-fallback public-live-player-fallback-static"><img src="{{ $liveFallbackImage }}" alt="{{ $record->title }}">@include('public.live-poster')</div>
@elseif($record && ($video||$audio))

@if($video)<div class="public-video-player-shell" style="--video-poster:url('{{ $videoPoster }}')"><video class="public-main-player" controls preload="none"
data-view-url="{{ route('public.record-view',$record) }}" poster="{{ $videoPoster }}"
src="{{ $video->publicUrl() ?? route('public.media',[$record,$video]) }}" @auth data-progress-url="{{ route('public.record-state',$record) }}" data-resume="{{ $states['progress']['position']??0 }}" @endauth>
</video>
</div>
@else<audio class="public-main-audio" @if(($record->metadata['public_section']??'')==='podcast') data-audio-view-url="{{ route('public.audio-play',$record) }}" @endif controls preload="metadata" src="{{ $audio->publicUrl() ?? route('public.media',[$record,$audio]) }}" @auth data-progress-url="{{ route('public.record-state',$record) }}" data-resume="{{ $states['progress']['position']??0 }}" @endauth>
</audio>
@endif

@else
@include('public.empty',['emptyClass'=>'public-empty-player','hint'=>__('public.no_local_playback')])
@endif
