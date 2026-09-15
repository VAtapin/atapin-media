@php($settingsNotice = session('desktop_error') ?: session('status'))
<section class="desktop-settings" data-settings-app data-settings-direct data-active-section="{{ session('saved_section', 'desktop_design') }}" data-connection-saved="{{ __('social.setup_saved') }}" data-connection-copied="{{ __('social.copied') }}" data-connection-copy-failed="{{ __('social.copy_failed') }}" data-connection-requires-save="{{ __('social.connect_requires_save') }}" data-social-edit="{{ __('social.edit') }}" data-social-connect="{{ __('social.connect_oauth') }}" data-social-check="{{ __('social.check_connection') }}" data-social-disconnect="{{ __('social.disconnect') }}" data-social-account-label="{{ __('social.account_id') }}" data-stripe-tested="{{ __('stripe.test_succeeded') }}" data-stripe-save-before-test="{{ __('stripe.save_before_test') }}">
    <aside class="desktop-settings-nav" aria-label="{{ __('ui.settings') }}">
        <button type="button" data-settings-tab="profile"><span aria-hidden="true">●</span>Mein Profil</button>
        @can('settings.manage')
        <button type="button" data-settings-tab="desktop_design"><span aria-hidden="true">◈</span>Desktop &amp; Design</button>
        <button type="button" data-settings-tab="ai"><span aria-hidden="true">✦</span>KI</button>
        <button type="button" data-settings-tab="media_appearance"><span aria-hidden="true">◉</span>{{ __('ui.settings_website_author') }}</button>
        @endcan
        @can('integrations.manage')
        <button type="button" data-settings-tab="social"><span aria-hidden="true">◌</span>Social Media</button>
        <button type="button" data-settings-tab="integrations"><span aria-hidden="true">⌘</span>Integrationen</button>
        @endcan
        @can('content.publish')
        <button type="button" data-settings-tab="publishing"><span aria-hidden="true">↗</span>Publishing</button>
        @endcan
        @can('users.manage')
        <button type="button" data-settings-tab="users"><span aria-hidden="true">♙</span>Benutzer &amp; Rechte</button>
        @endcan
        @can('settings.manage')
        <button type="button" data-settings-tab="system"><span aria-hidden="true">⚙</span>System</button>
        @endcan
    </aside>

    <div class="desktop-settings-workspace">
        @can('settings.manage')@include('desktop.media-appearance')@endcan
        <div class="desktop-settings-notice {{ session('desktop_error') ? 'error' : 'success' }}" data-settings-notice @if(!$settingsNotice) hidden @endif role="status">{{ $settingsNotice }}</div>

        <section class="desktop-settings-panel" data-settings-panel="profile" hidden>
            <header class="desktop-settings-heading"><div><span>Konto</span><h2>Mein Profil</h2><p>Persönliche Angaben und Links. Diese Daten sind unabhängig von den offiziellen Kanälen des Projekts.</p></div></header>
            @php($profile = $currentUser->profile)
            @php($profileLinks = $profile?->social_links ?? [])
            <form class="desktop-settings-form" data-profile-form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data">@csrf @method('PATCH')
                <div class="desktop-settings-profile-header"><img data-profile-avatar src="{{ $profile?->avatar_path ? route('profile.avatar').'?v='.$profile->updated_at->timestamp : '/assets/brand/owner/logo-mark.png' }}" alt=""><label>Profilfoto<input name="avatar" type="file" accept="image/png,image/jpeg,image/webp" data-image-upload-profile="avatar"><small data-image-upload-status>PNG, JPEG oder WebP. Wird automatisch auf Avatar-Größe optimiert.</small></label></div>
                <div class="desktop-settings-grid two"><label>{{ __('ui.name') }}<input name="name" required maxlength="255" value="{{ old('name', $currentUser->name) }}"></label><label>{{ __('ui.email') }}<input name="email" type="email" required maxlength="255" value="{{ old('email', $currentUser->email) }}"></label><label>Telefon<input name="phone" maxlength="80" value="{{ old('phone', $profile?->phone) }}"></label><label>Ort<input name="location" maxlength="120" value="{{ old('location', $profile?->location) }}"></label></div><label>Persönliche Website<input name="website" type="url" maxlength="1000" value="{{ old('website', $profile?->website) }}"></label><label>Über mich<textarea name="bio" rows="4" maxlength="3000">{{ old('bio', $profile?->bio) }}</textarea></label><div class="desktop-settings-divider">Meine persönlichen Social-Media-Links</div><div class="desktop-settings-grid three"><label>YouTube<input name="personal_youtube" type="url" value="{{ old('personal_youtube', $profileLinks['youtube'] ?? '') }}"></label><label>Facebook<input name="personal_facebook" type="url" value="{{ old('personal_facebook', $profileLinks['facebook'] ?? '') }}"></label><label>Instagram<input name="personal_instagram" type="url" value="{{ old('personal_instagram', $profileLinks['instagram'] ?? '') }}"></label><label>TikTok<input name="personal_tiktok" type="url" value="{{ old('personal_tiktok', $profileLinks['tiktok'] ?? '') }}"></label><label>Telegram<input name="personal_telegram" type="url" value="{{ old('personal_telegram', $profileLinks['telegram'] ?? '') }}"></label><label>LinkedIn<input name="personal_linkedin" type="url" value="{{ old('personal_linkedin', $profileLinks['linkedin'] ?? '') }}"></label></div><div class="desktop-settings-divider">Passwort ändern</div><div class="desktop-settings-grid three"><label>Aktuelles Passwort<input name="current_password" type="password" autocomplete="current-password"></label><label>Neues Passwort<input name="password" type="password" minlength="5" autocomplete="new-password"></label><label>Neues Passwort wiederholen<input name="password_confirmation" type="password" minlength="5" autocomplete="new-password"></label></div><div class="desktop-settings-actions"><button class="desktop-settings-primary" data-settings-save>{{ __('ui.save') }}</button></div>
            </form>
        </section>

        @can('settings.manage')
        <section class="desktop-settings-panel" data-settings-panel="desktop_design">
            <header class="desktop-settings-heading"><div><span>Desktop</span><h2>Desktop &amp; Design</h2><p>{{ __('ui.desktop_appearance_hint') }}</p></div></header>
            <form class="desktop-settings-form" method="post" action="{{ route('settings') }}" enctype="multipart/form-data">
                @csrf @method('PUT')
                <input type="hidden" name="section" value="desktop_design">
                <div class="desktop-settings-grid three">
                    <label>{{ __('ui.icon_set') }}<select name="desktop_icon_set">@foreach(config('desktop.icon_sets') as $key=>$set)<option value="{{ $key }}" @selected(old('desktop_icon_set',$settings['desktop_icon_set'] ?? 'manna')===$key)>{{ __('ui.'.$set['label_key']) }}</option>@endforeach</select></label>
                    <label>{{ __('ui.wallpaper') }}<select name="desktop_wallpaper">@foreach(config('desktop.wallpapers') as $key=>$wallpaper)<option value="{{ $key }}" data-wallpaper-url="{{ $wallpaper['path'] ?? '' }}" @selected(old('desktop_wallpaper',$settings['desktop_wallpaper'] ?? 'mountains')===$key)>{{ __('ui.'.$wallpaper['label_key']) }}</option>@endforeach<option value="custom" data-wallpaper-url="" @selected(old('desktop_wallpaper',$settings['desktop_wallpaper'] ?? '')==='custom')>{{ __('ui.desktop_wallpaper_custom') }}</option></select></label>
                    <label>{{ __('ui.accent_color') }}<select name="desktop_accent">@foreach(config('desktop.accents') as $key=>$accent)<option value="{{ $key }}" @selected(old('desktop_accent',$settings['desktop_accent'] ?? 'gold')===$key)>{{ __('ui.'.$accent['label_key']) }}</option>@endforeach</select></label>
                </div>
                <div class="desktop-settings-grid three">
                    <label>{{ __('ui.desktop_density') }}<select name="desktop_density"><option value="comfortable" @selected(old('desktop_density',$settings['desktop_density'] ?? 'comfortable')==='comfortable')>{{ __('ui.desktop_density_comfortable') }}</option><option value="compact" @selected(old('desktop_density',$settings['desktop_density'] ?? '')==='compact')>{{ __('ui.desktop_density_compact') }}</option></select></label>
                    <label>{{ __('ui.desktop_shortcut_layout') }}<select name="desktop_shortcut_layout"><option value="free" @selected(old('desktop_shortcut_layout',$settings['desktop_shortcut_layout'] ?? 'free')==='free')>{{ __('ui.desktop_shortcut_layout_free') }}</option><option value="grid" @selected(old('desktop_shortcut_layout',$settings['desktop_shortcut_layout'] ?? '')==='grid')>{{ __('ui.desktop_shortcut_layout_grid') }}</option></select></label>
                    <label>{{ __('ui.desktop_ui_scale') }}<select data-ui-scale><option value="90">90%</option><option value="100" selected>100%</option><option value="110">110%</option><option value="120">120%</option><option value="130">130%</option></select></label>
                    <label class="desktop-settings-check"><input type="checkbox" name="desktop_effects" value="1" @checked(old('desktop_effects',$settings['desktop_effects'] ?? true))><span>{{ __('ui.desktop_effects') }}</span></label>
                </div>
                <label class="desktop-settings-upload">{{ __('ui.desktop_custom_wallpaper') }}<input name="desktop_custom_wallpaper" type="file" accept="image/png,image/jpeg,image/webp" data-image-upload-profile="wallpaper"><small data-image-upload-status>{{ __('ui.desktop_custom_wallpaper_hint') }} Automatisch auf maximal 2560 × 1440 und 3 MB optimiert.</small></label>
                <div class="desktop-settings-actions"><button class="desktop-settings-primary" data-settings-save>{{ __('ui.save') }}</button><button class="desktop-settings-secondary" name="reset_wallpaper" value="1">{{ __('ui.reset_standard_wallpaper') }}</button></div>
            </form>
        </section>

        <section class="desktop-settings-panel" data-settings-panel="ai" hidden>
            <header class="desktop-settings-heading"><div><span>KI</span><h2>Künstliche Intelligenz</h2><p>{{ __('ui.ai_hint') }}</p></div></header>
            <form class="desktop-settings-form" method="post" action="{{ route('settings') }}">@csrf @method('PUT')<input type="hidden" name="section" value="ai">
                <div class="desktop-settings-grid two"><label>{{ __('ui.provider') }}<select name="ai_provider">@foreach(['none'=>'—','openai'=>'OpenAI','anthropic'=>'Anthropic','azure'=>'Azure OpenAI'] as $key=>$label)<option value="{{ $key }}" @selected(old('ai_provider',$settings['ai_provider'] ?? 'none')===$key)>{{ $label }}</option>@endforeach</select></label><label>{{ __('ui.model') }}<input name="ai_model" maxlength="120" value="{{ old('ai_model',$settings['ai_model'] ?? '') }}"></label></div>
                <label>{{ __('ui.api_key') }} @if($secretStatus['ai_api_key'])<small>{{ __('ui.secret_saved') }}</small>@endif<input name="ai_api_key" type="password" autocomplete="new-password" placeholder="{{ __('ui.leave_empty_to_keep') }}"></label>
                <label class="desktop-settings-check"><input type="checkbox" name="ai_auto_classify" value="1" @checked(old('ai_auto_classify',$settings['ai_auto_classify'] ?? true))><span>{{ __('imports.ai_auto') }}</span></label>
                <label class="desktop-settings-check"><input type="checkbox" name="ai_chat_enabled" value="1" @checked($settings['ai_chat_enabled']??false)><span>{{ __('public.ai_chat_enable') }}</span></label>
                <small>{{ __('ui.ai_usage_hint') }}</small>
                <details class="desktop-settings-subsection"><summary>{{ __('ui.settings_ai_images') }}</summary><label>{{ __('imports.cover_style') }}<textarea name="cover_style_prompt" rows="4" maxlength="4000">{{ $settings['cover_style_prompt']??\App\Services\AiCoverGenerator::STYLE }}</textarea></label><label>{{ __('imports.image_model') }}<input name="ai_image_model" maxlength="120" value="{{ $settings['ai_image_model']??'gpt-image-2.5-flare' }}"></label></details>
                <label class="desktop-settings-check"><input type="checkbox" name="ai_enabled" value="1" @checked(old('ai_enabled',$settings['ai_enabled'] ?? false))><span>{{ __('ui.enable_ai') }}</span></label><div class="desktop-settings-actions"><button class="desktop-settings-primary" data-settings-save>{{ __('ui.save') }}</button></div>
            </form>
        </section>
        @endcan

        @can('integrations.manage')
        <section class="desktop-settings-panel" data-settings-panel="social" hidden>
            <header class="desktop-settings-heading"><div><span>Social</span><h2>Social Media</h2><p>{{ __('ui.social_hint') }}</p></div></header>
            @php($socialConnections = $settings['social_connections'] ?? [])
            @php($socialEditor = app(\App\Services\Publishing\SocialConnections::class)->editor())
            @php($metaPending = session('publishing.meta.oauth_pending'))
            @if(is_array($metaPending) && !empty($metaPending['pages']))
            <form class="desktop-settings-form desktop-settings-meta-select" data-meta-page-select method="post" action="{{ route('desktop.publishing.meta.select') }}">@csrf
                <header><h3>{{ __('social.select_page') }}</h3><p>{{ __('social.select_page_hint') }}</p></header>
                <div class="desktop-settings-meta-choice"><label>{{ __('social.select_page') }}<select name="page_id" required>@foreach($metaPending['pages'] as $page)<option value="{{ $page['id'] }}">{{ $page['name'] }} · {{ !empty($page['instagram']['username']) ? '@'.$page['instagram']['username'] : __('social.page_without_instagram') }}</option>@endforeach</select></label><button class="desktop-settings-primary">{{ __('social.use_page') }}</button></div>
            </form>
            @endif
            <div class="desktop-settings-connection-list" data-connection-list="social">
                @forelse($socialConnections as $connection)
                @php($connectionEditor = $socialEditor[$connection['provider']] ?? null)
                <div class="desktop-settings-connection-row">
                    <button type="button" class="desktop-settings-connection" data-connection-open="social" data-provider="{{ $connection['provider'] }}" data-public-url="{{ $connection['public_url'] ?? '' }}" data-external-id="{{ $connection['external_id'] ?? '' }}" data-bot-username="{{ $connection['bot_username'] ?? '' }}" data-mini-app-enabled="{{ !empty($connection['mini_app_enabled']) ? '1' : '0' }}"><strong>{{ $connectionEditor['label'] ?? ucfirst($connection['provider']) }}</strong>@if(!empty($connection['external_id']))<span class="desktop-settings-connection-account"><b>{{ $connection['display_name'] ?? $connection['public_url'] ?? $connection['external_id'] }}</b><small>{{ __('social.account_id') }}: {{ $connection['external_id'] }}</small></span>@endif<span class="desktop-settings-status is-{{ $connectionEditor['status'] ?? 'not_configured' }}" data-connection-status="{{ $connection['provider'] }}" @if(in_array($connection['provider'], ['facebook','instagram'], true)) data-meta-status="{{ $connection['provider'] }}" @endif>{{ $connectionEditor['status_label'] ?? __('social.status_not_configured') }}</span><small>{{ __('social.edit') }}</small></button>
                    @if($connectionEditor && ((!empty($connectionEditor['oauth_configured']) && empty($connectionEditor['connected'])) || (!empty($connectionEditor['connected']) && (!empty($connectionEditor['check_url']) || !empty($connectionEditor['disconnect_url'])))))
                    <div class="desktop-settings-connection-actions">
                        @if(!empty($connectionEditor['oauth_configured']) && empty($connectionEditor['connected']))<a class="desktop-settings-secondary" href="{{ $connectionEditor['oauth_url'] }}">{{ __('social.connect_oauth') }}</a>@endif
                        @if(!empty($connectionEditor['connected']) && !empty($connectionEditor['check_url']))<button type="button" class="desktop-settings-secondary" data-social-action="check" data-social-url="{{ $connectionEditor['check_url'] }}" data-status-providers="{{ in_array($connection['provider'], ['facebook','instagram'], true) ? 'facebook,instagram' : $connection['provider'] }}" @if(in_array($connection['provider'], ['facebook','instagram'], true)) data-meta-action="check" data-meta-url="{{ $connectionEditor['check_url'] }}" @endif>{{ __('social.check_connection') }}</button>@endif
                        @if(!empty($connectionEditor['connected']) && !empty($connectionEditor['disconnect_url']))<button type="button" class="desktop-settings-secondary" data-social-action="disconnect" data-social-url="{{ $connectionEditor['disconnect_url'] }}" data-status-providers="{{ in_array($connection['provider'], ['facebook','instagram'], true) ? 'facebook,instagram' : $connection['provider'] }}" @if(in_array($connection['provider'], ['facebook','instagram'], true)) data-meta-action="disconnect" data-meta-url="{{ $connectionEditor['disconnect_url'] }}" @endif>{{ __('social.disconnect') }}</button>@endif
                    </div>
                    @endif
                </div>
                @empty<p class="desktop-settings-empty">Noch kein soziales Netzwerk eingerichtet.</p>@endforelse
            </div>
            <button type="button" class="desktop-settings-secondary desktop-settings-add" data-connection-add="social">Soziales Netzwerk hinzufügen</button>
            <form class="desktop-settings-form desktop-settings-connection-form" data-connection-form="social" data-social-definitions='@json($socialEditor)' method="post" action="{{ route('settings') }}" hidden>@csrf @method('PUT')<input type="hidden" name="section" value="social"><input type="hidden" name="provider" value="youtube">
                <div class="desktop-settings-grid three">
                    <label>{{ __('social.network') }}<select data-provider-select="social">@foreach($socialEditor as $provider => $definition)<option value="{{ $provider }}">{{ $definition['label'] }}</option>@endforeach</select></label>
                    <label data-provider-field="public_url"><span data-provider-label></span> <small>{{ __('social.optional') }}</small><input name="public_url" type="url" maxlength="1000"></label>
                    <label data-provider-field="external_id"><span data-provider-label></span><input name="external_id" maxlength="255"></label>
                </div>
                <p data-connection-hint></p>
                <label data-connection-redirect hidden><span>{{ __('social.redirect_uri') }}</span><div class="desktop-settings-copy"><input data-connection-redirect-uri readonly><button type="button" class="desktop-settings-secondary" data-connection-copy>{{ __('social.copy') }}</button></div><small>{{ __('social.redirect_uri_hint') }}</small></label>
                <div class="desktop-settings-grid two">
                    <label data-provider-field="api_key"><span data-provider-label></span><input name="api_key" type="password" autocomplete="new-password" maxlength="4000" placeholder="{{ __('social.keep_secret') }}"></label>
                    <label data-provider-field="access_token"><span data-provider-label></span><input name="access_token" type="password" autocomplete="new-password" maxlength="4000" placeholder="{{ __('social.keep_secret') }}"></label>
                    <label data-provider-field="oauth_client_id"><span data-provider-label></span><input name="oauth_client_id" autocomplete="off" maxlength="4000" placeholder="{{ __('social.keep_secret') }}"></label>
                    <label data-provider-field="oauth_client_secret"><span data-provider-label></span><input name="oauth_client_secret" type="password" autocomplete="new-password" maxlength="4000" placeholder="{{ __('social.keep_secret') }}"></label>
                    <label data-provider-field="bot_username"><span data-provider-label></span><input name="bot_username" maxlength="32" pattern="[a-zA-Z][a-zA-Z0-9_]{4,31}"></label>
                    <div data-provider-field="mini_app_enabled"><label><input type="hidden" name="mini_app_enabled" value="0"><input type="checkbox" name="mini_app_enabled" value="1"><span data-provider-label></span></label><p>{{ __('social.mini_app_entry') }}: <a href="{{ route('home') }}" target="_blank" rel="noopener">{{ route('home') }}</a></p></div>
                </div>
                <div class="desktop-settings-actions"><a class="desktop-settings-secondary" data-connection-oauth hidden aria-disabled="true">{{ __('social.connect_oauth') }}</a><button class="desktop-settings-primary" data-settings-save>{{ __('ui.save') }}</button></div>
            </form>
        </section>
        @endcan

        @can('content.publish')
        <section class="desktop-settings-panel" data-settings-panel="publishing" hidden>
            <header class="desktop-settings-heading"><div><span>Workflow</span><h2>Publishing</h2><p>{{ __('ui.publishing_hint') }}</p></div></header>
            <form class="desktop-settings-form" method="post" action="{{ route('settings') }}">@csrf @method('PUT')<input type="hidden" name="section" value="publishing">
                <div class="desktop-settings-grid two"><label>{{ __('ui.default_visibility') }}<select name="publishing_default_visibility">@foreach(['private'=>__('ui.visibility_private'),'internal'=>__('ui.visibility_internal'),'public'=>__('ui.visibility_public')] as $key=>$label)<option value="{{ $key }}" @selected(old('publishing_default_visibility',$settings['publishing_default_visibility'] ?? 'internal')===$key)>{{ $label }}</option>@endforeach</select></label><label>{{ __('ui.default_timezone') }}<input name="publishing_default_timezone" value="{{ old('publishing_default_timezone',$settings['publishing_default_timezone'] ?? $settings['system_timezone'] ?? config('platform.timezone')) }}"></label></div>
                <label class="desktop-settings-check"><input type="checkbox" name="publishing_approval_required" value="1" @checked(old('publishing_approval_required',$settings['publishing_approval_required'] ?? true))><span>{{ __('ui.publishing_approval_required') }}</span></label><label class="desktop-settings-check"><input type="checkbox" name="publishing_automation_enabled" value="1" @checked(old('publishing_automation_enabled',$settings['publishing_automation_enabled'] ?? false))><span>{{ __('ui.publishing_automation_enabled') }}</span></label><div class="desktop-settings-actions"><button class="desktop-settings-primary" data-settings-save>{{ __('ui.save') }}</button></div>
            </form>
        </section>
        @endcan

        @can('integrations.manage')
        <section class="desktop-settings-panel" data-settings-panel="integrations" hidden>
            <header class="desktop-settings-heading"><div><span>Verbindungen</span><h2>Integrationen</h2><p>{{ __('ui.integrations_hint') }}</p></div></header>
            @php($integrationConnections = $settings['integration_connections'] ?? [])
            <form class="desktop-settings-form desktop-settings-stripe" data-stripe-settings data-connection-form="stripe" data-stripe-test-url="{{ route('settings.integrations.stripe.test') }}" data-stripe-can-test="{{ $stripeEditor['can_test'] ? '1' : '0' }}" method="post" action="{{ route('settings') }}">@csrf @method('PUT')<input type="hidden" name="section" value="integrations"><input type="hidden" name="provider" value="stripe">
                <header class="desktop-settings-stripe-header"><div><h3>Stripe</h3><p>{{ __('stripe.hint') }}</p></div><span class="desktop-settings-status is-{{ $stripeEditor['status'] }}" data-stripe-status>{{ $stripeEditor['status_label'] }}</span></header>
                <div class="desktop-settings-grid two"><label>{{ __('stripe.mode') }}<select name="mode"><option value="test" @selected($stripeEditor['mode']==='test')>{{ __('stripe.mode_test') }}</option><option value="live" @selected($stripeEditor['mode']==='live')>{{ __('stripe.mode_live') }}</option></select></label></div>
                <div class="desktop-settings-divider">API</div><div class="desktop-settings-grid two">
                    <label>{{ __('stripe.publishable_key') }}<input name="publishable_key" type="password" autocomplete="new-password" maxlength="255" placeholder="{{ $stripeEditor['publishable_key_saved'] ? __('stripe.saved_publishable') : 'pk_test_…' }}"></label>
                    <label>{{ __('stripe.secret_key') }}<input name="api_key" type="password" autocomplete="new-password" maxlength="2000" placeholder="{{ $stripeEditor['api_key_saved'] ? __('stripe.saved_secret') : 'sk_test_…' }}"></label>
                </div>
                <div class="desktop-settings-divider">Webhook</div><label>{{ __('stripe.webhook_url') }}<div class="desktop-settings-copy"><input id="stripe-webhook-url" value="{{ $stripeEditor['webhook_url'] }}" readonly><button type="button" class="desktop-settings-secondary" data-connection-copy data-copy-target="stripe-webhook-url">{{ __('stripe.copy') }}</button></div></label>
                <label>{{ __('stripe.signing_secret') }}<input name="webhook_secret" type="password" autocomplete="new-password" maxlength="2000" placeholder="{{ $stripeEditor['webhook_secret_saved'] ? __('stripe.saved_signing') : 'whsec_…' }}"></label>
                <div class="desktop-settings-stripe-events"><strong>{{ __('stripe.events') }}</strong>@foreach(['checkout.session.completed','checkout.session.async_payment_succeeded','checkout.session.async_payment_failed','checkout.session.expired','charge.refunded'] as $event)<span>✓ {{ $event }}</span>@endforeach</div>
                <p class="desktop-settings-stripe-feedback" data-stripe-feedback hidden role="status"></p>
                <div class="desktop-settings-actions"><button type="button" class="desktop-settings-secondary" data-stripe-test @disabled(!$stripeEditor['can_test'])>{{ __('stripe.test_connection') }}</button><button class="desktop-settings-primary" data-settings-save>{{ __('ui.save') }}</button></div>
            </form>
            @php($otherIntegrationConnections = collect($integrationConnections)->reject(fn($connection) => ($connection['provider'] ?? null) === 'stripe'))
            <div class="desktop-settings-connection-list" data-connection-list="integrations">@forelse($otherIntegrationConnections as $connection)<button type="button" class="desktop-settings-connection" data-connection-open="integrations" data-provider="{{ $connection['provider'] }}" data-public-url="{{ $connection['public_url'] ?? '' }}" data-external-id="{{ $connection['external_id'] ?? '' }}"><strong>{{ str_replace('_', ' ', ucfirst($connection['provider'])) }}</strong><span>{{ $connection['public_url'] ?? __('ui.secret_saved') }}</span></button>@empty<p class="desktop-settings-empty">{{ __('stripe.no_other_integrations') }}</p>@endforelse</div>
            <button type="button" class="desktop-settings-secondary desktop-settings-add" data-connection-add="integrations">Integration hinzufügen</button>
            <form class="desktop-settings-form desktop-settings-connection-form" data-connection-form="integrations" method="post" action="{{ route('settings') }}" hidden>@csrf @method('PUT')<input type="hidden" name="section" value="integrations"><input type="hidden" name="provider" value="google_drive"><div class="desktop-settings-grid three"><label>Service<select data-provider-select="integrations"><option value="google_drive">Google Drive</option><option value="google_calendar">Google Calendar</option><option value="google_analytics">Google Analytics</option><option value="mailchimp">Mailchimp</option><option value="zapier">Zapier</option><option value="webhook">Webhook</option></select></label><label data-provider-field="public_url"><span data-provider-label="public_url">Service-URL</span> <small>optional</small><input name="public_url" type="url"></label><label data-provider-field="account_id"><span data-provider-label="account_id">Account / Projekt-ID</span><input name="account_id"></label></div><div class="desktop-settings-divider">Zugangsdaten</div><div class="desktop-settings-grid two"><label data-provider-field="api_key"><span data-provider-label="api_key">API-Key</span><input name="api_key" type="password" autocomplete="new-password"></label><label data-provider-field="oauth_client_id"><span data-provider-label="oauth_client_id">OAuth Client-ID</span><input name="oauth_client_id"></label><label data-provider-field="access_token"><span data-provider-label="access_token">Access Token</span><input name="access_token" type="password" autocomplete="new-password"></label><label data-provider-field="webhook_secret"><span data-provider-label="webhook_secret">Webhook Secret</span> <small>optional</small><input name="webhook_secret" type="password" autocomplete="new-password"></label></div><div class="desktop-settings-actions"><button class="desktop-settings-primary" data-settings-save>{{ __('ui.save') }}</button></div></form>
        </section>
        @endcan

        @can('users.manage')
        <section class="desktop-settings-panel" data-settings-panel="users" hidden>
            <header class="desktop-settings-heading"><div><span>Zugriff</span><h2>Benutzer &amp; Rechte</h2><p>{{ __('ui.users_hint') }}</p></div></header>
            <div class="desktop-settings-user-list"><div class="desktop-settings-subheading"><strong>Benutzer</strong><button type="button" class="desktop-settings-secondary" data-user-create>Benutzer hinzufügen</button></div>@foreach($users as $user)<div class="desktop-settings-user" data-user-summary="{{ $user->id }}"><span><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></span><small>{{ $user->roles->first()?->name }}</small><button type="button" class="desktop-settings-secondary" data-user-edit="{{ $user->id }}">Bearbeiten</button></div><form class="desktop-settings-form desktop-settings-user-edit" data-user-edit-form="{{ $user->id }}" method="post" action="{{ route('users.update', $user) }}" hidden>@csrf @method('PATCH')<div class="desktop-settings-grid three"><label>{{ __('ui.name') }}<input name="name" required maxlength="255" value="{{ $user->name }}"></label><label>{{ __('ui.email') }}<input name="email" type="email" required maxlength="255" value="{{ $user->email }}"></label><label>{{ __('ui.role') }}<select name="role_id">@foreach($roles as $role)<option value="{{ $role->id }}" @selected($user->roles->contains('id', $role->id))>{{ $role->name }}</option>@endforeach</select></label></div><div class="desktop-settings-grid two"><label>Neues Passwort <small>optional</small><input name="password" type="password" minlength="5" autocomplete="new-password"></label><label>Neues Passwort wiederholen<input name="password_confirmation" type="password" minlength="5" autocomplete="new-password"></label></div><div class="desktop-settings-actions"><button class="desktop-settings-primary" data-settings-save>{{ __('ui.save') }}</button><button type="button" class="desktop-settings-secondary" data-user-cancel="{{ $user->id }}">Abbrechen</button></div></form>@endforeach</div>
            <form class="desktop-settings-form desktop-settings-user-create" data-user-create-form method="post" action="{{ route('users.store') }}" hidden>@csrf<div class="desktop-settings-grid three"><label>{{ __('ui.name') }}<input name="name" required></label><label>{{ __('ui.email') }}<input name="email" type="email" required></label><label>{{ __('ui.password') }}<input name="password" type="password" required minlength="5"></label></div><label>{{ __('ui.role') }}<select name="role_id">@foreach($roles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach</select></label><div class="desktop-settings-actions"><button class="desktop-settings-primary" data-settings-save>Benutzer erstellen</button><button type="button" class="desktop-settings-secondary" data-user-create-cancel>Abbrechen</button></div></form>
            <div class="desktop-settings-role-picker"><label>{{ __('ui.role') }}<select data-role-picker><option value="">{{ __('ui.select_role') }}</option>@foreach($roles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach</select></label><button type="button" class="desktop-settings-secondary" data-role-create>{{ __('ui.new_role') }}</button></div>
            @foreach($roles as $role)
            <form class="desktop-settings-role-card" data-role-panel="{{ $role->id }}" method="post" action="{{ route('settings.roles.update',$role) }}" hidden>@csrf @method('PUT')<h3>{{ $role->name }}</h3><div class="desktop-settings-permissions">@foreach($permissions as $permission)<label class="desktop-settings-check"><input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked($role->permissions->contains('id',$permission->id))><span>{{ $permission->name }}</span></label>@endforeach</div><div class="desktop-settings-actions"><button class="desktop-settings-primary" data-settings-save>{{ __('ui.save') }}</button></div></form>
            @endforeach
            <form class="desktop-settings-form desktop-settings-role-card" data-role-create-form method="post" action="{{ route('settings.roles.store') }}" hidden>@csrf<label>{{ __('ui.new_role') }}<input name="name" required maxlength="80"></label><div class="desktop-settings-permissions">@foreach($permissions as $permission)<label class="desktop-settings-check"><input type="checkbox" name="permissions[]" value="{{ $permission->id }}"><span>{{ $permission->name }}</span></label>@endforeach</div><div class="desktop-settings-actions"><button class="desktop-settings-primary">{{ __('ui.create_role') }}</button></div></form>
        </section>
        @endcan

        @can('settings.manage')
        <section class="desktop-settings-panel" data-settings-panel="system" hidden>
            <header class="desktop-settings-heading"><div><span>Plattform</span><h2>System</h2><p>{{ __('ui.system_hint') }}</p></div></header>
            <form class="desktop-settings-form" method="post" action="{{ route('settings') }}">@csrf @method('PUT')<input type="hidden" name="section" value="system">
                <div class="desktop-settings-grid two"><label>{{ __('ui.site_name') }}<input name="site_name" required maxlength="120" value="{{ old('site_name',$settings['site_name'] ?? config('platform.brand')) }}"></label><label>{{ __('ui.contact_email') }}<input name="contact_email" type="email" value="{{ old('contact_email',$settings['contact_email'] ?? '') }}"></label></div>
                <label>{{ __('ui.site_description') }}<textarea name="site_description" rows="3" maxlength="500">{{ old('site_description',$settings['site_description'] ?? '') }}</textarea></label>
                <div class="desktop-settings-grid three"><label>{{ __('ui.language') }}<select name="system_locale">@foreach(config('platform.locales') as $locale)<option value="{{ $locale }}" @selected(old('system_locale',$settings['system_locale'] ?? config('app.locale'))===$locale)>{{ strtoupper($locale) }}</option>@endforeach</select></label><label>{{ __('ui.timezone') }}<input name="system_timezone" value="{{ old('system_timezone',$settings['system_timezone'] ?? config('platform.timezone')) }}"></label><label>{{ __('ui.branding') }}<input name="system_branding_name" maxlength="120" value="{{ old('system_branding_name',$settings['system_branding_name'] ?? '') }}"></label></div>
                <div class="desktop-settings-divider">Impressum, Datenschutz &amp; Redaktion</div>
                @php($legalDocuments = $settings['legal_documents'] ?? [])
                <label>Sprache dieser Inhalte<select name="legal_locale" data-legal-locale data-legal-documents="{{ e(json_encode($legalDocuments, JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG)) }}">@foreach(config('platform.locales') as $locale)<option value="{{ $locale }}">{{ strtoupper($locale) }}</option>@endforeach</select></label>
                <div class="desktop-settings-editor">
                    <label>Impressum<textarea name="impressum" data-rich-text data-legal-editor rows="9" maxlength="50000"></textarea></label>
                    <label>Datenschutz<textarea name="privacy_policy" data-rich-text data-legal-editor rows="9" maxlength="50000"></textarea></label>
                    <label>Redaktionelle Hinweise<textarea name="editorial_policy" data-rich-text data-legal-editor rows="9" maxlength="50000"></textarea></label>
                </div>
                <div class="desktop-settings-actions"><button class="desktop-settings-primary" data-settings-save>{{ __('ui.save') }}</button></div>
                <div class="desktop-settings-divider">{{ __('public.page_texts') }}</div>
                @foreach(['about_text'=>'ueber-uns','mission_text'=>'unsere-mission'] as $key=>$page)
                <div class="desktop-settings-editor"><label>{{ __('public.section_'.$page) }}<textarea name="{{ $key }}" data-rich-text data-legal-editor rows="9" maxlength="50000"></textarea></label></div>
                @endforeach
                <label>{{ __('public.community_guidelines') }}<textarea name="community_guidelines" data-rich-text rows="9" maxlength="10000">{{ $settings['community_guidelines']??'' }}</textarea></label>
                <button class="desktop-settings-primary" data-settings-save>{{ __('ui.save') }}</button>
            </form>
            <section data-contact-inbox>
                <h3>{{ __('public.contact_inbox') }} ({{ $contactInbox?->total()??0 }})</h3>
                <div data-contact-entries>
                @forelse($contactInbox??[] as $entry)<details><summary>{{ $entry->created_at->format('d.m.Y H:i') }} · {{ $entry->subject }} · {{ __('public.contact_delivery_'.$entry->delivery_status) }}</summary><p>{{ $entry->name }} · <a href="mailto:{{ $entry->email }}">{{ $entry->email }}</a></p><p style="white-space:pre-wrap">{{ $entry->body }}</p>@if(!$entry->delivered_at)<form method="post" action="{{ route('contact.retry',$entry) }}">@csrf<button type="submit">{{ __('public.contact_retry') }}</button></form>@endif</details>
                @empty<p>{{ __('public.no_data') }}</p>@endforelse
                </div>
                @if($contactInbox?->hasMorePages())<button type="button" data-contact-next="{{ route('contact.inbox',['page'=>2]) }}">{{ __('public.contact_more') }}</button>@endif
            </section>
        </section>
        @endcan
    </div>
</section>
