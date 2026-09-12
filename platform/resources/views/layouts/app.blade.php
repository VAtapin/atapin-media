<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('ui.desktop')) · {{ config('platform.brand') }}</title>
    <link rel="icon" href="/favicon.png"><link rel="stylesheet" href="/assets/fonts/fonts.css"><link rel="stylesheet" href="/assets/brand/ui-kit.css?v=owner-1"><link rel="stylesheet" href="/assets/app.css">
</head>
<body class="module-page">
<a class="skip" href="#main">{{ __('ui.skip') }}</a>
<main id="main" class="main">
    @if(session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="notice error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
</body></html>