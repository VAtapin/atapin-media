@if($record)
@auth
<button type="button" class="public-button public-button-secondary" data-push-url="{{ route('public.live-push',$record) }}" data-push-key="{{ app(\App\Services\Settings::class)->get('push_public_key','') }}">{{ __('public.push_enable') }}</button>
<small>{{ __('public.push_hint') }}</small>
@else
<a class="public-button public-button-secondary" href="/login">{{ __('public.push_login') }}</a>
@endauth
@endif
