@php
    $topics = collect($homeTopics ?? []);
    $asBooks = fn ($topicRows) => $topicRows->map(function (array $topic) {
        $destination = collect(['beitraege', 'videos', 'buecher'])
            ->map(fn (string $section) => $topic['sections'][$section] ?? null)
            ->filter(fn (?array $link) => ($link['count'] ?? 0) > 0)
            ->sortByDesc('count')->first();
        return ['name' => $topic['name'],
            'url' => $destination['url'] ?? route('public.beitraege', ['taxonomy'=>$topic['slug']])];
    })->values()->all();
    $groups = collect($homeCategories ?? [])->map(fn (array $category) => [
        'name' => $category['name'],
        'books' => $asBooks($topics->where('category_id', $category['id'])),
    ]);
    $uncategorized = $asBooks($topics->whereNull('category_id'));
    if ($uncategorized !== []) $groups->push(['name' => __('public.topics'), 'books' => $uncategorized]);
    if ($groups->isEmpty()) $groups->push(['name' => __('public.categories'), 'books' => []]);
@endphp
@include('public.book-shelf', ['shelfGroups' => $groups->all()])
