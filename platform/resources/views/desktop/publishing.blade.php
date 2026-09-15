<section class="desktop-publishing" data-publishing data-api-url="{{ route('desktop.publishing.index') }}">
    <header class="desktop-publishing-head">
        <div><p class="desktop-publishing-eyebrow">{{ __('publishing.eyebrow') }}</p><h1>{{ __('publishing.title') }}</h1><p>{{ __('publishing.intro') }}</p></div>
        <div class="desktop-publishing-actions"><button type="button" class="desktop-button" data-publishing-refresh>{{ __('publishing.refresh') }}</button></div>
    </header>
    <div class="desktop-publishing-feedback" data-publishing-feedback hidden role="status"></div>
    <section class="desktop-publishing-filters" aria-label="{{ __('publishing.filters') }}">
        <label><span>{{ __('publishing.from') }}</span><input type="date" data-publishing-filter="from"></label>
        <label><span>{{ __('publishing.to') }}</span><input type="date" data-publishing-filter="to"></label>
        <label><span>{{ __('publishing.provider') }}</span><select data-publishing-filter="provider"><option value="">{{ __('publishing.all_providers') }}</option></select></label>
        <label><span>{{ __('publishing.status_filter') }}</span><select data-publishing-filter="status"><option value="">{{ __('publishing.all_statuses') }}</option><option value="published">{{ __('publishing.published') }}</option><option value="hidden">{{ __('publishing.hidden') }}</option><option value="queued">{{ __('publishing.queued') }}</option><option value="processing">{{ __('publishing.processing') }}</option><option value="failed">{{ __('publishing.failed') }}</option><option value="deleted">{{ __('publishing.deleted') }}</option></select></label>
        <label class="desktop-publishing-filter-search"><span>{{ __('publishing.search') }}</span><input type="search" data-publishing-filter="search" placeholder="{{ __('publishing.search_placeholder') }}"></label>
        <button type="button" class="desktop-button" data-publishing-reset>{{ __('publishing.reset_filters') }}</button>
    </section>
    <section class="desktop-publishing-summary" data-publishing-summary aria-label="{{ __('publishing.summary') }}"></section>
    <div class="desktop-publishing-viewbar"><h2>{{ __('publishing.external_title') }}</h2><div class="desktop-publishing-view-toggle" role="group" aria-label="{{ __('publishing.view') }}"><button type="button" class="desktop-button" data-publishing-view="cards">{{ __('publishing.cards') }}</button><button type="button" class="desktop-button" data-publishing-view="list">{{ __('publishing.list') }}</button></div></div>
    <section class="desktop-publishing-status-list" data-publishing-status-list aria-live="polite"></section>
</section>
