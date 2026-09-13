@extends('public.layout',['title'=>__('public.section_live'),'layoutMode'=>$layoutMode??'overview'])

@section('content')
@php($card=$featured)@php($liveStatus=$record?->metadata['live_status']??null)@php($liveDate=$record?->metadata['starts_at']?\Illuminate\Support\Carbon::parse($record->metadata['starts_at'])->timezone(config('app.timezone'))->format('d.m.Y H:i'):null)@php($liveStatusLabel=$liveStatus?__('public.live_'.$liveStatus):null)@php($viewer=auth()->user())@php($sessionId=request()->hasSession()?request()->session()->getId():null)
@if(($layoutMode??'overview')==='overview')
@include('public.hero',['section'=>'live'])
@endif
@if(($layoutMode??'overview')==='detail')
<div class="public-live-top">
<div>
@include('public.player')
<div class="public-live-summary">
<h1>{{ $record?->title??'—' }}</h1>
@if($liveDate)<time class="public-record-meta" datetime="{{ $record->metadata['starts_at'] }}">{{ $liveDate }}</time>@endif
@if($record?->body)<p class="public-live-description">{{ $record->body }}</p>@endif
@if($liveStatusLabel)<p class="public-live-status"><span class="public-live-status-dot status-{{ $liveStatus }}" aria-hidden="true"></span><span>{{ $liveStatusLabel }}</span></p>@endif
@if($record)<div class="public-live-actions public-live-detail-actions"><div class="public-action-row">
@include('public.state-button',['subject'=>$record,'action'=>'reminder','label'=>__('public.reminder')])</div>
@include('public.push-button')
</div>@endif
</div>
</div>
<section class="public-panel public-live-chat" id="chat" @if($record) data-live-heartbeat="{{ route('public.live-heartbeat',$record) }}" @endif>
<div class="public-panel-heading">
<h2>{{ __('public.live_chat') }}</h2>
<small>{{ __('public.online') }}: <span data-live-online>◇ {{ __('public.no_data') }}</span></small>
</div>
<div class="public-chat-messages">
@forelse($chat as $message)@php($messageOwn=app(\App\Services\PublicContent::class)->isOwnedByViewer($message,$viewer,$sessionId))@php($messageModeration=$message->metadata['moderation']??[])<article @class(['public-chat-message'=>$messageOwn,'is-blocked'=>$messageOwn&&($messageModeration['blocked']??false),'is-pending'=>$messageOwn&&($messageModeration['state']??null)==='pending_ai'])>
<span class="public-avatar">{{ mb_substr(is_string($message->metadata['author']??null)?$message->metadata['author']:'',0,1)?:'◇' }}</span>
<div>
<strong>{{ is_string($message->metadata['author']??null)?$message->metadata['author']:'—' }}</strong>
<small>{{ $message->created_at->format('H:i') }}</small>
<p>{{ $message->body }}</p>@if($messageOwn&&($messageModeration['blocked']??false))<span class="public-chat-status public-chat-status-blocked">{{ __('public.chat_blocked') }}</span>@elseif($messageOwn&&($messageModeration['state']??null)==='pending_ai')<span class="public-chat-status">{{ __('public.chat_moderation_pending') }}</span>@endif
</div>
</article>
@empty
@include('public.empty')
@endforelse</div>
 @if($record)
 @if($communityBlocked)<div class="public-chat-block-notice" role="alert">{{ __('public.chat_blocked_three') }}</div>@else<form method="post" action="{{ route('public.message-submit',$record) }}">@csrf
 <label class="public-sr-only" for="chat-body">{{ __('public.write_message') }}</label>
 <input id="chat-body" name="body" required minlength="2" maxlength="5000" placeholder="{{ __('public.write_message') }}">
 <button type="submit" class="public-button public-chat-send">{{ __('public.send') }}</button>
 </form><small>{{ __('public.chat_guest_hint') }}</small>@if(session('public_status'))<p class="public-chat-feedback public-feedback" data-auto-dismiss role="status"><span>{{ session('public_status') }}</span><button type="button" class="public-feedback-close" data-dismiss-feedback aria-label="{{ __('public.close') }}" title="{{ __('public.close') }}">×</button></p>@endif @endif
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
@endif
 <div class="public-action-row">
<button type="button" class="public-button public-button-secondary public-icon-button" data-share aria-label="{{ __('public.share') }}" title="{{ __('public.share') }}">@include('public.icon',['name'=>'share'])<span class="public-sr-only">{{ __('public.share') }}</span></button>
@auth
@include('public.state-button',['subject'=>$record,'action'=>'like','label'=>__('public.like'),'iconOnly'=>true])
@include('public.state-button',['subject'=>$record,'action'=>'bookmark','label'=>__('public.bookmark'),'iconOnly'=>true])
@endauth</div>
</section>
</div>
@endif
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
