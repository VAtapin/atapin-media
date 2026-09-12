<section class="desktop-import-center" data-import-center data-user-id="{{ auth()->id() }}" data-imports-url="{{ route('imports.index') }}" data-imports-options-url="{{ route('imports.options') }}">
    <form class="import-center-form" data-import-form>
        @can('media.upload')
        <label><span>{{ __('imports.upload_archive') }}</span><input type="file" data-import-file accept=".zip,.tar,.tar.gz,.tgz"><small>{{ __('imports.upload_hint') }}</small></label>
        @endcan
        <label>
            <span>Quelle</span>
            <select name="source" data-import-source required>
                <option value="">Quelle wählen ...</option>
            </select>
        </label>

        <label>
            <span>Zielbereich</span>
            <select name="target_profile" data-import-target>
                <option value="mixed">Automatisch (gemischt)</option>
            </select>
        </label>

        <label>
            <span>Pfad / ID / URL</span>
            <input type="text" name="source_value" data-import-source-value placeholder="Pfad, Link oder ID" maxlength="255">
            <small>Für lokale Quellen: Pfad unter <code>private/import-inbox</code>. Für Services: URL.</small>
        </label>

        <label>
            <span>Erweiterte Felder</span>
            <div class="import-center-grid-2">
                <input type="text" name="channel_id" data-import-channel placeholder="YouTube Channel-ID (optional)" maxlength="255">
                <input type="text" name="playlist_id" data-import-playlist placeholder="YouTube Playlist-ID (optional)" maxlength="255">
            </div>
            <textarea name="notes" data-import-notes placeholder="Notizen (optional)" rows="3" maxlength="2000"></textarea>
        </label>

        <label class="import-center-check">
            <input type="checkbox" name="only_unsorted" value="1" checked>
            <span>Neue Einträge im Status Unsortiert starten</span>
        </label>

        <button type="submit" class="import-center-primary" data-import-start>Import starten</button>
        <p class="import-center-message" data-import-message role="status" aria-live="polite" hidden></p>
    </form>

    <div class="import-center-live">
        <div class="import-center-status" data-import-status>Laufende Import-Jobs werden geladen ...</div>
        <div class="import-center-run-list-wrap">
            <div class="import-center-run-list-head">
                <strong>Import-Protokoll</strong>
                <button type="button" data-import-refresh>Aktualisieren</button>
            </div>
            <div class="import-center-run-list" data-import-run-list aria-live="polite"></div>
        </div>
    </div>
</section>
