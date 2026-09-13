@extends('public.layout')
@section('content')<section class="public-panel"><h1>{{ __('public.account_register') }}</h1><form method="post" action="/registrieren">@csrf
<label>{{ __('ui.name') }}<input name="name" required maxlength="120" value="{{ old('name') }}"></label>
<label>{{ __('ui.email') }}<input name="email" type="email" required value="{{ old('email') }}"></label>
<label>{{ __('ui.password') }}<input name="password" type="password" required minlength="12" maxlength="72" autocomplete="new-password"></label>
<label>{{ __('public.password_confirm') }}<input name="password_confirmation" type="password" required autocomplete="new-password"></label>
<label><input name="consent" type="checkbox" value="1" required>{{ __('public.account_consent') }} <a href="/datenschutz">{{ __('public.privacy') }}</a></label><input name="website" hidden tabindex="-1">
<button class="public-button">{{ __('public.account_register') }}</button></form><a href="/login">{{ __('ui.login') }}</a></section>@endsection
