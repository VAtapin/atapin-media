<section class="desktop-publishing" data-publishing data-api-url="{{ route('desktop.publishing.index') }}" data-publish-url="{{ route('desktop.publishing.publish') }}" data-sync-url="{{ route('desktop.publishing.sync') }}" data-youtube-connect-url="{{ route('desktop.publishing.youtube.connect') }}">
    <header class="desktop-publishing-head">
        <div><p class="desktop-publishing-eyebrow">{{ __('publishing.eyebrow') }}</p><h1>{{ __('publishing.title') }}</h1><p>{{ __('publishing.intro') }}</p></div>
        <div class="desktop-publishing-actions"><button type="button" class="desktop-button" data-publishing-sync>{{ __('publishing.sync') }}</button>@can('integrations.manage')<a class="desktop-button is-primary" data-youtube-connect target="_self" href="{{ route('desktop.publishing.youtube.connect') }}">{{ __('publishing.connect_youtube') }}</a><a class="desktop-button" href="{{ route('desktop.publishing.x.connect') }}">{{ __('publishing.connect_x') }}</a>@endcan</div>
    </header>
    <div class="desktop-publishing-feedback" data-publishing-feedback hidden role="status"></div>
    <aside class="desktop-publishing-explainer">
        <h2>{{ __('publishing.explanation_title') }}</h2>
        <p>{{ __('publishing.explanation_website') }}</p>
        <p>{{ __('publishing.explanation_channels') }}</p>
        <p>{{ __('publishing.explanation_status') }}</p>
    </aside>
    <div class="desktop-publishing-grid">
        <section class="desktop-publishing-card"><div class="desktop-publishing-card-head"><h2>{{ __('publishing.choose_content') }}</h2><span data-publishing-connection-status></span></div><label class="desktop-publishing-select"><span>{{ __('publishing.content') }}</span><select data-publishing-record></select></label><p class="desktop-publishing-muted" data-publishing-empty hidden>{{ __('publishing.no_content') }}</p></section>
        <section class="desktop-publishing-card"><div class="desktop-publishing-card-head"><h2>{{ __('publishing.destinations') }}</h2><span>{{ __('publishing.destinations_hint') }}</span></div><div class="desktop-publishing-destinations" data-publishing-destinations></div><label class="desktop-publishing-destination"><input type="checkbox" data-remove-on-unpublish><span>{{ __('publishing.remove_on_unpublish') }}</span></label><button type="button" class="desktop-button is-primary" data-publishing-submit>{{ __('publishing.publish_now') }}</button></section>
    </div>
    @can('integrations.manage')
    <section class="desktop-publishing-card">
        <h2>{{ __('publishing.live_outputs') }}</h2><p class="desktop-publishing-muted">{{ __('publishing.live_outputs_hint') }}</p>
        <form data-live-output-form class="desktop-publishing-actions">
            <label>{{ __('publishing.output_id') }}<input name="id" required pattern="rtmp_[a-z0-9_]{1,27}" placeholder="rtmp_facebook"></label>
            <label>{{ __('publishing.output_label') }}<input name="label" required maxlength="100"></label>
            <label>{{ __('publishing.output_url') }}<input name="url" type="password" required autocomplete="off" spellcheck="false"></label>
            <button class="desktop-button" type="submit">{{ __('publishing.save_output') }}</button>
        </form><div data-live-output-list></div>
    </section>
    @endcan
    <section class="desktop-publishing-card desktop-publishing-status-card"><div class="desktop-publishing-card-head"><h2>{{ __('publishing.status_title') }}</h2><span>{{ __('publishing.status_hint') }}</span></div><div class="desktop-publishing-status-list" data-publishing-status-list></div></section>
</section>
