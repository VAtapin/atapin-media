<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ (($layoutMode??'')==='detail'?(($record??$bookRecord??null)?->metadata['seo_title']??null):null) ?: $title }} · {{ $siteName }}</title>
<meta name="description" content="{{ (($layoutMode??'')==='detail'?(($record??$bookRecord??null)?->metadata['seo_description']??null):null) ?: $description }}">
<link rel="icon" href="/favicon.png">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="apple-touch-icon" href="/assets/brand/owner/app-icon.png">
<meta name="theme-color" content="#8b6a3d">
<link rel="stylesheet" href="/assets/telegram-mini-app.css?v=1">
<script src="/assets/telegram-mini-app.js?v=1" defer></script>
<link rel="stylesheet" href="/assets/fonts/fonts.css">
<link rel="stylesheet" href="/assets/brand/ui-kit.css">
<link rel="stylesheet" href="/assets/public.css">
<link rel="stylesheet" href="/assets/public-pages.css?v=24">
<script src="/assets/public.js?v=15" defer>
</script>
<script src="/assets/public-pwa.js?v=1" defer></script>
<script src="/assets/public-push.js?v=1" defer></script>
<script src="/assets/public-ai-chat.js?v=1" defer></script>
<script src="/assets/public-comments.js?v=1" defer></script>
<script src="/assets/public-audio-analytics.js?v=1" defer></script>
</head>
@php($layoutMode=$layoutMode??'overview')
<body class="public-site public-editorial public-layout-{{ $layoutMode }} public-section-{{ $section ?? 'page' }}" style="--public-live-image:url('{{ $heroImage ?? config('public_ui.hero_image') }}')">@include('public.header',['headerMode'=>$layoutMode,'section'=>$section??'start'])<main class="public-screen" data-public-layout="{{ $layoutMode }}" data-public-section="{{ $section ?? 'page' }}">@if(session('public_status')&&!((($section??null)==='live')&&request()->filled('event')))<p class="public-feedback" data-auto-dismiss role="status"><span>{{ session('public_status') }}</span><button type="button" class="public-feedback-close" data-dismiss-feedback aria-label="{{ __('public.close') }}" title="{{ __('public.close') }}">×</button></p>@endif @if($errors->any())<p class="public-feedback" role="alert">{{ $errors->first() }}</p>@endif @yield('content')</main>@include('public.footer')<p class="public-feedback" data-public-feedback role="status" hidden>
</p>
<script>window.publicLabels=@json(__('public'));</script>
</body>
</html>
