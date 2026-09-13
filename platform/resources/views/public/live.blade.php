@extends('public.layout',['title'=>__('public.section_live')])

@section('content')
@php($card=$featured)<div class="public-live-top">
<div>
@include('public.player')<div class="public-title-actions">
<div>
<h1>{{ $record?->title??'—' }}</h1>
<p class="public-record-meta">{{ $card['author']??__('public.no_data') }} · {{ $record?->metadata['starts_at']??'—' }}</p>
<p class="public-live-status">{{ __('public.live_status') }}: {{ in_array($record?->metadata['live_status']??null,['live','scheduled','ended'])?__('public.live_'.$record->metadata['live_status']):__('public.no_data') }}</p>
</div>
<div class="public-action-row">
@include('public.state-button',['subject'=>$record,'action'=>'reminder','label'=>__('public.reminder')])</div>
@include('public.push-button')
</div>
</div>
<section class="public-panel public-live-chat" id="chat" @if($record) data-live-heartbeat="{{ route('public.live-heartbeat',$record) }}" @endif>
<div class="public-panel-heading">
<h2>{{ __('public.live_chat') }}</h2>
<small>{{ __('public.online') }}: <span data-live-online>◇ {{ __('public.no_data') }}</span></small>
</div>
<div class="public-chat-messages">
@forelse($chat as $message)<article>
<span class="public-avatar">{{ mb_substr(is_string($message->metadata['author']??null)?$message->metadata['author']:'',0,1)?:'◇' }}</span>
<div>
<strong>{{ is_string($message->metadata['author']??null)?$message->metadata['author']:'—' }}</strong>
<small>{{ $message->created_at->format('H:i') }}</small>
<p>{{ $message->body }}</p>
</div>
</article>
@empty
@include('public.empty')
@endforelse</div>
@if($record)<form method="post" action="{{ route('public.record-state',$record) }}">@csrf<input type="hidden" name="action" value="chat">
<label class="public-sr-only" for="chat-body">{{ __('public.write_message') }}</label>
<input id="chat-body" name="body" required minlength="2" maxlength="5000" placeholder="{{ __('public.write_message') }}">
<button class="public-button">{{ __('public.send') }} →</button>
</form>
<small>{{ __('public.moderation_hint') }}</small>
@auth
@if(app(\App\Services\PublicAiChat::class)->available())
<form method="post" action="{{ route('public.ai-chat',$record) }}" data-ai-form>@csrf
<label for="ai-question">{{ __('public.ai_chat_label') }}</label>
<input id="ai-question" name="question" required minlength="2" maxlength="1000" placeholder="@Assistent …">
<label><input type="checkbox" name="consent" value="1" required>{{ __('public.ai_chat_consent') }}</label>
<button class="public-button">{{ __('public.ai_chat_ask') }}</button>
<p data-ai-answer role="status"></p>
</form>
@endif
@endauth
@else<input placeholder="{{ __('public.write_message') }}" disabled>
@endif<div class="public-action-row">
<button class="public-button public-button-secondary" data-share>{{ __('public.share') }}</button>
@include('public.state-button',['subject'=>$record,'action'=>'like','label'=>__('public.like')])
@include('public.state-button',['subject'=>$record,'action'=>'bookmark','label'=>__('public.bookmark')])</div>
</section>
</div>
<div class="public-wide">
<div class="public-live-bottom">
@include('public.cards',['heading'=>__('public.next_live'),'cards'=>$upcoming,'panelClass'=>'public-related','listingUrl'=>'/live'])<section class="public-panel">
<div class="public-panel-heading">
<h2>{{ __('public.schedule') }}</h2>
<a href="/live">{{ __('public.show_all') }} →</a>
</div>
@forelse($upcoming as $event)<a class="public-schedule-row" href="{{ $event['url'] }}">{{ $event['meta']?:'—' }} <strong>{{ $event['title'] }}</strong> →</a>
@empty
@include('public.empty')
@endforelse</section>
<section class="public-panel">
@include('public.poll')</section>
@include('public.cards',['heading'=>__('public.recordings'),'cards'=>$popular,'panelClass'=>'public-related','listingUrl'=>'/live?sort=oldest'])</div>
<div class="public-live-footer">
<section class="public-panel">
<h2>{{ __('public.community_feedback') }}</h2>
@include('public.empty')</section>
@include('public.newsletter',['compact'=>true])</div>
</div>

@endsection
