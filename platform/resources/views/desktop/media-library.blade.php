<section class="desktop-media-library" data-media-library data-can-edit="{{ auth()->user()->hasPermission('media.edit') ? 'true' : 'false' }}" data-can-undo="{{ auth()->user()->hasPermission('media.edit') && auth()->user()->hasPermission('content.edit') ? 'true' : 'false' }}" data-user-id="{{ auth()->id() }}" data-library-url="{{ route('media.library') }}">
    <div class="media-library-toolbar-row">
        <button type="button" class="media-library-primary" data-library-content-toggle>{{ __('imports.content') }}</button>
        @can('imports.manage')<button type="button" class="desktop-button" data-open-app="imports">{{ __('imports.import_action') }}</button>@endcan
        @can('imports.manage')
        @can('media.edit')<button type="button" class="desktop-button" data-local-video-check>{{ __('imports.audit_start') }}</button>@endcan
        @endcan
        <details class="media-library-actions"><summary>{{ __('imports.more_actions') }}</summary><div class="media-library-toolbar-row">
        <button type="button" class="media-library-primary" data-library-grid>{{ __('imports.grid') }}</button>
        <button type="button" class="desktop-button" data-library-collections>{{ __('imports.collections') }}</button>
        @can('media.upload')<button type="button" class="desktop-button" data-media-upload-folder>{{ __('imports.upload_folder') }}</button>@endcan
        @can('media.edit')<button type="button" class="desktop-button" data-library-select>{{ __('imports.select_files') }}</button>@endcan
        @can('imports.manage')<button type="button" class="media-library-primary" data-library-import-existing>{{ __('imports.import_existing') }}</button>@endcan
        @can('content.edit')<button type="button" class="media-library-primary" data-classify-batch="media">{{ __('imports.ai_batch') }}</button>@endcan
        </div></details>
        @can('media.upload')
        <button type="button" class="media-library-primary" data-media-upload data-media-upload-label>{{ __('imports.upload_files') }}</button>
        <input type="file" data-media-upload-input multiple hidden><input type="file" data-media-upload-folder-input webkitdirectory multiple hidden>
        @endcan
        <p data-media-upload-message class="media-library-upload-message" aria-live="polite" role="status" hidden></p>
    </div>
    <section class="media-upload-queue" data-media-upload-queue hidden>
        <div class="media-library-toolbar-row" data-media-upload-controls><button type="button" class="desktop-button" data-media-upload-pause>{{ __('imports.upload_pause') }}</button><button type="button" class="desktop-button" data-media-upload-stop>{{ __('imports.upload_stop') }}</button><small>{{ __('imports.upload_queue_hint') }}</small></div>
        <ul data-media-upload-items></ul>
    </section>
    @include('desktop.media-organization')
    <div data-library-content-container hidden>@include('desktop.content-library')</div>
    <form class="media-library-toolbar" data-library-filter>
        <label class="media-library-search">
            <span class="sr-only">Suchen</span>
            <input name="q" type="search" maxlength="120" placeholder="Medien durchsuchen">
        </label>
        <select name="status" aria-label="Status">
            <option value="">Alle Status</option>
            <option value="unsorted">Unsortiert</option>
            <option value="processing">In Verarbeitung</option>
            <option value="ready">Bereit</option>
            <option value="needs_attention">Benötigt Aufmerksamkeit</option>
            <option value="failed">Fehlgeschlagen</option>
        </select>
        <select name="kind" aria-label="Dateityp">
            <option value="">Alle Typen</option>
            <option value="video">Video</option>
            <option value="audio">Audio</option>
            <option value="image">Bilder</option>
            <option value="document">Dokumente</option>
            <option value="pdf">PDF</option>
            <option value="other">Andere</option>
        </select>
        <details class="media-library-more-filters"><summary>{{ __('imports.more_filters') }}</summary><div class="media-library-filter-fields">
        <select name="source" aria-label="Quelle">
            <option value="">Alle Quellen</option>
            <option value="intake">Dateien des Eigentümers</option>
            <option value="youtube">YouTube-Archiv</option>
            <option value="youtube-takeout">{{ __('imports.source_youtube-takeout') }}</option>
            <option value="upload">Media-Library-Upload</option>
            <option value="local-folder">{{ __('imports.server_folder') }}</option>
            <option value="local-archive">{{ __('imports.upload_archive') }}</option>
            @foreach(['tiktok','instagram','facebook-video','youtube-service'] as $source)<option value="{{ $source }}">{{ __('imports.source_'.$source) }}</option>@endforeach
        </select>
        <select name="sort" aria-label="Sortierung"><option value="newest">Neueste zuerst</option><option value="oldest">Älteste zuerst</option><option value="name">Name</option><option value="size">Größe</option></select>
        <label><span class="sr-only">{{ __('imports.tag_filter') }}</span><input name="tag" maxlength="100" placeholder="{{ __('imports.tag_filter') }}"></label>
        <select name="collection" aria-label="{{ __('imports.collections') }}" data-media-collection-options><option value="">{{ __('imports.all_collections') }}</option></select>
        <select name="archive" aria-label="{{ __('imports.archive_action') }}"><option value="active">{{ __('imports.active_files') }}</option><option value="archived">{{ __('imports.archived_files') }}</option><option value="all">{{ __('imports.all_files') }}</option></select>
        </div></details>
    </form>
    <div class="media-library-summary" data-library-summary>Archiv wird geladen …</div>
    <div class="media-library-layout">
        <ol class="media-library-list" data-library-list aria-live="polite"></ol>
        <aside class="media-library-details" data-library-details><p>Wähle ein Medium, um Details zu sehen.</p></aside>
    </div>
    <nav class="media-library-pagination" data-library-pagination aria-label="Seitennavigation"></nav>
</section>
