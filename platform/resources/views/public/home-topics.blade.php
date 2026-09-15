@php
    $homeTopicItems = collect($homeTopics ?? []);
    $categoryGroups = $homeTopicItems->groupBy(fn ($topic) => $topic['category_id'] ?: 'topic-'.$topic['id']);
    $initialCategory = $categoryGroups->first()?->first();
@endphp
@if($categoryGroups->isNotEmpty())
<section class="public-home-topics" aria-label="{{ __('public.categories') }} / {{ __('public.topics') }}" data-public-home-topics>
    <nav class="public-taxonomy-crumbs" aria-label="{{ __('public.categories') }} / {{ __('public.topics') }}">
        <details class="public-taxonomy-crumb" data-home-categories-menu>
            <summary>
                <img data-home-category-image @if($initialCategory['category_cover_url']) src="{{ $initialCategory['category_cover_url'] }}" @else hidden @endif alt="" loading="lazy">
                <span data-home-category-label>{{ $initialCategory['category_name'] ?: $initialCategory['name'] }}</span>
            </summary>
            <div class="public-taxonomy-menu public-taxonomy-menu-categories">
                @foreach($categoryGroups as $groupKey => $categoryTopics)
                    @php($category = $categoryTopics->first())
                    <button type="button" data-home-category="{{ $groupKey }}"
                        data-home-category-name="{{ $category['category_name'] ?: $category['name'] }}"
                        data-home-category-cover="{{ $category['category_cover_url'] ?: '' }}"
                        data-home-topic-count="{{ $categoryTopics->count() }}"
                        aria-pressed="{{ $loop->first ? 'true' : 'false' }}">
                        @if($category['category_cover_url'])<img src="{{ $category['category_cover_url'] }}" alt="" loading="lazy">@endif
                        <span>{{ $category['category_name'] ?: $category['name'] }}</span>
                        <small>{{ $categoryTopics->count() }}</small>
                    </button>
                @endforeach
            </div>
        </details>
        <span class="public-taxonomy-separator" aria-hidden="true">›</span>
        <details class="public-taxonomy-crumb">
            <summary><span>{{ __('public.topics') }}</span><small data-home-topic-count>{{ $categoryGroups->first()->count() }}</small></summary>
            <div class="public-taxonomy-menu public-taxonomy-menu-topics">
                @foreach($categoryGroups as $groupKey => $categoryTopics)
                <div data-home-topic-group="{{ $groupKey }}" @if(!$loop->first) hidden @endif>
                    @foreach($categoryTopics as $topic)
                    <div class="public-taxonomy-menu-topic">
                        <strong>{{ $topic['name'] }}</strong>
                        <div>
                            @foreach(['videos','beitraege','buecher'] as $topicSection)
                                @php($sectionLink = $topic['sections'][$topicSection] ?? null)
                                @if(($sectionLink['count'] ?? 0) > 0)
                                <a href="{{ $sectionLink['url'] }}">{{ __('public.section_'.$topicSection) }} <small>{{ $sectionLink['count'] }}</small></a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
                @endforeach
            </div>
        </details>
    </nav>
</section>
@endif
