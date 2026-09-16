@extends('public.layout', ['title'=>$selectedShelf['name'] ?? __('public.category_directory_title')])
@push('publicStyles')
<link rel="stylesheet" href="/assets/public-book-shelf.css?v=6">
<link rel="stylesheet" href="/assets/public-category-directory.css?v=1">
@endpush
@push('publicScripts')<script src="/assets/public-book-shelf.js?v=5" defer></script>@endpush

@section('content')
@php
    $categoryCount = count($directoryShelves ?? []);
    $topicCount = collect($directoryShelves ?? [])->sum(fn (array $shelf) => count($shelf['books']));
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
        <div class="public-overview-feature public-directory-feature">
            <img class="public-photo" src="{{ $heroImage }}" alt="" aria-hidden="true">
            <div class="public-feature-content">
                <span class="public-feature-label">{{ $selectedShelf['name'] ?? __('public.category_directory_title') }}</span>
                <h2>{{ trans_choice('public.category_directory_categories_count', $categoryCount, ['count'=>$categoryCount]) }}</h2>
                <p>{{ trans_choice('public.category_directory_topics_count', $topicCount, ['count'=>$topicCount]) }}</p>
            </div>
        </div>
    </div>
    <aside class="public-hero-side-copy"><p>{{ __('public.shelf_motto') }}</p></aside>
</section>
<div class="public-wide public-directory-content">
    <div class="public-book-cabinet" id="cabinet" data-book-cabinet>
        @if($selectedShelf)
            @php
                $navigationGroups = collect($categoryShelves)->map(function (array $shelf) use ($selectedShelf) {
                    $shelf['books'] = $shelf['slug'] === $selectedShelf['slug'] ? $shelf['books'] : [];
                    $shelf['selected'] = $shelf['slug'] === $selectedShelf['slug'];
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
    @include('public.newsletter')
</div>
@endsection
