@php
    $taxonomyChoices = collect($taxonomyFilters ?? []);
    $taxonomyCategories = $taxonomyChoices->where('kind', 'category')->values();
    $taxonomyTopics = $taxonomyChoices->where('kind', 'topic')->values();
    $activeTaxonomy = (string) (($selectedTaxonomy ?? null)?->slug ?? request('taxonomy', ''));
    $allTaxonomyUrl = route('public.'.$section, request()->except(['taxonomy', 'tag', 'page']));
@endphp

@if($taxonomyChoices->isNotEmpty())
<section class="public-taxonomy-browser" aria-labelledby="taxonomy-heading-{{ $section }}" data-public-taxonomy>
    <div class="public-taxonomy-heading">
        <div>
            <p class="public-eyebrow">{{ __('public.taxonomy_eyebrow') }}</p>
            <h2 id="taxonomy-heading-{{ $section }}">{{ __('public.explore_'.$section.'_by_topic') }}</h2>
        </div>
        <a href="{{ $allTaxonomyUrl }}" @if($activeTaxonomy==='') aria-current="page" @endif>
            {{ __('public.all_'.$section) }}
        </a>
    </div>

    @if($taxonomyCategories->isNotEmpty())
    <div class="public-taxonomy-group">
        <h3>{{ __('public.categories') }}</h3>
        <nav class="public-taxonomy-row" aria-label="{{ __('public.categories') }}">
            @foreach($taxonomyCategories as $term)
                @php($current = $activeTaxonomy === (string) $term['id'] || $activeTaxonomy === (string) $term['slug'])
                <a href="{{ $term['url'] }}" @class(['current'=>$current]) @if($current) aria-current="page" @endif>
                    <span class="public-taxonomy-symbol">◇</span>
                    <span>
                        <strong>{{ $term['name'] }}</strong>
                        <small>{{ trans_choice('public.section_item_count_'.$section, (int) $term['count'], ['count'=>(int) $term['count']]) }}</small>
                    </span>
                </a>
            @endforeach
        </nav>
    </div>
    @endif

    @if($taxonomyTopics->isNotEmpty())
    <div class="public-taxonomy-group">
        <h3>{{ __('public.topics') }}</h3>
        <nav class="public-taxonomy-row public-taxonomy-row-topics" aria-label="{{ __('public.topics') }}">
            @foreach($taxonomyTopics as $term)
                @php($current = $activeTaxonomy === (string) $term['id'] || $activeTaxonomy === (string) $term['slug'])
                <a href="{{ $term['url'] }}" @class(['current'=>$current]) @if($current) aria-current="page" @endif>
                    <span>
                        @if(!empty($term['parent_name']))<small>{{ $term['parent_name'] }}</small>@endif
                        <strong>{{ $term['name'] }}</strong>
                        <small>{{ trans_choice('public.section_item_count_'.$section, (int) $term['count'], ['count'=>(int) $term['count']]) }}</small>
                    </span>
                    <span aria-hidden="true">→</span>
                </a>
            @endforeach
        </nav>
    </div>
    @endif
</section>
@endif
