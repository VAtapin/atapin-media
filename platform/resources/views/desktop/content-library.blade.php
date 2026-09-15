<section class="desktop-media-library desktop-content-library" data-content-library data-ai-available="{{ app(\App\Services\ContentShortDescriptions::class)->available() ? 'true' : 'false' }}" data-can-edit="{{ auth()->user()->hasPermission('content.edit') ? 'true' : 'false' }}" data-can-media-edit="{{ auth()->user()->hasPermission('media.edit') ? 'true' : 'false' }}" data-can-upload="{{ auth()->user()->hasPermission('media.upload') ? 'true' : 'false' }}" data-user-id="{{ auth()->id() }}" data-content-url="{{ route('content.index') }}">
    @can('content.publish')<span hidden data-can-publish></span>@endcan
    <header class="content-library-header">
        <div>
            <p class="content-library-eyebrow">{{ __('imports.content') }}</p>
            <h1 data-content-heading data-video-heading="{{ __('imports.video_library_heading') }}">{{ __('imports.content') }}</h1>
            <p data-content-intro data-video-intro="{{ __('imports.video_library_intro') }}">{{ __('imports.content_library_intro') }}</p>
        </div>
        <div class="content-library-actions" data-content-actions>
            @can('content.edit')<button type="button" class="desktop-button is-primary" data-content-new>{{ __('workspaces.new') }}</button><button type="button" class="desktop-button" data-content-series>{{ __('workspaces.titles.series') }}</button>@endcan
            <details class="content-library-more-actions">
                <summary>{{ __('imports.more_actions') }}</summary>
                <div>
                    @can('media.edit')<button type="button" class="desktop-button" data-classify-batch="record">{{ __('imports.ai_batch') }}</button>@endcan
                    @can('content.edit')<button type="button" class="desktop-button" data-structure-batch>{{ __('imports.structure_batch') }}</button>@endcan
                    @can('content.edit')<button type="button" class="desktop-button" data-short-descriptions-missing>{{ __('imports.short_descriptions_missing') }}</button>@endcan
                    @can('imports.manage') @can('media.edit')<button type="button" class="desktop-button" data-local-video-check>{{ __('imports.audit_start') }}</button>@endcan @endcan
                    @can('content.edit')<details class="media-inspector"><summary>{{ __('imports.local_connections') }}</summary><p>{{ __('imports.local_connections_hint') }}</p><button type="button" class="desktop-button" data-repair-local-links>{{ __('imports.repair_connections') }}</button><p role="status"></p></details>@endcan
                </div>
            </details>
        </div>
    </header>
    <form class="media-library-toolbar" data-content-filter>
        <div class="content-library-filter-main">
            <label class="media-library-search"><span class="sr-only">{{ __('ui.search') }}</span><input type="search" name="q" maxlength="120" placeholder="{{ __('ui.search') }}"></label>
            <select name="kind" aria-label="{{ __('imports.content_type') }}">
                <option value="">{{ __('imports.all_types') }}</option>
                <option value="playlist">{{ __('imports.kind_playlist') }}</option>
                @foreach([...\App\Models\SourceRecord::KINDS,'archive_data'] as $kind)<option value="{{ $kind }}">{{ __('imports.kind_'.$kind) }}</option>@endforeach
            </select>
            <select name="status" aria-label="Status"><option value="">{{ __('imports.all_status') }}</option><option value="unsorted">{{ __('imports.unsorted') }}</option><option value="review">{{ __('ui.import_review') }}</option><option value="ready">{{ __('imports.ready') }}</option><option value="needs_attention">{{ __('imports.needs_attention') }}</option></select>
            <select name="publication" aria-label="{{ __('imports.publication_filter') }}"><option value="">{{ __('imports.all_publication_states') }}</option><option value="published">{{ __('imports.publication_published') }}</option><option value="unpublished">{{ __('imports.publication_unpublished') }}</option></select>
            <select name="trash" aria-label="{{ __('imports.trash_title') }}"><option value="active">{{ __('imports.trash_active') }}</option><option value="deleted">{{ __('imports.trash_title') }}</option></select>
            <button type="submit" class="media-library-primary">{{ __('imports.refresh') }}</button>
        </div>
        <details class="content-library-advanced-filters">
            <summary>{{ __('imports.more_filters') }}</summary>
            <div class="content-library-advanced-fields" data-content-advanced-fields></div>
        </details>
    </form>
    <div class="content-library-viewbar">
        <div class="media-library-summary" data-content-summary></div>
        <div class="content-library-view-switch" role="group" aria-label="Darstellung">
            <button type="button" class="desktop-button" data-content-view="cards" aria-pressed="true">{{ __('workspaces.book_view_cards') }}</button>
            <button type="button" class="desktop-button" data-content-view="list" aria-pressed="false">{{ __('workspaces.book_view_list') }}</button>
        </div>
    </div>
    <div class="media-library-layout">
        <ol class="media-library-list" data-content-list></ol>
        <aside class="media-library-details" data-content-details></aside>
    </div>
    <nav class="media-library-pagination" data-content-pagination aria-label="{{ __('imports.pages') }}"></nav>
</section>
