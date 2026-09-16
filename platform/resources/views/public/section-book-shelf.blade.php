@php
    $selectedId = (int) (($selectedTaxonomy ?? null)?->id ?? 0);
    $selectedKind = ($selectedTaxonomy ?? null)?->kind;
    $selectedCategoryId = $selectedKind === 'topic'
        ? (collect($directoryShelfGroups ?? [])->first(fn (array $group) => collect($group['books'])->contains('id', $selectedId))['id'] ?? null)
        : $selectedId;
    $groups = collect($directoryShelfGroups ?? [])->map(function (array $group) use ($selectedId, $selectedKind) {
        $group['url'] = isset($group['slug']) ? route('public.beitraege', ['taxonomy' => $group['slug']]) : null;
        $group['selected'] = $selectedKind === 'category' && $group['id'] === $selectedId;
        $group['books'] = collect($group['books'])->reject(fn (array $book) => $selectedKind === 'topic' && $book['id'] === $selectedId)
            ->map(fn (array $book) => [...$book, 'url' => route('public.beitraege', ['taxonomy' => $book['slug']])])->values()->all();
        return $group;
    });
    if ($selectedCategoryId) {
        $selectedGroup = $groups->first(fn (array $group) => $group['id'] === $selectedCategoryId);
        if ($selectedGroup) $groups = $groups->sortBy(fn (array $group) => $group['id'] === $selectedGroup['id'] ? 0 : 1)->values();
    }
    if ($groups->isEmpty()) $groups->push(['name' => __('public.categories'), 'books' => []]);
@endphp
@include('public.book-shelf', ['shelfGroups'=>$groups->all(), 'shelfContext'=>'overview'])
