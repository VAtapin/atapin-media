<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ app(\App\Services\Settings::class)->get('site_name', config('platform.brand')) }}</title>
    <link rel="icon" href="/favicon.png">
    <link rel="stylesheet" href="/assets/fonts/fonts.css">
    <link rel="stylesheet" href="/assets/brand/ui-kit.css?v=owner-1">
    <link rel="stylesheet" href="/assets/site-notice.css">
</head>
<body class="site-notice">
    <header class="site-notice-brand">@include('components.brand')</header>
    <main class="site-notice-message">
        <span class="site-notice-rule" aria-hidden="true"></span>
        <h1>{{ __('ui.site_preparing_title') }}</h1>
        <p>{{ __('ui.site_preparing_message') }}</p>
    </main>
</body>
</html>
