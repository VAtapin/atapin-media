<header class="public-header">
    <div class="public-header-inner">
        <a class="public-brand-link" href="/">@include('public.brand')</a>
        <button class="public-menu-button" type="button" aria-controls="public-navigation" aria-expanded="false" aria-label="{{ __('ui.menu') }}">☰</button>
        <nav id="public-navigation" class="public-navigation" aria-label="{{ __('ui.navigation') }}">
            @foreach(config('public_ui.navigation') as $key=>$item)
                <a href="{{ $item['path'] }}" @class(['current'=>($section??'start')===$key]) @if(($section??'start')===$key)aria-current="page"@endif>{{ __('public.nav_'.$key) }}</a>
            @endforeach
        </nav>
        <form class="public-search" method="get" action="/suche" role="search">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10" cy="10" r="6.5"/><path d="m15 15 5 5"/></svg>
            <label class="public-sr-only" for="public-q">{{ __('ui.search') }}</label><input id="public-q" name="q" type="search" placeholder="{{ __('public.search_placeholder') }}" value="{{ request('q') }}">
        </form>
        <a class="public-signin" href="/login">{{ __('ui.login') }}</a>
        <a class="public-button public-join" href="/community">@include('public.icon',['name'=>'community']) {{ __('public.join') }}</a>
    </div>
</header>
