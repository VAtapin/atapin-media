@php($participation=app(\App\Services\PublicParticipation::class))
<section class="public-poll">
<p class="public-eyebrow">{{ __('public.current_poll') }}</p>
@if($poll)<h2>{{ $poll->title }}</h2>
@if(!empty($poll->metadata['poll']['external_url']))
@php($externalUrl=$poll->metadata['poll']['external_url'])
@if(app(\App\Services\ExternalPollUrl::class)->valid($externalUrl) && app(\App\Services\Polls::class)->open($poll))
@if(($poll->metadata['poll']['external_display']??'link')==='iframe' && app(\App\Services\ExternalPollUrl::class)->embeddable($externalUrl))
<iframe src="{{ $externalUrl }}" title="{{ $poll->title }}" sandbox="allow-scripts allow-forms allow-same-origin" referrerpolicy="no-referrer" loading="lazy" style="width:100%;height:480px;border:0"></iframe>
@endif
<a class="public-button" href="{{ $externalUrl }}" target="_blank" rel="noopener noreferrer">{{ __('public.vote') }} →</a>
@else <p>{{ __('public.poll_closed') }}</p> @endif
@else
@php($options=$participation->options($poll))@php($showResults=app(\App\Services\Polls::class)->results($poll,auth()->user()))@php($tally=$showResults?$participation->voteResults($poll):['votes'=>0,'results'=>[]])@php($total=$tally['votes'])<form action="{{ route('public.record-state',$poll) }}" method="post" data-public-form>@csrf<input type="hidden" name="action" value="vote">
@foreach($options as $index=>$option)@php($count=$tally['results'][$index]['count']??0)<label>
<input type="{{ ($poll->metadata['poll']['multiple']??false)?'checkbox':'radio' }}" name="{{ ($poll->metadata['poll']['multiple']??false)?'options[]':'option' }}" value="{{ $index }}" @required(!($poll->metadata['poll']['multiple']??false))>
<span>{{ $option['text'] }}</span>
@if($showResults)<meter min="0" max="{{ max(1,$total) }}" value="{{ $count }}">
</meter>
<small>{{ $total?round($count/$total*100):0 }}%</small>@endif
</label>
@endforeach @if($showResults)<p>{{ $total }} {{ __('public.votes') }}</p>@endif
<button class="public-button" @disabled(!$options||!app(\App\Services\Polls::class)->open($poll))>{{ __('public.vote') }} →</button>@unless(app(\App\Services\Polls::class)->open($poll))<p>{{ __('public.poll_closed') }}</p>@endunless
</form>
@endif
@else
@include('public.empty')<button class="public-button" disabled>{{ __('public.vote') }} →</button>
@endif</section>
