@extends('public.layout',['title'=>$record->title])
@section('content')
<a href="{{ $section==='videos'?'/videos':'/beitraege' }}">← {{ __('public.section_'.$section) }}</a>
<article class="public-panel public-detail"><h1>{{ $record->title }}</h1>
@foreach($assets as $asset)
@if($asset->kind==='video')<video controls preload="metadata" @if($card['image'])poster="{{ $card['image'] }}"@endif src="{{ route('public.media',[$record,$asset]) }}"></video>
@elseif($asset->kind==='image')<img src="{{ route('public.media',[$record,$asset]) }}" alt="" loading="lazy">
@elseif($asset->kind==='audio')<audio controls preload="metadata" src="{{ route('public.media',[$record,$asset]) }}"></audio>@endif
@endforeach
<div class="public-document">{{ $record->body }}</div></article>
@endsection
