<section class="desktop-media-library desktop-content-library" data-content-library data-content-url="{{ route('content.index') }}">
    <form class="media-library-toolbar" data-content-filter>
        <label class="media-library-search"><span class="sr-only">{{ __('ui.search') }}</span><input type="search" name="q" maxlength="120" placeholder="{{ __('ui.search') }}"></label>
        <select name="kind" aria-label="{{ __('imports.content_type') }}">
            <option value="">{{ __('imports.all_types') }}</option>
            @foreach(['video','short','post','poll','comment'] as $kind)<option value="{{ $kind }}">{{ __('imports.kind_'.$kind) }}</option>@endforeach
        </select>
        <select name="status" aria-label="Status"><option value="">{{ __('imports.all_status') }}</option><option value="unsorted">{{ __('imports.unsorted') }}</option><option value="review">{{ __('ui.import_review') }}</option><option value="ready">{{ __('imports.ready') }}</option></select>
        <button type="submit" class="media-library-primary">{{ __('imports.refresh') }}</button>
    </form>
    <div class="media-library-summary" data-content-summary></div>
    <div class="media-library-layout">
        <ol class="media-library-list" data-content-list></ol>
        <aside class="media-library-details" data-content-details></aside>
    </div>
    <nav class="media-library-pagination" data-content-pagination aria-label="{{ __('imports.pages') }}"></nav>
</section>
