<section class="desktop-media-library desktop-content-library" data-content-library data-can-edit="{{ auth()->user()->hasPermission('content.edit') ? 'true' : 'false' }}" data-can-media-edit="{{ auth()->user()->hasPermission('media.edit') ? 'true' : 'false' }}" data-can-upload="{{ auth()->user()->hasPermission('media.upload') ? 'true' : 'false' }}" data-user-id="{{ auth()->id() }}" data-content-url="{{ route('content.index') }}">
    @can('content.edit')<details class="media-inspector"><summary>{{ __('imports.local_connections') }}</summary><p>{{ __('imports.local_connections_hint') }}</p><button type="button" class="desktop-button" data-repair-local-links>{{ __('imports.repair_connections') }}</button><p role="status"></p></details>@endcan
    <form class="media-library-toolbar" data-content-filter>
        <label class="media-library-search"><span class="sr-only">{{ __('ui.search') }}</span><input type="search" name="q" maxlength="120" placeholder="{{ __('ui.search') }}"></label>
        <select name="kind" aria-label="{{ __('imports.content_type') }}">
            <option value="">{{ __('imports.all_types') }}</option>
            <option value="playlist">{{ __('imports.kind_playlist') }}</option>
            @foreach(['video','short','post','poll','comment'] as $kind)<option value="{{ $kind }}">{{ __('imports.kind_'.$kind) }}</option>@endforeach
        </select>
        <select name="status" aria-label="Status"><option value="">{{ __('imports.all_status') }}</option><option value="unsorted">{{ __('imports.unsorted') }}</option><option value="review">{{ __('ui.import_review') }}</option><option value="ready">{{ __('imports.ready') }}</option><option value="needs_attention">{{ __('imports.needs_attention') }}</option></select>
        <button type="submit" class="media-library-primary">{{ __('imports.refresh') }}</button>
        <select name="trash" aria-label="{{ __('imports.trash_title') }}"><option value="active">{{ __('imports.trash_active') }}</option><option value="deleted">{{ __('imports.trash_title') }}</option></select>
        @can('media.edit')<button type="button" class="media-library-primary" data-classify-batch="record">{{ __('imports.ai_batch') }}</button>@endcan
        @can('imports.manage')
        @can('media.edit')<button type="button" class="desktop-button" data-local-video-check>{{ __('imports.audit_start') }}</button>@endcan
        @endcan
    </form>
    <div class="media-library-summary" data-content-summary></div>
    <div class="media-library-layout">
        <ol class="media-library-list" data-content-list></ol>
        <aside class="media-library-details" data-content-details></aside>
    </div>
    <nav class="media-library-pagination" data-content-pagination aria-label="{{ __('imports.pages') }}"></nav>
</section>
