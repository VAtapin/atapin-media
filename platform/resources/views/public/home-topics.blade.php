@php($homeTopicItems = collect($homeTopics ?? []))
@if($homeTopicItems->isNotEmpty())
<section class="public-home-topics" aria-labelledby="home-topics-heading" data-public-home-topics>
    <div class="public-home-topics-heading">
        <div>
            <p class="public-eyebrow">{{ __('public.taxonomy_eyebrow') }}</p>
            <h2 id="home-topics-heading">{{ __('public.home_topics_title') }}</h2>
        </div>
        <p>{{ __('public.home_topics_intro') }}</p>
    </div>
    <div class="public-home-topics-row">
        @foreach($homeTopicItems as $topic)
        <article class="public-home-topic-card">
            @if(!empty($topic['parent_name']))<small>{{ $topic['parent_name'] }}</small>@endif
            <h3>{{ $topic['name'] }}</h3>
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
        </article>
        @endforeach
    </div>
</section>
@endif
