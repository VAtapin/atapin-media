<!doctype html><html lang="{{ app()->getLocale() }}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ __('ui.login') }} · {{ config('platform.brand') }}</title><link rel="icon" href="/favicon.svg"><link rel="stylesheet" href="/assets/fonts/fonts.css"><link rel="stylesheet" href="/assets/app.css"></head>
<body class="login-page"><main class="login-card"><img src="/favicon.svg" width="68" height="68" alt=""><p class="eyebrow">{{ config('platform.brand') }}</p><h1>{{ __('ui.login') }}</h1><p class="muted">{{ __('ui.login_intro') }}</p>
@if($errors->any())<div class="notice error" role="alert">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('login') }}">@csrf
<label>{{ __('ui.email') }}<input type="email" name="email" value="{{ old('email') }}" required autocomplete="username" autofocus></label>
<label>{{ __('ui.password') }}<input type="password" name="password" required maxlength="72" autocomplete="current-password"></label>
<label class="checkbox"><input type="checkbox" name="remember" value="1">{{ __('ui.remember') }}</label>
<button class="button full">{{ __('ui.login') }}</button></form><a class="back-link" href="/">{{ __('ui.public_website') }}</a></main></body></html>
