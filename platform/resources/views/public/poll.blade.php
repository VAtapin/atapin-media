@php($participation=app(\App\Services\PublicParticipation::class))
<section class="public-poll">
<p class="public-eyebrow">{{ __('public.current_poll') }}</p>
@if($poll)<h2>{{ $poll->title }}</h2>@php($options=$participation->options($poll))@php($total=$participation->states($poll)->where('action','vote')->count())<form action="{{ route('public.record-state',$poll) }}" method="post" data-public-form>@csrf<input type="hidden" name="action" value="vote">
@foreach($options as $index=>$option)@php($count=$participation->states($poll)->where('action','vote')->where('value->option',$index)->count())<label>
<input type="radio" name="option" value="{{ $index }}" required>
<span>{{ $option['text'] }}</span>
<meter min="0" max="{{ max(1,$total) }}" value="{{ $count }}">
</meter>
<small>{{ $total?round($count/$total*100):0 }}%</small>
</label>
@endforeach<p>{{ $total }} {{ __('public.votes') }}</p>
<button class="public-button" @disabled(!$options)>{{ __('public.vote') }} →</button>
</form>
@else
@include('public.empty')<button class="public-button" disabled>{{ __('public.vote') }} →</button>
@endif</section>
