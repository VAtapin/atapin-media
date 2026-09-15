@php($participation=app(\App\Services\PublicParticipation::class))
<section class="public-poll">
<header class="public-poll-header"><p class="public-eyebrow">{{ __('public.current_poll') }}</p>
@if($poll)<h2>{{ $poll->title }}</h2></header>
@if(!empty($poll->metadata['poll']['external_url']))
@php($externalUrl=$poll->metadata['poll']['external_url'])
@if(app(\App\Services\ExternalPollUrl::class)->valid($externalUrl) && app(\App\Services\Polls::class)->open($poll))
@if(($poll->metadata['poll']['external_display']??'link')==='iframe' && app(\App\Services\ExternalPollUrl::class)->embeddable($externalUrl))
<iframe src="{{ $externalUrl }}" title="{{ $poll->title }}" sandbox="allow-scripts allow-forms allow-same-origin" referrerpolicy="no-referrer" loading="lazy" style="width:100%;height:480px;border:0"></iframe>
@endif
<a class="public-button" href="{{ $externalUrl }}" target="_blank" rel="noopener noreferrer">{{ __('public.vote') }} →</a>
@else <p>{{ __('public.poll_closed') }}</p> @endif
@else
@php($polls=app(\App\Services\Polls::class))@php($options=$participation->options($poll))@php($canVote=$polls->allowed($poll,auth()->user()))@php($showResults=$polls->results($poll,auth()->user()))@php($tally=$showResults?$participation->voteResults($poll):['votes'=>0,'results'=>[]])@php($total=$tally['votes'])
<form class="public-poll-form" action="{{ route('public.record-state',$poll) }}" method="post" @if($canVote) data-public-form data-public-poll @endif>@csrf<input type="hidden" name="action" value="vote">
<div class="public-poll-options">@foreach($options as $index=>$option)@php($count=$tally['results'][$index]['count']??0)<label class="public-poll-option">
<input type="{{ ($poll->metadata['poll']['multiple']??false)?'checkbox':'radio' }}" name="{{ ($poll->metadata['poll']['multiple']??false)?'options[]':'option' }}" value="{{ $index }}" @required(!($poll->metadata['poll']['multiple']??false))>
<span class="public-poll-option-copy">{{ $option['text'] }}</span>
@if($showResults)<span class="public-poll-result"><meter min="0" max="{{ max(1,$total) }}" value="{{ $count }}"></meter><small>{{ $total?round($count/$total*100):0 }}%</small></span>@endif
</label>
@endforeach</div>
<footer class="public-poll-footer">@if($showResults)<p class="public-poll-total">{{ $total }} {{ __('public.votes') }}</p>@endif
@guest<a class="public-button" href="/login">{{ __('public.poll_login') }} →</a>@else<button class="public-button" @disabled(!$options||!$polls->open($poll)||!$canVote)>{{ __('public.vote') }} →</button>@endguest</footer>
@unless($polls->open($poll))<p class="public-poll-notice">{{ __('public.poll_closed') }}</p>@endunless
@auth @unless($canVote)<p class="public-poll-notice">{{ __('public.poll_subscriber_required') }}</p>@endunless @endauth
<p data-public-form-message class="public-poll-message" role="status" hidden></p>
</form>
@endif
@else
@include('public.empty')<button class="public-button" disabled>{{ __('public.vote') }} →</button></header>
@endif</section>
