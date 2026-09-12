@can('media.edit')
<form class="media-bulk-form" data-media-bulk hidden>
    <div class="media-library-toolbar-row"><strong data-media-selected-count></strong><button type="button" class="desktop-button" data-media-select-page>{{ __('imports.select_page') }}</button><button type="button" class="desktop-button" data-media-clear-selection>{{ __('imports.clear_selection') }}</button></div>
    <div class="media-bulk-fields">
        <label>{{ __('imports.add_tags') }}<input name="add_tags" maxlength="500" placeholder="{{ __('imports.tags_hint') }}"></label>
        <label>{{ __('imports.status') }}<select name="status"><option value="">{{ __('imports.leave_unchanged') }}</option>@foreach(['unsorted','ready','needs_attention'] as $status)<option value="{{ $status }}">{{ __('imports.'.$status) }}</option>@endforeach</select></label>
        <label>{{ __('imports.target_profile') }}<select name="target_profile"><option value="">{{ __('imports.leave_unchanged') }}</option>@foreach(['media_library','videos','shorts','posts'] as $target)<option value="{{ $target }}">{{ __('imports.'.$target) }}</option>@endforeach</select></label>
        <label>{{ __('imports.add_collection') }}<select name="collection_id" data-media-collection-options><option value="">{{ __('imports.leave_unchanged') }}</option></select></label>
        <label>{{ __('imports.archive_action') }}<select name="archive_action"><option value="">{{ __('imports.leave_unchanged') }}</option><option value="archive">{{ __('imports.archive') }}</option><option value="restore">{{ __('imports.restore') }}</option></select></label>
    </div>
    <p>{{ __('imports.archive_hint') }}</p>
    <div class="media-library-toolbar-row"><button type="submit" class="desktop-button is-primary">{{ __('imports.apply_selection') }}</button><p data-media-bulk-message role="status"></p></div>
</form>
@endcan
<section class="media-collection-manager" data-media-collections hidden>
    <p>{{ __('imports.collections_hint') }}</p>
    <div class="media-collection-layout">
        <div>
            <form data-collection-search class="media-library-toolbar-row"><input name="q" type="search" maxlength="120" placeholder="{{ __('imports.search_collections') }}"><button type="submit" class="desktop-button">{{ __('ui.search') }}</button></form>
            <ul data-collection-list class="media-collection-list"></ul>
            <nav data-collection-pages class="media-library-pagination"></nav>
            @can('media.edit')
            <form data-collection-create class="media-collection-form"><label>{{ __('imports.new_collection') }}<input name="title" required maxlength="255"></label><button type="submit" class="desktop-button">{{ __('imports.create_collection') }}</button></form>
            @endcan
        </div>
        <div data-collection-details></div>
    </div>
    <p data-collection-message role="status"></p>
</section>
