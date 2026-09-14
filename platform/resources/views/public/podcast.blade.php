@extends('public.layout',['title'=>__('public.section_podcast')])

@section('content')

@include('public.hero')<div class="public-wide">
<nav class="public-podcast-navigation">
@foreach(['catalog','series','topics','guests','everyday'] as $anchor)<a class="public-panel" href="#{{ $anchor }}">
@include('public.icon',['name'=>'podcast'])<span>
<strong>{{ __('public.podcast_'.$anchor) }}</strong>
<small>{{ __('public.podcast_hint_'.$anchor) }}</small>
</span> →</a>
@endforeach<a class="public-panel" href="#catalog">{{ __('ui.search') }} →</a>
</nav>
@include('public.filters')<div class="public-podcast-grid">
<section class="public-panel">
<div class="public-panel-heading">
<h2>{{ __('public.latest_episodes') }}</h2>
<a href="/podcast">{{ __('public.show_all') }} →</a>
</div>
<div class="public-cards public-related">
@forelse($items as $item)
@include('public.card')
@empty
@include('public.empty')
@endforelse</div>
@include('public.pagination')
@if($record)<h3>{{ $record->title }}</h3>
<p>{{ $record->metadata['short_description']??'' }}</p><details class="public-full-description"><summary>{{ __('public.show_more') }}</summary><div class="public-document">{{ $record->body }}</div></details>
@include('public.player',['card'=>$featured])<div class="public-action-row">
@include('public.state-button',['subject'=>$record,'action'=>'bookmark','label'=>__('public.bookmark')])</div>
@include('public.comments')
@endif</section>
@include('public.series')<section class="public-panel" id="topics">
<div class="public-panel-heading">
<h2>{{ __('public.topic_categories') }}</h2>
<a href="#catalog">{{ __('public.show_all') }} →</a>
</div>
<div class="public-topic-tiles">
@forelse($topics as $tag=>$count)<a href="{{ route('public.podcast',['tag'=>$tag]) }}">
@include('public.icon',['name'=>'book'])<strong>{{ $tag }}</strong>
<small>{{ $count }} {{ __('public.episodes') }}</small>
</a>
@empty
@include('public.empty')
@endforelse</div>
<h3 id="guests">{{ __('public.podcast_guests') }}</h3>
@if($featured&&$featured['author'])<p>{{ $featured['author'] }}</p>
@else
@include('public.empty')
@endif</section>
<aside class="public-right-sidebar">
<section class="public-panel">
<div class="public-panel-heading">
<h2>{{ __('public.continue_listening') }}</h2>
</div>
@if($resume)
@include('public.card',['item'=>$resume])<p>{{ gmdate('i:s',(int)$resume['position']) }}</p>
<a href="{{ $resume['url'] }}">{{ __('public.continue') }} →</a>
@else
@include('public.empty')
@endif</section>
@include('public.cards',['heading'=>__('public.recommendation'),'cards'=>$popular->take(1),'panelClass'=>'public-related'])</aside>
</div>
<div class="public-podcast-bottom">
<section class="public-panel" id="everyday">
<h2>{{ __('public.listen_everywhere') }}</h2>
<p>{{ __('public.local_audio_hint') }}</p>
<a class="public-button public-button-secondary" href="#catalog">{{ __('public.discover_podcast') }} →</a>
</section>
@include('public.newsletter',['compact'=>true])</div>
</div>

@endsection
