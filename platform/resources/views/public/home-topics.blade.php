@php
    $groups = $homeShelves ?? [];
    if ($groups === []) $groups = [['name' => __('public.categories'), 'books' => []]];
@endphp
@include('public.book-shelf', ['shelfGroups' => $groups, 'shelfContext' => 'home'])
