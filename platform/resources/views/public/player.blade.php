@php($video=$assets->firstWhere('kind','video'))@php($audio=$assets->firstWhere('kind','audio'))

@if($record && ($record->metadata['live_stream_enabled']??false)&&($record->metadata['live_status']??'')==='live')
<iframe class="public-main-player" src="/_live/live-{{ $record->id }}/" title="{{ $record->title }}" allow="autoplay; fullscreen" allowfullscreen></iframe>
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
