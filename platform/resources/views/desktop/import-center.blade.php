<section class="desktop-import-center" data-import-center data-user-id="{{ auth()->id() }}" data-imports-url="{{ route('imports.index') }}" data-imports-options-url="{{ route('imports.options') }}" data-import-files-url="{{ route('imports.files') }}" data-takeout-url="{{ route('imports.takeout') }}">
    <div class="media-library-toolbar-row"><button type="button" class="desktop-button" data-open-import-history>{{ __('imports.run_history') }}</button>@can('media.edit')<button type="button" class="desktop-button" data-local-video-check>{{ __('imports.audit_start') }}</button>@endcan
    @can('content.edit')<button type="button" class="desktop-button" data-catalog-reset>{{ __('imports.reset_start') }}</button>@endcan</div>
    <div class="import-center-status" data-import-status role="status">{{ __('imports.history_loading') }}</div>
    <p class="import-worker-status" data-import-worker-status role="status" hidden></p>
    <form class="import-center-form" data-import-form>
        <fieldset class="import-methods">
            <legend>{{ __('imports.choose_method') }}</legend>
            @can('media.upload')
            <label><input type="radio" name="method" value="computer" checked><strong>{{ __('imports.method_computer') }}</strong><small>{{ __('imports.method_computer_hint') }}</small></label>
            @endcan
            <label><input type="radio" name="method" value="link" @cannot('media.upload') checked @endcannot><strong>{{ __('imports.method_link') }}</strong><small>{{ __('imports.method_link_hint') }}</small></label>
            <label><input type="radio" name="method" value="existing"><strong>{{ __('imports.method_existing') }}</strong><small>{{ __('imports.method_existing_hint') }}</small></label>
            <label><input type="radio" name="method" value="server"><strong>{{ __('imports.method_server') }}</strong><small>{{ __('imports.method_server_hint') }}</small></label>
            <label><input type="radio" name="method" value="takeout"><strong>{{ __('imports.method_takeout') }}</strong><small>{{ __('imports.takeout_hint') }}</small></label>
        </fieldset>
        @can('media.upload')
        <div class="import-method-panel" data-import-panel="computer">
            <label class="import-upload-zone" data-import-drop><span>{{ __('imports.upload_archive') }}</span><input type="file" data-import-file accept=".zip,.tar,.tar.gz,.tgz"><small>{{ __('imports.upload_hint') }}</small></label>
        </div>
        @endcan
        <div class="import-method-panel" data-import-panel="link" hidden>
            <label><span>{{ __('imports.link_label') }}</span><input type="url" data-import-link placeholder="https://www.youtube.com/…" maxlength="255"><small>{{ __('imports.method_link_hint') }}</small></label>
            <p class="import-center-message" data-import-detected role="status"></p>
        </div>
        <div class="import-method-panel" data-import-panel="existing" hidden>
            <label><span>{{ __('imports.existing_label') }}</span><select data-import-existing><option value="intake">{{ __('imports.source_intake') }}</option><option value="youtube">{{ __('imports.source_youtube') }}</option></select></label>
            <p>{{ __('imports.existing_hint') }}</p>
        </div>
        <div class="import-method-panel" data-import-panel="server" hidden>
            <p>{{ __('imports.server_scope') }}</p>
            <div class="import-browser-toolbar"><button type="button" class="desktop-button" data-import-up disabled>{{ __('imports.browser_up') }}</button><strong data-import-browser-path>{{ __('imports.browser_root') }}</strong><button type="button" class="desktop-button" data-import-reload>{{ __('imports.refresh') }}</button></div>
            <ul class="import-browser-list" data-import-browser-list aria-label="{{ __('imports.server_folder') }}"></ul>
            <p data-import-browser-message role="status"></p>
            <button type="button" class="desktop-button" data-import-use-folder disabled>{{ __('imports.use_folder') }}</button>
            <p class="import-selection" data-import-selection role="status"></p>
        </div>
        <div class="import-method-panel" data-import-panel="takeout" hidden>
            <p>{{ __('imports.takeout_hint') }}</p>
            <label><span>{{ __('imports.takeout_batch') }}</span><select data-takeout-batch></select></label>
            <label><span>{{ __('imports.takeout_parts') }}</span><input type="number" min="1" max="100" value="8" data-takeout-parts></label>
            <button type="button" class="desktop-button" data-takeout-refresh>{{ __('imports.refresh') }}</button><p data-takeout-message role="status"></p>
        </div>
        <details class="import-advanced">
            <summary>{{ __('imports.advanced_options') }}</summary>
            <label><span>{{ __('imports.target_profile') }}</span><select data-import-target><option value="mixed">{{ __('imports.target_mixed') }}</option></select><small>{{ __('imports.target_hint') }}</small></label>
            <label><span>{{ __('imports.notes_label') }}</span><textarea data-import-notes rows="2" maxlength="2000"></textarea></label>
        </details>
        <p class="import-safety-note">{{ __('imports.safety_note') }}</p>
        <div class="import-submit-row"><button type="submit" class="desktop-button is-primary" data-import-start>{{ __('imports.start') }}</button><p class="import-center-message" data-import-message role="status" aria-live="polite" hidden></p></div>
        <div class="import-submit-row" data-import-upload-controls hidden><button type="button" class="desktop-button" data-import-upload-pause>{{ __('imports.upload_pause') }}</button><button type="button" class="desktop-button" data-import-upload-stop>{{ __('imports.upload_stop') }}</button><small>{{ __('imports.upload_queue_hint') }}</small></div>
    </form>
    <dialog class="import-report-dialog import-history-dialog os-window-content" data-import-history-dialog aria-label="{{ __('imports.run_history') }}">
        <div class="import-center-run-list-wrap">
            <div class="import-center-run-list-head"><strong>{{ __('imports.run_history') }}</strong><div><button type="button" class="desktop-button" data-import-refresh>{{ __('imports.refresh') }}</button><button type="button" class="desktop-button" data-close-import-history>{{ __('imports.report_close') }}</button></div></div>
            <p class="import-worker-status" data-import-worker-status role="status" hidden></p>
            <div class="import-center-run-list" data-import-run-list aria-live="polite"></div>
            <nav class="media-library-pagination" data-import-pages aria-label="{{ __('imports.pages') }}"></nav>
        </div>
    </dialog>
</section>
