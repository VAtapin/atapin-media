@php($groups = collect($shelfGroups ?? []))
<section class="public-book-shelf" aria-label="{{ $shelfLabel ?? __('public.categories').' / '.__('public.topics') }}" data-public-book-shelf data-shelf-context="{{ $shelfContext ?? 'overview' }}" data-shelf-index="{{ $shelfIndex ?? 0 }}" data-shelf-variant="blank" style="--shelf-layer:{{ $shelfLayer ?? 2 }}">
    <div class="public-book-shelf-background" aria-hidden="true"></div>
    <div class="public-book-shelf-inner">
        <div class="public-book-shelf-stage" data-shelf-stage>
            <div class="public-book-shelf-categories" data-shelf-categories>
                @foreach($groups as $group)
                <div class="public-book-shelf-category" data-shelf-category="{{ $loop->index }}">
                    @if(($group['url'] ?? null) && count($group['books'] ?? []) > 0)
                    <a class="public-book-shelf-mobile-category public-book-shelf-mobile-category-{{ ($loop->index % 5) + 1 }}" href="{{ $group['url'] }}" aria-label="{{ $group['name'] }}" title="{{ $group['name'] }}" @if($group['selected'] ?? false) aria-current="page" @endif>
                        <span class="public-book-shelf-mobile-category-title">{{ $group['name'] }}</span>
                        <span class="public-book-shelf-book-mark" aria-hidden="true">◇</span>
                    </a>
                    @endif
                    <div class="public-book-shelf-category-books">
                        @foreach($group['books'] as $book)
                        <a class="public-book-shelf-book public-book-shelf-book-{{ ($loop->index % 5) + 1 }}" href="{{ $book['url'] }}" aria-label="{{ $book['name'] }}" title="{{ $book['name'] }}" @if($book['selected'] ?? false) aria-current="page" @endif>
                            <span class="public-book-shelf-book-title">{{ $book['name'] }}</span>
                            <span class="public-book-shelf-book-mark" aria-hidden="true">◇</span>
                        </a>
                        @endforeach
                    </div>
                    @if($group['url'] ?? null)
                    <a class="public-book-shelf-category-name" href="{{ $group['url'] }}" @if($group['selected'] ?? false) aria-current="page" @endif>{{ $group['name'] }}</a>
                    @else
                    <span class="public-book-shelf-category-name">{{ $group['name'] }}</span>
                    @endif
                </div>
                @endforeach
            </div>
            <a class="public-book-shelf-all" data-shelf-all href="{{ route('public.categories') }}" hidden>{{ __('public.shelf_see_all') }} →</a>
            <noscript><a class="public-book-shelf-all" href="{{ route('public.categories') }}">{{ __('public.shelf_see_all') }} →</a></noscript>
        </div>
    </div>
</section>
