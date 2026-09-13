<article class="public-content-card public-content-card-{{ $style ?? 'video' }}">
    <a class="public-card-image" href="{{ $item['url'] }}">
        @include('public.picture', ['image' => $item['image'], 'icon' => ($style ?? 'video') === 'book' ? 'book' : 'video'])
        @if(!empty($item['duration']) && is_numeric($item['duration']))
            <span class="public-duration">{{ gmdate($item['duration'] >= 3600 ? 'H:i:s' : 'i:s', (int) $item['duration']) }}</span>
        @endif
    </a>
    <div>
        <h3><a href="{{ $item['url'] }}">{{ $item['title'] }}</a></h3>
        @if(($style ?? 'video') !== 'video' || (isset($item['section']) && $item['section'] === 'live'))
            @if(isset($item['excerpt']) && $item['excerpt'])
                <p>{{ $item['excerpt'] }}</p>
            @endif
        @endif
        @if(isset($item['author']) && $item['author'])
            <small>{{ $item['author'] }}</small>
        @endif
        @if(isset($item['meta']) && $item['meta'])
            <small>
                {{ $item['meta'] }}
                @if(isset($item['views']))
                    · {{ $item['views'] }} {{ __('public.views') }}
                @endif
            </small>
        @endif
        @if(isset($item['price']))
            <strong>{{ $item['price'] }}</strong>
        @endif
        @if(($style ?? 'video') === 'book')
            <a class="public-button" href="{{ $item['url'] }}">{{ __('public.to_book') }} →</a>
        @endif
    </div>
</article>
