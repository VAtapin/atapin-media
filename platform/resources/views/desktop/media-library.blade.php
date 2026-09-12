<section class="desktop-media-library" data-media-library data-user-id="{{ auth()->id() }}" data-library-url="{{ route('media.library') }}">
    <div class="media-library-toolbar-row">
        <button type="button" class="media-library-primary" data-library-content-toggle>{{ __('imports.content') }}</button>
        <button type="button" class="media-library-primary" data-library-grid>{{ __('imports.grid') }}</button>
        @can('imports.manage')<button type="button" class="media-library-primary" data-library-import-existing>{{ __('imports.import_existing') }}</button>@endcan
        <button type="button" class="media-library-primary" data-media-upload data-media-upload-label>Hochladen</button>
        <input type="file" data-media-upload-input multiple hidden>
        <p data-media-upload-message class="media-library-upload-message" aria-live="polite" role="status" hidden></p>
    </div>
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
        <select name="source" aria-label="Quelle">
            <option value="">Alle Quellen</option>
            <option value="intake">Dateien des Eigentümers</option>
            <option value="youtube">YouTube-Archiv</option>
            <option value="upload">Media-Library-Upload</option>
            <option value="local-folder">{{ __('imports.server_folder') }}</option>
            <option value="local-archive">{{ __('imports.upload_archive') }}</option>
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
        <select name="sort" aria-label="Sortierung"><option value="newest">Neueste zuerst</option><option value="oldest">Älteste zuerst</option><option value="name">Name</option><option value="size">Größe</option></select>
    </form>
    <div class="media-library-summary" data-library-summary>Archiv wird geladen …</div>
    <div class="media-library-layout">
        <ol class="media-library-list" data-library-list aria-live="polite"></ol>
        <aside class="media-library-details" data-library-details><p>Wähle ein Medium, um Details zu sehen.</p></aside>
    </div>
    <nav class="media-library-pagination" data-library-pagination aria-label="Seitennavigation"></nav>
</section>
