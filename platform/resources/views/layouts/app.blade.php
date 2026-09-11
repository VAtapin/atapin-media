<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('ui.desktop')) · {{ config('platform.brand') }}</title>
    <link rel="icon" href="/favicon.svg"><link rel="stylesheet" href="/assets/fonts/fonts.css">
    <link rel="stylesheet" href="/assets/app.css"><script src="/assets/app.js" defer></script>
</head>
<body class="workspace">
<a class="skip" href="#main">{{ __('ui.skip') }}</a>
<aside class="sidebar" id="sidebar">
    <a class="brand" href="{{ route('desktop') }}"><img src="/favicon.svg" width="38" height="38" alt=""><span>{{ config('platform.brand') }}<small>Media Desktop</small></span></a>
    <nav aria-label="{{ __('ui.navigation') }}">
        <p class="nav-heading">{{ __('ui.workspace') }}</p>
        @can('desktop.view')<a @class(['nav-link','active'=>request()->routeIs('desktop')]) href="{{ route('desktop') }}">@include('components.icon',['name'=>'desktop']) {{ __('ui.desktop') }}</a>@endcan
        <p class="nav-heading">{{ __('ui.contents') }}</p>
        @can('media.view')<a @class(['nav-link','active'=>request()->routeIs('media.*')]) href="{{ route('media.index') }}">@include('components.icon',['name'=>'media']) {{ __('ui.media_library') }}</a>@endcan
        @can('media.upload')<a class="nav-link" href="/upload/">@include('components.icon',['name'=>'upload']) {{ __('ui.upload_files') }}</a>@endcan
        <p class="nav-heading">{{ __('ui.more') }}</p>
        @can('settings.manage')<a @class(['nav-link','active'=>request()->routeIs('settings')]) href="{{ route('settings') }}">@include('components.icon',['name'=>'settings']) {{ __('ui.settings') }}</a>@endcan
        @can('audit.view')<a @class(['nav-link','active'=>request()->routeIs('audit')]) href="{{ route('audit') }}">@include('components.icon',['name'=>'list']) {{ __('ui.audit') }}</a>@endcan
    </nav>
    <div class="sidebar-footer"><a href="/">{{ __('ui.public_website') }} ↗</a><span>{{ __('ui.private_workspace') }}</span></div>
</aside>
<div class="workspace-body">
    <header class="topbar"><button class="icon-button menu-toggle" aria-controls="sidebar" aria-expanded="false" aria-label="{{ __('ui.menu') }}">☰</button>
        <span class="breadcrumb">{{ __('ui.workspace') }} <span>/</span> @yield('title', __('ui.desktop'))</span>
        <div class="account"><span class="avatar">{{ mb_substr(auth()->user()->name,0,1) }}</span><span>{{ auth()->user()->name }}</span>
        <form method="post" action="{{ route('logout') }}">@csrf<button class="text-button">{{ __('ui.logout') }}</button></form></div>
    </header>
    <main id="main" class="main">
        @if(session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="notice error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('content')
    </main>
</div>
</body></html>
