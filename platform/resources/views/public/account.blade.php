@extends('public.layout')

@section('content')
@if($isStaffAccount)
<div class="public-account-page public-account-staff-only">
    <section class="public-panel">
        <p class="public-account-eyebrow">{{ __('public.account_staff_eyebrow') }}</p>
        <h1>{{ __('public.account_staff_title') }}</h1>
        <p class="public-account-staff-identity">{{ $accountUser->name }} · {{ $accountUser->email }}</p>
        <p class="public-account-staff-hint">{{ __('public.account_staff_hint') }}</p>
        <a class="public-button" href="/desktop">{{ __('public.account_staff_link') }} →</a>
    </section>
</div>
@else
<div class="public-account-page">
    <header class="public-account-intro">
        <div>
            <p class="public-account-eyebrow">{{ __('public.account_eyebrow') }}</p>
            <h1>{{ __('public.account') }}</h1>
            <p class="public-account-identity"><strong>{{ auth()->user()->name }}</strong><span>{{ auth()->user()->email }}</span></p>
        </div>
        <span class="public-account-badge">{{ auth()->user()->email_verified_at ? __('public.account_verified_label') : __('public.account_unverified_label') }}</span>
    </header>

    @if($requiresEmailVerification)
    <section class="public-account-notice" role="status">
        <span class="public-account-notice-icon">!</span>
        <div><strong>{{ __('public.account_verify_title') }}</strong><p>{{ __('public.account_verify') }}</p></div>
        <form method="post" action="{{ route('public.account-resend') }}">@csrf<button class="public-button">{{ __('public.account_resend') }}</button></form>
    </section>
    @endif

    <div class="public-account-grid">
        <section class="public-panel public-account-section public-account-activity">
            <div class="public-account-section-heading"><div><p class="public-account-eyebrow">{{ __('public.account_activity_eyebrow') }}</p><h2>{{ __('public.account_activity') }}</h2></div><span class="public-account-count">{{ $states->total() }}</span></div>
            <div class="public-account-list">
                @forelse($items as $item)
                <article class="public-account-item">
                    <span class="public-account-item-icon">@include('public.icon', ['name' => $item['state']->action === 'bookmark' ? 'book' : 'video'])</span>
                    <div class="public-account-item-copy"><a href="{{ $item['card']['url'] }}">{{ $item['card']['title'] }}</a><small>{{ __('public.'.$item['state']->action) }}
                        @if(($position = $item['state']->value['position'] ?? null) !== null)
                            · {{ __('public.account_progress_position', ['position' => gmdate('i:s', (int) $position)]) }}
                        @endif
                    </small></div>
                    <form method="post" action="{{ route('public.account-state-remove', $item['state']) }}">@csrf @method('DELETE')<button class="public-icon-button" aria-label="{{ __('public.account_remove') }}" title="{{ __('public.account_remove') }}">×</button></form>
                </article>
                @empty
                    @include('public.empty', ['hint' => __('public.account_activity_empty')])
                @endforelse
            </div>
            @if($states->hasPages())
                <nav class="public-account-pagination" aria-label="{{ __('public.pagination') }}">{{ $states->links() }}</nav>
            @endif
        </section>

        <section class="public-panel public-account-section public-account-reminders">
            <div class="public-account-section-heading"><div><p class="public-account-eyebrow">{{ __('public.account_reminders_eyebrow') }}</p><h2>{{ __('public.account_push') }}</h2></div><span class="public-account-count">{{ $push->count() }}</span></div>
            <div class="public-account-list">
                @forelse($push as $entry)
                <article class="public-account-item"><span class="public-account-item-icon">@include('public.icon', ['name' => 'calendar'])</span><div class="public-account-item-copy"><a href="{{ $entry['card']['url'] }}">{{ $entry['card']['title'] }}</a><small>{{ $entry['card']['meta'] ?: __('public.account_reminder_active') }}</small></div><form method="post" action="{{ route('public.account-push-cancel', $entry['subscription']) }}">@csrf @method('DELETE')<button class="public-icon-button" aria-label="{{ __('public.account_remove') }}" title="{{ __('public.account_remove') }}">×</button></form></article>
                @empty
                    @include('public.empty', ['hint' => __('public.account_reminders_empty')])
                @endforelse
            </div>
        </section>
    </div>

    <div class="public-account-history-grid">
        <section class="public-panel public-account-history">
            <div class="public-account-section-heading"><div><p class="public-account-eyebrow">{{ __('public.account_purchases') }}</p><h2>{{ __('public.account_purchases') }}</h2></div><span class="public-account-count">{{ $purchases->total() }}</span></div>
            <div class="public-account-history-list">
                @forelse($purchases as $purchase)
                <article class="public-account-history-item"><strong><a href="{{ $purchase['url'] }}">{{ $purchase['title'] }}</a></strong><small>{{ __('public.account_order_status', ['status' => $purchase['statusLabel']]) }} · {{ number_format($purchase['sale']->amount_cents / 100, 2, ',', '.') }} {{ $purchase['sale']->currency }} · {{ $purchase['sale']->created_at?->format('d.m.Y') }}</small></article>
                @empty
                    @include('public.empty', ['hint' => __('public.account_purchases_empty')])
                @endforelse
            </div>
            @if($purchases->hasPages())
                <nav class="public-account-pagination" aria-label="{{ __('public.pagination') }}">{{ $purchases->links() }}</nav>
            @endif
        </section>

        <section class="public-panel public-account-history">
            <div class="public-account-section-heading"><div><p class="public-account-eyebrow">{{ __('public.account_subscriptions') }}</p><h2>{{ __('public.account_subscriptions') }}</h2></div><span class="public-account-count">{{ $subscriptions->count() }}</span></div>
            <div class="public-account-history-list">
                @forelse($subscriptions as $entry)
                <article class="public-account-history-item"><strong>{{ __('public.account_subscription_newsletter') }}</strong><small>{{ $entry['statusLabel'] }} · {{ $entry['subscription']->created_at?->format('d.m.Y') }}</small></article>
                @empty
                    @include('public.empty', ['hint' => __('public.account_subscriptions_empty')])
                @endforelse
            </div>
        </section>

        <section class="public-panel public-account-history">
            <div class="public-account-section-heading"><div><p class="public-account-eyebrow">{{ __('public.account_messages') }}</p><h2>{{ __('public.account_messages') }}</h2></div><span class="public-account-count">{{ $messages->total() }}</span></div>
            <div class="public-account-history-list">
                @forelse($messages as $message)
                <article class="public-account-history-item"><strong><a href="{{ $message['url'] }}">{{ $message['title'] }}</a></strong><small>{{ $message['kindLabel'] }} · {{ $message['statusLabel'] }} · {{ $message['date']?->format('d.m.Y H:i') }}</small><p>{{ \Illuminate\Support\Str::limit($message['body'] ?? '', 180) }}</p></article>
                @empty
                    @include('public.empty', ['hint' => __('public.account_messages_empty')])
                @endforelse
            </div>
            @if($messages->hasPages())
                <nav class="public-account-pagination" aria-label="{{ __('public.pagination') }}">{{ $messages->links() }}</nav>
            @endif
        </section>

        <section class="public-panel public-account-history">
            <div class="public-account-section-heading"><div><p class="public-account-eyebrow">{{ __('public.account_reviews') }}</p><h2>{{ __('public.account_reviews') }}</h2></div><span class="public-account-count">{{ $reviews->total() }}</span></div>
            <div class="public-account-history-list">
                @forelse($reviews as $entry)
                <article class="public-account-history-item"><strong><a href="{{ $entry['url'] }}">{{ $entry['title'] }}</a></strong><small>{{ $entry['review']->rating }}/5 · {{ $entry['statusLabel'] }} · {{ $entry['review']->created_at?->format('d.m.Y') }}</small><p>{{ \Illuminate\Support\Str::limit($entry['review']->body, 180) }}</p></article>
                @empty
                    @include('public.empty', ['hint' => __('public.account_reviews_empty')])
                @endforelse
            </div>
            @if($reviews->hasPages())
                <nav class="public-account-pagination" aria-label="{{ __('public.pagination') }}">{{ $reviews->links() }}</nav>
            @endif
        </section>

        <section class="public-panel public-account-history">
            <div class="public-account-section-heading"><div><p class="public-account-eyebrow">{{ __('public.account_ai_chats') }}</p><h2>{{ __('public.account_ai_chats') }}</h2></div><span class="public-account-count">{{ $aiChats->total() }}</span></div>
            <div class="public-account-history-list">
                @forelse($aiChats as $entry)
                <article class="public-account-history-item"><strong>{{ $entry['entry']->question }}</strong><small>{{ $entry['statusLabel'] }} · {{ $entry['entry']->created_at?->format('d.m.Y H:i') }}</small><p>{{ \Illuminate\Support\Str::limit($entry['entry']->answer ?? '', 220) }}</p>
                    @if($entry['card'])
                        <a class="public-account-history-link" href="{{ $entry['card']['url'] }}">{{ $entry['card']['title'] }} →</a>
                    @endif
                </article>
                @empty
                    @include('public.empty', ['hint' => __('public.account_ai_empty')])
                @endforelse
            </div>
            @if($aiChats->hasPages())
                <nav class="public-account-pagination" aria-label="{{ __('public.pagination') }}">{{ $aiChats->links() }}</nav>
            @endif
        </section>
    </div>

    <section class="public-panel public-account-profile">
        <div class="public-account-section-heading"><div><p class="public-account-eyebrow">{{ __('public.account_profile_eyebrow') }}</p><h2>{{ __('public.account_profile') }}</h2></div></div>
        <p class="public-account-form-hint">{{ __('public.account_password_hint') }}</p>
        <form class="public-account-form" method="post" action="{{ route('public.account-profile') }}">@csrf @method('PATCH')<label>{{ __('ui.name') }}<input name="name" required maxlength="120" value="{{ auth()->user()->name }}"></label><label>{{ __('public.current_password') }}<input name="current_password" type="password" required autocomplete="current-password"></label><label>{{ __('public.new_password') }}<input name="password" type="password" minlength="12" maxlength="72" autocomplete="new-password"></label><label>{{ __('public.password_confirm') }}<input name="password_confirmation" type="password" autocomplete="new-password"></label><div class="public-account-form-actions"><button class="public-button">{{ __('ui.save') }}</button></div></form>
        <form method="post" action="/logout">@csrf<button class="public-button public-button-secondary" type="submit">{{ __('ui.logout') }}</button></form>
    </section>
</div>
@endif
@endsection
