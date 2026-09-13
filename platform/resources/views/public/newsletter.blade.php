<section class="public-newsletter {{ $compact??false?'public-newsletter-compact':'' }}" id="newsletter">
@include('public.icon',['name'=>'newsletter'])<div>
<h2>{{ __('public.newsletter_title') }}</h2>
<p>{{ __('public.newsletter_pending') }}</p>
</div>
<form>
<label class="public-sr-only" for="newsletter-{{ $section }}">{{ __('ui.email') }}</label>
<input id="newsletter-{{ $section }}" type="email" placeholder="{{ __('public.email_placeholder') }}" disabled>
<button class="public-button" disabled>{{ __('public.subscribe') }} →</button>
</form>
</section>
