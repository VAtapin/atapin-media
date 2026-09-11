@if($paginator->hasPages())<nav class="pagination" aria-label="{{ __('ui.pages') }}">
@if($paginator->onFirstPage())<span>{{ __('ui.previous') }}</span>@else<a class="button secondary" href="{{ $paginator->previousPageUrl() }}">{{ __('ui.previous') }}</a>@endif
<span>{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
@if($paginator->hasMorePages())<a class="button secondary" href="{{ $paginator->nextPageUrl() }}">{{ __('ui.next') }}</a>@else<span>{{ __('ui.next') }}</span>@endif
</nav>@endif
