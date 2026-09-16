@extends('public.layout', ['title'=>$selectedShelf['name'] ?? __('public.category_directory_title')])
@push('publicStyles')
<link rel="stylesheet" href="/assets/public-book-shelf.css?v=7">
<link rel="stylesheet" href="/assets/public-category-directory.css?v=3">
@endpush
@push('publicScripts')<script src="/assets/public-book-shelf.js?v=6" defer></script>@endpush

@section('content')
@php
    $categoryCount = count($directoryShelves ?? []);
    $topicCount = collect($directoryShelves ?? [])->sum(fn (array $shelf) => count($shelf['books']));
    $featureImage = $selectedShelf['cover_url'] ?? $heroImage;
@endphp
<section class="public-overview-hero public-directory-hero" style="--hero-image:url('{{ $heroImage }}')">
    <img class="public-overview-hero-background" src="{{ $heroImage }}" alt="" aria-hidden="true">
    <div class="public-overview-hero-inner">
        <div class="public-overview-copy">
            <p class="public-eyebrow">{{ __('public.categories') }}</p>
            <h1>{{ $selectedShelf['name'] ?? __('public.category_directory_title') }}</h1>
            <p class="public-overview-intro">{{ $selectedShelf ? __('public.category_directory_selected_intro') : __('public.category_directory_intro') }}</p>
            <div class="public-overview-actions"><a class="public-button" href="{{ $selectedShelf ? route('public.categories') : '#cabinet' }}">{{ $selectedShelf ? __('public.category_directory_all') : __('public.category_directory_explore') }} →</a></div>
        </div>
        <div class="public-overview-feature public-directory-feature{{ $selectedShelf ? ' public-directory-feature-selected' : '' }}">
            <img class="public-photo" src="{{ $featureImage }}" alt="" aria-hidden="true">
            <div class="public-feature-content">
                @if($selectedShelf)
                <h2>{{ $selectedShelf['name'] }}</h2>
                @else
                <span class="public-feature-label">{{ $selectedShelf['name'] ?? __('public.category_directory_title') }}</span>
                <h2>{{ trans_choice('public.category_directory_categories_count', $categoryCount, ['count'=>$categoryCount]) }}</h2>
                <p>{{ trans_choice('public.category_directory_topics_count', $topicCount, ['count'=>$topicCount]) }}</p>
                @endif
            </div>
        </div>
    </div>
    <aside class="public-hero-side-copy"><p>{{ __('public.shelf_motto') }}</p></aside>
</section>
<div class="public-wide public-directory-content">
    <div class="public-book-cabinet" id="cabinet" data-book-cabinet>
        @if($selectedShelf)
            @php
                $navigationGroups = collect($categoryShelves)->map(function (array $shelf) use ($selectedShelf, $selectedTopic) {
                    $shelf['books'] = $shelf['slug'] === $selectedShelf['slug'] ? $shelf['books'] : [];
                    $shelf['selected'] = $shelf['slug'] === $selectedShelf['slug'];
                    if ($shelf['selected']) {
                        $shelf['books'] = collect($shelf['books'])
                            ->reject(fn (array $book) => ($selectedTopic?->id ?? null) === $book['id'])
                            ->map(fn (array $book) => [...$book, 'url' => route('public.categories', [
                                'category' => $selectedShelf['slug'],
                                'taxonomy' => $book['slug'],
                            ])])->values()->all();
                    }
                    return $shelf;
                })->sortBy(fn (array $shelf) => $shelf['selected'] ? 0 : 1)->values()->all();
            @endphp
            @include('public.book-shelf', ['shelfGroups'=>$navigationGroups, 'shelfLabel'=>__('public.categories').' / '.$selectedShelf['name'], 'shelfContext'=>'cabinet-detail'])
        @else
        @forelse($directoryShelves as $shelf)
            @include('public.book-shelf', ['shelfGroups'=>[$shelf], 'shelfContext'=>'cabinet', 'shelfIndex'=>$loop->index, 'shelfLayer'=>count($directoryShelves)-$loop->index+1, 'shelfLabel'=>$shelf['name']])
        @empty
            <p class="public-empty-slot">{{ __('public.no_data') }}</p>
        @endforelse
        @endif
    </div>
    @if($selectedShelf)
    @php($contentSlug = $selectedTopic?->slug ?? $selectedShelf['slug'])
    <section class="public-directory-selection" aria-labelledby="category-content-heading">
        <div class="public-directory-selection-heading">
            <p class="public-eyebrow">{{ __('public.category_content_eyebrow') }}</p>
            <h2 id="category-content-heading">{{ __('public.category_content_title', ['name'=>$selectedTopic?->name ?? $selectedShelf['name']]) }}</h2>
        </div>
        <div class="public-directory-sections">
            @include('public.cards', [
                'heading'=>__('public.latest_articles'), 'cards'=>$categoryContent['beitraege'], 'style'=>'post',
                'panelClass'=>'public-directory-content-panel',
                'listingUrl'=>route('public.beitraege', ['taxonomy'=>$contentSlug]),
                'emptyUrl'=>route('public.beitraege', ['taxonomy'=>$contentSlug]),
            ])
            @include('public.cards', [
                'heading'=>__('public.latest_videos'), 'cards'=>$categoryContent['videos'], 'style'=>'video',
                'panelClass'=>'public-directory-content-panel',
                'listingUrl'=>route('public.videos', ['taxonomy'=>$contentSlug]),
                'emptyUrl'=>route('public.videos', ['taxonomy'=>$contentSlug]),
            ])
            @include('public.cards', [
                'heading'=>__('public.new_in_library'), 'cards'=>$categoryContent['buecher'], 'style'=>'book',
                'panelClass'=>'public-directory-content-panel',
                'listingUrl'=>route('public.buecher', ['taxonomy'=>$contentSlug]),
                'emptyUrl'=>route('public.buecher', ['taxonomy'=>$contentSlug]),
            ])
        </div>
    </section>
    @endif
    @include('public.newsletter')
</div>
@endsection
