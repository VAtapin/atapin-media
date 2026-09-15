<section class="desktop-settings-panel" data-settings-panel="media_appearance" hidden>
    <header class="desktop-settings-heading"><div><span>Website</span><h2>{{ __('ui.settings_website_author') }}</h2><p>{{ __('ui.settings_website_author_hint') }}</p></div></header>
    <form class="desktop-settings-form" method="post" enctype="multipart/form-data" action="{{ route('settings') }}">@csrf @method('PUT')<input type="hidden" name="section" value="media_appearance">
        <label>{{ __('imports.project_author') }}<input name="public_author_name" maxlength="120" value="{{ $settings['public_author_name']??'' }}"></label>
        <label>{{ __('imports.author_photo') }}<input type="file" name="public_author_photo" accept="image/jpeg,image/png,image/webp"></label>
        @if($settings['public_author_image']??null)<img src="{{ $settings['public_author_image'] }}" alt="" width="64" height="64">@endif
        <details><summary>{{ __('imports.hero_sayings') }}</summary>
            @foreach(config('platform.locales') as $locale)
            <h3>{{ strtoupper($locale) }}</h3>
            @foreach(['start','videos','beitraege','buecher','podcast','live','community','ueber-uns','unsere-mission'] as $heroSection)
            <label>{{ __($heroSection==='start'?'public.nav_start':'public.heading_'.$heroSection,[], $locale) }}<textarea name="hero_sayings[{{ $locale }}][{{ $heroSection }}]" maxlength="500" rows="2">{{ $settings['hero_sayings'][$locale][$heroSection]??__($heroSection==='start'?'public.hero_side_quote':'public.hero_side_quote_'.$heroSection,[],$locale) }}</textarea></label>
            @endforeach
            @endforeach
        </details>
        <button class="desktop-settings-primary" data-settings-save>{{ __('ui.save') }}</button>
    </form>
</section>
