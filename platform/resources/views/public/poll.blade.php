@php($participation=app(\App\Services\PublicParticipation::class))
<section class="public-poll">
<p class="public-eyebrow">{{ __('public.current_poll') }}</p>
@if($poll)<h2>{{ $poll->title }}</h2>@php($options=$participation->options($poll))@php($showResults=app(\App\Services\Polls::class)->results($poll,auth()->user()))@php($total=$participation->states($poll)->where('action','vote')->count())<form action="{{ route('public.record-state',$poll) }}" method="post" data-public-form>@csrf<input type="hidden" name="action" value="vote">
@foreach($options as $index=>$option)@php($count=$participation->states($poll)->where('action','vote')->where(fn($q)=>$q->where('value->option',$index)->orWhereJsonContains('value->options',$index))->count())<label>
<input type="{{ ($poll->metadata['poll']['multiple']??false)?'checkbox':'radio' }}" name="{{ ($poll->metadata['poll']['multiple']??false)?'options[]':'option' }}" value="{{ $index }}" @required(!($poll->metadata['poll']['multiple']??false))>
<span>{{ $option['text'] }}</span>
@if($showResults)<meter min="0" max="{{ max(1,$total) }}" value="{{ $count }}">
</meter>
<small>{{ $total?round($count/$total*100):0 }}%</small>@endif
</label>
@endforeach @if($showResults)<p>{{ $total }} {{ __('public.votes') }}</p>@endif
<button class="public-button" @disabled(!$options||!app(\App\Services\Polls::class)->open($poll))>{{ __('public.vote') }} →</button>@unless(app(\App\Services\Polls::class)->open($poll))<p>{{ __('public.poll_closed') }}</p>@endunless
</form>
@else
@include('public.empty')<button class="public-button" disabled>{{ __('public.vote') }} →</button>
@endif</section>
