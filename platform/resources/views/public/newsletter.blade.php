<section class="public-newsletter {{ $compact??false?'public-newsletter-compact':'' }}" id="newsletter">
@include('public.icon',['name'=>'newsletter'])<div>
<h2>{{ __('public.newsletter_title') }}</h2>
<p>{{ __('public.newsletter_consent') }}</p>
</div>
<form method="post" action="{{ route('public.newsletter') }}">@csrf
<label class="public-sr-only" for="newsletter-{{ $section??'start' }}">{{ __('ui.email') }}</label>
<input id="newsletter-{{ $section??'start' }}" name="email" type="email" required placeholder="{{ __('public.email_placeholder') }}">
<label><input type="checkbox" name="consent" value="1" required>{{ __('public.newsletter_consent') }}</label>
<input name="website" tabindex="-1" autocomplete="off" hidden>
<button class="public-button">{{ __('public.subscribe') }} →</button>
</form>
</section>
