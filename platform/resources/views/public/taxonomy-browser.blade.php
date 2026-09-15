@php
    $taxonomyChoices = collect($taxonomyFilters ?? []);
    $taxonomyCategories = $taxonomyChoices->where('kind', 'category')->values();
    $taxonomyTopics = $taxonomyChoices->where('kind', 'topic')->values();
    $activeTaxonomy = (string) (($selectedTaxonomy ?? null)?->slug ?? request('taxonomy', ''));
    $activeTerm = $taxonomyChoices->first(fn ($term) => $activeTaxonomy === (string) $term['id'] || $activeTaxonomy === (string) $term['slug']);
    $activeCategory = $activeTerm ? $taxonomyCategories->firstWhere('id', $activeTerm['category_id']) : null;
    $activeTopic = ($activeTerm['kind'] ?? null) === 'topic' ? $activeTerm : null;
    $allTaxonomyUrl = route('public.'.$section, request()->except(['taxonomy', 'tag', 'page']));
@endphp

@if($taxonomyChoices->isNotEmpty())
<section class="public-taxonomy-browser" aria-label="{{ __('public.categories') }} / {{ __('public.topics') }}" data-public-taxonomy>
    <nav class="public-taxonomy-crumbs" aria-label="{{ __('public.categories') }} / {{ __('public.topics') }}">
        <a href="{{ $allTaxonomyUrl }}" @if($activeTaxonomy==='') aria-current="page" @endif>{{ __('public.all_'.$section) }}</a>

        @if($taxonomyCategories->isNotEmpty())
        <span class="public-taxonomy-separator" aria-hidden="true">›</span>
        <details class="public-taxonomy-crumb">
            <summary>
                @if($activeCategory && $activeCategory['cover_url'])<img src="{{ $activeCategory['cover_url'] }}" alt="" loading="lazy">@endif
                <span>{{ $activeCategory['name'] ?? __('public.categories') }}</span>
            </summary>
            <div class="public-taxonomy-menu public-taxonomy-menu-categories">
                @foreach($taxonomyCategories as $term)
                    @php($current = $activeTaxonomy === (string) $term['id'] || $activeTaxonomy === (string) $term['slug'])
                    <a href="{{ $term['url'] }}" @if($current) aria-current="page" @endif>
                        @if($term['cover_url'])<img src="{{ $term['cover_url'] }}" alt="" loading="lazy">@endif
                        <span>{{ $term['name'] }}</span>
                        <small>{{ $term['count'] }}</small>
                    </a>
                @endforeach
            </div>
        </details>
        @endif

        @if($taxonomyTopics->isNotEmpty())
        <span class="public-taxonomy-separator" aria-hidden="true">›</span>
        <details class="public-taxonomy-crumb">
            <summary><span>{{ $activeTopic['name'] ?? __('public.topics') }}</span></summary>
            <div class="public-taxonomy-menu public-taxonomy-menu-topics">
                @php($menuTopics = $activeCategory ? $taxonomyTopics->where('category_id', $activeCategory['id']) : $taxonomyTopics)
                @foreach($menuTopics->groupBy(fn ($term) => $term['category_name'] ?: __('public.topics')) as $groupName => $groupTopics)
                    <div class="public-taxonomy-menu-group">
                        <small>{{ $groupName }}</small>
                        @foreach($groupTopics as $term)
                            @php($current = $activeTaxonomy === (string) $term['id'] || $activeTaxonomy === (string) $term['slug'])
                            <a href="{{ $term['url'] }}" @if($current) aria-current="page" @endif>
                                <span>{{ $term['name'] }}</span>
                                <small>{{ $term['count'] }}</small>
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </details>
        @endif
    </nav>
</section>
@endif
