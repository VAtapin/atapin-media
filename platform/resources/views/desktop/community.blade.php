<section class="desktop-community" data-community-moderation>
    <header class="desktop-community-header">
        <div>
            <p class="desktop-community-eyebrow">Community</p>
            <h1>{{ __('ui.community_moderation_title') }}</h1>
            <p>{{ __('ui.community_moderation_intro') }}</p>
        </div>
        <a class="desktop-button" href="/community" target="_blank" rel="noopener">{{ __('ui.community_open_website') }}</a>
    </header>
    <div class="desktop-community-stats">
        <div><strong>{{ $stats['human_review'] }}</strong><span>{{ __('ui.community_human_review') }}</span></div>
        <div><strong>{{ $stats['ai_pending'] }}</strong><span>{{ __('ui.community_ai_pending') }}</span></div>
        <div><strong>AI</strong><span>{{ __('ui.community_ai_flow') }}</span></div>
    </div>
    <section class="desktop-community-queue">
        <div class="desktop-community-queue-heading"><div><p class="desktop-community-eyebrow">Human review</p><h2>{{ __('ui.community_queue_title') }}</h2></div><span>{{ $stats['human_review'] }}</span></div>
        @forelse($entries as $entry)
            @php($moderation = $entry->metadata['moderation'] ?? [])
            <article class="desktop-community-entry">
                <header><div><h3>{{ $entry->title }}</h3><p>{{ $entry->metadata['author'] ?? __('public.guest') }} · {{ $entry->created_at->format('d.m.Y H:i') }} · {{ $entry->kind === 'live_chat' ? __('public.live_chat') : __('public.comments') }}</p></div><span class="desktop-community-risk">{{ __('ui.community_risk') }} {{ number_format((float)($moderation['risk'] ?? 1), 2, ',', '') }}</span></header>
                <div class="desktop-community-body">{{ $entry->body }}</div>
                @if(!empty($moderation['reasons']))<ul class="desktop-community-reasons">@foreach($moderation['reasons'] as $reason)<li>{{ $reason }}</li>@endforeach</ul>@endif
                <footer><form method="post" action="{{ route('desktop.community-moderate', $entry) }}">@csrf @method('PATCH')<button class="desktop-button is-primary" name="decision" value="publish">{{ __('ui.community_publish') }}</button><button class="desktop-button" name="decision" value="reject">{{ __('ui.community_reject') }}</button></form></footer>
            </article>
        @empty
            <p class="desktop-community-empty">{{ __('ui.community_queue_empty') }}</p>
        @endforelse
    </section>
</section>
