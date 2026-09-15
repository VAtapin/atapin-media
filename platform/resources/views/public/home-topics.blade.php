@php($homeTopicItems = collect($homeTopics ?? []))
@if($homeTopicItems->isNotEmpty())
<section class="public-home-topics" aria-label="{{ __('public.categories') }} / {{ __('public.topics') }}" data-public-home-topics>
    <div class="public-home-topics-row">
        @foreach($homeTopicItems->groupBy(fn ($topic) => $topic['category_id'] ?: 'topic-'.$topic['id']) as $categoryTopics)
        @php($category = $categoryTopics->first())
        <div @class(['public-home-category-card', 'has-category' => (bool) $category['category_id']])>
            @if($category['category_id'])
            <div class="public-home-category-cover">
                @if($category['category_cover_url'])
                <img src="{{ $category['category_cover_url'] }}" alt="" loading="lazy">
                @endif
                <strong>{{ $category['category_name'] }}</strong>
            </div>
            @endif
            <div class="public-home-category-topics">
                @foreach($categoryTopics as $topic)
                <div class="public-home-topic-card">
                    <strong class="public-home-topic-name">{{ $topic['name'] }}</strong>
                    <nav aria-label="{{ $topic['name'] }}">
                        @foreach(['videos','beitraege','buecher'] as $topicSection)
                            @php($sectionLink = $topic['sections'][$topicSection] ?? null)
                            @if(($sectionLink['count'] ?? 0) > 0)
                            <a href="{{ $sectionLink['url'] }}">
                                <span>{{ __('public.section_'.$topicSection) }}</span>
                                <strong>{{ $sectionLink['count'] }}</strong>
                            </a>
                            @endif
                        @endforeach
                    </nav>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>
</section>
@endif
