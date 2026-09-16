@php
    $filters = collect($taxonomyFilters ?? []);
    $selected = ($selectedTaxonomy ?? null)?->id;
    $selectedTerm = $filters->firstWhere('id', $selected);
    $selectedCategory = $selectedTerm['category_id'] ?? null;
    $groups = collect($shelfCategories ?? [])->map(fn (array $category) => [
        'id' => $category['id'],
        'name' => $category['name'],
        'books' => $filters->where('kind', 'topic')->where('category_id', $category['id'])
            ->map(fn (array $topic) => [
                'name' => $topic['name'], 'url' => $topic['url'],
                'selected' => $topic['id'] === $selected,
            ])->values()->all(),
    ]);
    $uncategorized = $filters->where('kind', 'topic')->whereNull('category_id')
        ->map(fn (array $topic) => ['name'=>$topic['name'], 'url'=>$topic['url'],
            'selected'=>$topic['id'] === $selected])->values()->all();
    if ($uncategorized !== []) $groups->push(['id'=>null,'name'=>__('public.topics'),'books'=>$uncategorized]);
    if ($selectedCategory) $groups = $groups->sortBy(fn (array $group) => $group['id'] === $selectedCategory ? 0 : 1)->values();
    if ($groups->isEmpty()) $groups->push(['name'=>__('public.categories'),'books'=>[]]);
@endphp
@include('public.book-shelf', ['shelfGroups'=>$groups->all()])
