<section class="desktop-publishing" data-publishing data-api-url="{{ route('desktop.publishing.index') }}" data-publish-url="{{ route('desktop.publishing.publish') }}" data-sync-url="{{ route('desktop.publishing.sync') }}" data-youtube-connect-url="{{ route('desktop.publishing.youtube.connect') }}">
    <header class="desktop-publishing-head">
        <div><p class="desktop-publishing-eyebrow">{{ __('publishing.eyebrow') }}</p><h1>{{ __('publishing.title') }}</h1><p>{{ __('publishing.intro') }}</p></div>
        <div class="desktop-publishing-actions"><button type="button" class="desktop-button" data-publishing-sync>{{ __('publishing.sync') }}</button><a class="desktop-button is-primary" data-youtube-connect target="_self" href="{{ route('desktop.publishing.youtube.connect') }}">{{ __('publishing.connect_youtube') }}</a></div>
    </header>
    <div class="desktop-publishing-feedback" data-publishing-feedback hidden role="status"></div>
    <div class="desktop-publishing-grid">
        <section class="desktop-publishing-card"><div class="desktop-publishing-card-head"><h2>{{ __('publishing.choose_content') }}</h2><span data-publishing-connection-status></span></div><label class="desktop-publishing-select"><span>{{ __('publishing.content') }}</span><select data-publishing-record></select></label><p class="desktop-publishing-muted" data-publishing-empty hidden>{{ __('publishing.no_content') }}</p></section>
        <section class="desktop-publishing-card"><div class="desktop-publishing-card-head"><h2>{{ __('publishing.destinations') }}</h2><span>{{ __('publishing.destinations_hint') }}</span></div><div class="desktop-publishing-destinations" data-publishing-destinations></div><button type="button" class="desktop-button is-primary" data-publishing-submit>{{ __('publishing.publish_now') }}</button></section>
    </div>
    <section class="desktop-publishing-card desktop-publishing-status-card"><div class="desktop-publishing-card-head"><h2>{{ __('publishing.status_title') }}</h2><span>{{ __('publishing.status_hint') }}</span></div><div class="desktop-publishing-status-list" data-publishing-status-list></div></section>
</section>
