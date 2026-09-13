<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }} · {{ $siteName }}</title>
<meta name="description" content="{{ $description }}">
<link rel="icon" href="/favicon.png">
<link rel="stylesheet" href="/assets/fonts/fonts.css">
<link rel="stylesheet" href="/assets/brand/ui-kit.css">
<link rel="stylesheet" href="/assets/public.css">
<link rel="stylesheet" href="/assets/public-pages.css?v=5">
<script src="/assets/public.js?v=6" defer>
</script>
<script src="/assets/public-push.js?v=1" defer></script>
<script src="/assets/public-ai-chat.js?v=1" defer></script>
</head>
<body class="public-site public-editorial public-section-{{ $section ?? 'page' }}" style="--public-live-image:url('{{ $heroImage ?? config('public_ui.hero_image') }}')">@include('public.header')<main class="public-screen" data-public-section="{{ $section ?? 'page' }}">@if(session('public_status'))<p class="public-feedback" role="status">{{ session('public_status') }}</p>@endif @if($errors->any())<p class="public-feedback" role="alert">{{ $errors->first() }}</p>@endif @yield('content')</main>@include('public.footer')<p class="public-feedback" data-public-feedback role="status" hidden>
</p>
<script>window.publicLabels=@json(__('public'));</script>
</body>
</html>
