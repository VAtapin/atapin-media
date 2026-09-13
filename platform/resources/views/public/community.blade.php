@extends('public.layout',['title'=>__('public.section_community')])

@section('content')

@include('public.hero')<div class="public-wide">
<nav class="public-community-navigation">
@foreach(['discussions','questions','polls','guidelines','join'] as $anchor)<a class="public-panel" href="{{ in_array($anchor,['guidelines','join'])?($anchor==='join'?'/login':'/unsere-mission'):'#'.$anchor }}">
@include('public.icon',['name'=>'community'])<strong>{{ __('public.community_'.$anchor) }}</strong> →</a>
@endforeach</nav>
@include('public.filters')<div class="public-community-grid">
@include('public.topics')<section class="public-panel" id="discussions">
<div class="public-panel-heading">
<h2 id="questions">{{ __('public.latest_questions') }}</h2>
<a href="/community">{{ __('public.show_all') }} →</a>
</div>
@forelse($items as $item)<article class="public-community-entry">
<span class="public-avatar">{{ mb_substr($item['author'],0,1)?:'◇' }}</span>
<div>
<h3>
<a href="{{ $item['url'] }}">{{ $item['title'] }}</a>
</h3>
<small>{{ $item['author']?:'—' }} · {{ $item['meta']?:'—' }}</small>
@if($record?->id===$item['id'])<div class="public-document">{{ $record->body }}</div>
@if($record->kind==='post')
@include('public.comments')
@endif
@endif</div>
</article>
@empty
@include('public.empty')
@endforelse
@include('public.pagination')<div id="polls">
@include('public.poll')</div>
</section>
<section class="public-panel">
<div class="public-panel-heading">
<h2>{{ __('public.community_activity') }}</h2>
<small>◇ {{ __('public.online') }}: {{ __('public.no_data') }}</small>
</div>
@forelse($comments as $comment)<p class="public-activity-entry">{{ is_string($comment->metadata['author']??null)?$comment->metadata['author']:'—' }}: {{ \Illuminate\Support\Str::limit($comment->body,100) }}</p>
@empty
@include('public.empty')
@endforelse</section>
<aside class="public-right-sidebar">
<section class="public-panel">
<div class="public-panel-heading">
<h2>{{ __('public.top_members') }}</h2>
</div>@php($authors=$items->filter(fn($item)=>$item['author']!=='')->groupBy('author'))
@forelse($authors as $author=>$entries)<p class="public-member-row">
<span class="public-avatar">{{ mb_substr($author,0,1) }}</span>
<strong>{{ $author }}</strong>
</p>
@empty
@include('public.empty')
@endforelse</section>
@include('public.quote')</aside>
</div>
@include('public.newsletter')</div>

@endsection
