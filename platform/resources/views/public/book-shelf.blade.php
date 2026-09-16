@php($groups = collect($shelfGroups ?? []))
<section class="public-book-shelf" aria-label="{{ $shelfLabel ?? __('public.categories').' / '.__('public.topics') }}" data-public-book-shelf data-shelf-decor-mode="{{ $shelfDecor ?? 'auto' }}">
    <img class="public-book-shelf-background" src="/assets/book-shelf/shelf.png?v=2" alt="" aria-hidden="true" loading="lazy">
    <div class="public-book-shelf-inner">
        <div class="public-book-shelf-stage" data-shelf-stage>
            <img class="public-book-shelf-plant-left" data-shelf-plant-left src="/assets/book-shelf/plant-left.png" alt="" aria-hidden="true" loading="lazy" hidden>
            <div class="public-book-shelf-categories" data-shelf-categories>
                @foreach($groups as $group)
                <div class="public-book-shelf-category" data-shelf-category="{{ $loop->index }}">
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
            <div class="public-book-shelf-decor" data-shelf-decor>
                <div class="public-book-shelf-motto" data-shelf-motto aria-hidden="true" @if(($shelfDecor ?? 'auto') === 'cabinet') hidden @endif>{{ __('public.shelf_motto') }}</div>
                <img class="public-book-shelf-globe" data-shelf-globe src="/assets/book-shelf/globe.png" alt="" aria-hidden="true" loading="lazy" @if(($shelfDecor ?? 'auto') === 'cabinet') hidden @endif>
                <img class="public-book-shelf-plant-right" data-shelf-plant-right src="/assets/book-shelf/plant-right.png" alt="" aria-hidden="true" loading="lazy" @if(($shelfDecor ?? 'auto') === 'cabinet') hidden @endif>
            </div>
            <a class="public-book-shelf-all" data-shelf-all href="{{ route('public.categories') }}" hidden>{{ __('public.shelf_see_all') }} →</a>
            <noscript><a class="public-book-shelf-all" href="{{ route('public.categories') }}">{{ __('public.shelf_see_all') }} →</a></noscript>
        </div>
    </div>
</section>
