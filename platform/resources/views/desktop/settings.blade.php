<section class="desktop-settings" data-settings-app data-settings-direct data-active-section="desktop_design">
    <aside class="desktop-settings-nav" aria-label="{{ __('ui.settings') }}">
        <button type="button" data-settings-tab="desktop_design"><span aria-hidden="true">◈</span>Desktop &amp; Design</button>
        <button type="button" data-settings-tab="ai"><span aria-hidden="true">✦</span>KI</button>
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
        <button type="button" data-settings-tab="system"><span aria-hidden="true">⚙</span>System</button>
    </aside>

    <div class="desktop-settings-workspace">
        <div class="desktop-settings-notice" data-settings-notice hidden role="status"></div>

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
                    <label>{{ __('ui.desktop_ui_scale') }}<select data-ui-scale><option value="90">90%</option><option value="100" selected>100%</option><option value="110">110%</option><option value="120">120%</option><option value="130">130%</option></select></label>
                    <label class="desktop-settings-check"><input type="checkbox" name="desktop_effects" value="1" @checked(old('desktop_effects',$settings['desktop_effects'] ?? true))><span>{{ __('ui.desktop_effects') }}</span></label>
                </div>
                <label class="desktop-settings-upload">{{ __('ui.desktop_custom_wallpaper') }}<input name="desktop_custom_wallpaper" type="file" accept="image/png,image/jpeg,image/webp"><small>{{ __('ui.desktop_custom_wallpaper_hint') }}</small></label>
                <div class="desktop-settings-actions"><button class="desktop-settings-primary" data-settings-save>{{ __('ui.save') }}</button><button class="desktop-settings-secondary" name="reset_wallpaper" value="1">{{ __('ui.reset_standard_wallpaper') }}</button></div>
            </form>
        </section>

        <section class="desktop-settings-panel" data-settings-panel="ai" hidden>
            <header class="desktop-settings-heading"><div><span>KI</span><h2>Künstliche Intelligenz</h2><p>{{ __('ui.ai_hint') }}</p></div></header>
            <form class="desktop-settings-form" method="post" action="{{ route('settings') }}">@csrf @method('PUT')<input type="hidden" name="section" value="ai">
                <div class="desktop-settings-grid two"><label>{{ __('ui.provider') }}<select name="ai_provider">@foreach(['none'=>'—','openai'=>'OpenAI','anthropic'=>'Anthropic','azure'=>'Azure OpenAI'] as $key=>$label)<option value="{{ $key }}" @selected(old('ai_provider',$settings['ai_provider'] ?? 'none')===$key)>{{ $label }}</option>@endforeach</select></label><label>{{ __('ui.model') }}<input name="ai_model" maxlength="120" value="{{ old('ai_model',$settings['ai_model'] ?? '') }}"></label></div>
                <label>{{ __('ui.api_key') }} @if($secretStatus['ai_api_key'])<small>{{ __('ui.secret_saved') }}</small>@endif<input name="ai_api_key" type="password" autocomplete="new-password" placeholder="{{ __('ui.leave_empty_to_keep') }}"></label>
                <label class="desktop-settings-check"><input type="checkbox" name="ai_enabled" value="1" @checked(old('ai_enabled',$settings['ai_enabled'] ?? false))><span>{{ __('ui.enable_ai') }}</span></label><div class="desktop-settings-actions"><button class="desktop-settings-primary" data-settings-save>{{ __('ui.save') }}</button></div>
            </form>
        </section>

        @can('integrations.manage')
        <section class="desktop-settings-panel" data-settings-panel="social" hidden>
            <header class="desktop-settings-heading"><div><span>Social</span><h2>Social Media</h2><p>{{ __('ui.social_hint') }}</p></div></header>
            <form class="desktop-settings-form" method="post" action="{{ route('settings') }}">@csrf @method('PUT')<input type="hidden" name="section" value="social">
                <div class="desktop-settings-grid three"><label>YouTube<input name="youtube_channel" value="{{ old('youtube_channel',$settings['youtube_channel'] ?? '') }}"></label><label>Facebook<input name="facebook_page" value="{{ old('facebook_page',$settings['facebook_page'] ?? '') }}"></label><label>Instagram<input name="instagram_account" value="{{ old('instagram_account',$settings['instagram_account'] ?? '') }}"></label><label>TikTok<input name="tiktok_account" value="{{ old('tiktok_account',$settings['tiktok_account'] ?? '') }}"></label><label>Telegram<input name="telegram_chat_id" value="{{ old('telegram_chat_id',$settings['telegram_chat_id'] ?? '') }}"></label></div>
                <div class="desktop-settings-divider">Zugangsdaten für Veröffentlichungen</div>
                <div class="desktop-settings-grid three"><label>YouTube API-Key @if($secretStatus['youtube_api_key'])<small>{{ __('ui.secret_saved') }}</small>@endif<input name="youtube_api_key" type="password"></label><label>Facebook Token @if($secretStatus['facebook_access_token'])<small>{{ __('ui.secret_saved') }}</small>@endif<input name="facebook_access_token" type="password"></label><label>Instagram Token @if($secretStatus['instagram_access_token'])<small>{{ __('ui.secret_saved') }}</small>@endif<input name="instagram_access_token" type="password"></label><label>TikTok Token @if($secretStatus['tiktok_access_token'])<small>{{ __('ui.secret_saved') }}</small>@endif<input name="tiktok_access_token" type="password"></label><label>Telegram Bot Token @if($secretStatus['telegram_bot_token'])<small>{{ __('ui.secret_saved') }}</small>@endif<input name="telegram_bot_token" type="password"></label></div>
                <div class="desktop-settings-actions"><button class="desktop-settings-primary" data-settings-save>{{ __('ui.save') }}</button></div>
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
            <form class="desktop-settings-form" method="post" action="{{ route('settings') }}">@csrf @method('PUT')<input type="hidden" name="section" value="integrations">
                <div class="desktop-settings-grid two"><label>{{ __('ui.api_url') }}<input name="integration_api_url" type="url" value="{{ old('integration_api_url',$settings['integration_api_url'] ?? '') }}"></label><label>{{ __('ui.webhook_url') }}<input name="integration_webhook_url" type="url" value="{{ old('integration_webhook_url',$settings['integration_webhook_url'] ?? '') }}"></label></div><label>{{ __('ui.api_token') }} @if($secretStatus['integration_api_token'])<small>{{ __('ui.secret_saved') }}</small>@endif<input name="integration_api_token" type="password" autocomplete="new-password" placeholder="{{ __('ui.leave_empty_to_keep') }}"></label><div class="desktop-settings-actions"><button class="desktop-settings-primary" data-settings-save>{{ __('ui.save') }}</button></div>
            </form>
        </section>
        @endcan

        @can('users.manage')
        <section class="desktop-settings-panel" data-settings-panel="users" hidden>
            <header class="desktop-settings-heading"><div><span>Zugriff</span><h2>Benutzer &amp; Rechte</h2><p>{{ __('ui.users_hint') }}</p></div></header>
            <div class="desktop-settings-role-picker"><label>{{ __('ui.role') }}<select data-role-picker><option value="">{{ __('ui.select_role') }}</option>@foreach($roles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach</select></label><button type="button" class="desktop-settings-secondary" data-role-create>{{ __('ui.new_role') }}</button></div>
            @foreach($roles as $role)
            <form class="desktop-settings-role-card" data-role-panel="{{ $role->id }}" method="post" action="{{ route('settings.roles.update',$role) }}" hidden>@csrf @method('PUT')<h3>{{ $role->name }}</h3><div class="desktop-settings-permissions">@foreach($permissions as $permission)<label class="desktop-settings-check"><input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked($role->permissions->contains('id',$permission->id))><span>{{ $permission->name }}</span></label>@endforeach</div><div class="desktop-settings-actions"><button class="desktop-settings-primary" data-settings-save>{{ __('ui.save') }}</button></div></form>
            @endforeach
            <form class="desktop-settings-form desktop-settings-role-card" data-role-create-form method="post" action="{{ route('settings.roles.store') }}" hidden>@csrf<label>{{ __('ui.new_role') }}<input name="name" required maxlength="80"></label><div class="desktop-settings-permissions">@foreach($permissions as $permission)<label class="desktop-settings-check"><input type="checkbox" name="permissions[]" value="{{ $permission->id }}"><span>{{ $permission->name }}</span></label>@endforeach</div><div class="desktop-settings-actions"><button class="desktop-settings-primary">{{ __('ui.create_role') }}</button></div></form>
        </section>
        @endcan

        <section class="desktop-settings-panel" data-settings-panel="system" hidden>
            <header class="desktop-settings-heading"><div><span>Plattform</span><h2>System</h2><p>{{ __('ui.system_hint') }}</p></div></header>
            <form class="desktop-settings-form" method="post" action="{{ route('settings') }}">@csrf @method('PUT')<input type="hidden" name="section" value="system">
                <div class="desktop-settings-grid two"><label>{{ __('ui.site_name') }}<input name="site_name" required maxlength="120" value="{{ old('site_name',$settings['site_name'] ?? config('platform.brand')) }}"></label><label>{{ __('ui.contact_email') }}<input name="contact_email" type="email" value="{{ old('contact_email',$settings['contact_email'] ?? '') }}"></label></div><label>{{ __('ui.site_description') }}<textarea name="site_description" rows="3" maxlength="500">{{ old('site_description',$settings['site_description'] ?? '') }}</textarea></label><div class="desktop-settings-grid three"><label>{{ __('ui.language') }}<select name="system_locale">@foreach(config('platform.locales') as $locale)<option value="{{ $locale }}" @selected(old('system_locale',$settings['system_locale'] ?? config('app.locale'))===$locale)>{{ strtoupper($locale) }}</option>@endforeach</select></label><label>{{ __('ui.timezone') }}<input name="system_timezone" value="{{ old('system_timezone',$settings['system_timezone'] ?? config('platform.timezone')) }}"></label><label>{{ __('ui.branding') }}<input name="system_branding_name" maxlength="120" value="{{ old('system_branding_name',$settings['system_branding_name'] ?? '') }}"></label></div><div class="desktop-settings-actions"><button class="desktop-settings-primary" data-settings-save>{{ __('ui.save') }}</button></div>
            </form>
        </section>
    </div>
</section>
