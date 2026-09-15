@php
$programs = [
    ['id'=>'overview','name'=>__('workspaces.overview'),'icon'=>'Projekte'],
    ['id'=>'polls','name'=>__('workspaces.polls'),'icon'=>'Community'],
    ['id'=>'videos','name'=>'Videos','icon'=>'Videos'],
    ['id'=>'posts','name'=>'Beiträge','icon'=>'Beitraege'],
    ['id'=>'books-pdf','name'=>'Bücher & PDF','icon'=>'Buecher'],
    ['id'=>'podcast','name'=>'Podcast','icon'=>'Audio'],
    ['id'=>'live-studio','name'=>'Live Studio','icon'=>'LiveStudio'],
    ['id'=>'media','name'=>'Media Library','icon'=>'MediaLibrary'],
    ['id'=>'projects','name'=>'Projekte','icon'=>'Projekte'],
    ['id'=>'tasks','name'=>'Aufgaben','icon'=>'Aufgaben'],
    ['id'=>'calendar','name'=>'Kalender','icon'=>'Kalender'],
    ['id'=>'newsletter','name'=>'Newsletter','icon'=>'Subscribers'],
    ['id'=>'topics','name'=>'Themen & Kategorien','icon'=>'Bilder'],
    ['id'=>'publishing','name'=>'Publishing','icon'=>'Publishing'],
    ['id'=>'shop','name'=>'Shop & Verkäufe','icon'=>'Dateien'],
    ['id'=>'ai-assistant','name'=>'KI-Assistent','icon'=>'KI-Assistent'],
    ['id'=>'analytics','name'=>'Analytics','icon'=>'Analytics'],
    ['id'=>'imports','name'=>'Import Center','icon'=>'ImportCenter'],
    ['id'=>'integrations','name'=>'Integrationen','icon'=>'Integrationen'],
];
if ($canModerateCommunity ?? false) {
    array_splice($programs, 9, 0, [['id'=>'community','name'=>'Community','icon'=>'Community']]);
}
if ($canManageSettings) {
    $programs[] = ['id'=>'settings','name'=>'Einstellungen','icon'=>'Einstellungen'];
}
$desktopAppearance = $desktopAppearance ?? ['icon_set' => 'manna', 'wallpaper' => 'mountains', 'accent' => 'gold'];
 $programPermissions=[...\App\Http\Controllers\DesktopWorkspaceController::APPS,'imports'=>'imports.manage','publishing'=>'content.publish','live-studio'=>'content.publish','media'=>'media.view','videos'=>'media.view','posts'=>'media.view','podcast'=>'media.view'];
 $programs=array_values(array_filter($programs,fn($program)=>($program['id']!=='calendar'||auth()->user()->hasPermission('projects.manage')||auth()->user()->hasPermission('content.publish'))&&(!isset($programPermissions[$program['id']])||auth()->user()->hasPermission($programPermissions[$program['id']]))));
$iconSet = config('desktop.icon_sets.'.$desktopAppearance['icon_set'], config('desktop.icon_sets.manna'));
$wallpaper = config('desktop.wallpapers.'.$desktopAppearance['wallpaper']);
$wallpaperUrl = is_array($wallpaper) ? ($wallpaper['path'] ?? null) : null;
$wallpaperUrl ??= ($desktopAppearance['wallpaper'] === 'custom' && $desktopAppearance['custom_wallpaper']) ? route('desktop.wallpaper') : null;
@endphp
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Desktop · {{ config('platform.brand') }}</title>
    <link rel="icon" href="/favicon.png"><link rel="manifest" href="/manifest.webmanifest"><link rel="apple-touch-icon" href="/assets/brand/owner/app-icon.png"><meta name="theme-color" content="#8b6a3d"><link rel="stylesheet" href="/assets/fonts/fonts.css"><link rel="stylesheet" href="/assets/brand/ui-kit.css?v=owner-1"><link rel="stylesheet" href="/assets/desktop-os.css?v=4"><link rel="stylesheet" href="/assets/desktop-windows.css?v=3"><link rel="stylesheet" href="/assets/desktop-settings.css?v=3">
    <link rel="stylesheet" href="/assets/desktop-app.css?v=2"><link rel="stylesheet" href="/assets/desktop-shortcuts.css?v=3"><link rel="stylesheet" href="/assets/desktop-media-library.css?v=8"><link rel="stylesheet" href="/assets/desktop-import-center.css?v=3"><link rel="stylesheet" href="/assets/desktop-live-studio.css?v=3"><link rel="stylesheet" href="/assets/desktop-community.css?v=3"><link rel="stylesheet" href="/assets/desktop-publishing.css?v=3">
    <link rel="stylesheet" href="/assets/desktop-workspaces.css?v=4">
    <link rel="stylesheet" href="/assets/desktop-overview.css?v=2">
    <script>window.desktopWorkspaceLabels=@json(__('workspaces'));</script>
    <script src="/assets/desktop-workspaces.js?v=3" defer></script>
    <script src="/assets/public-pwa.js?v=1" defer></script>
    <script src="/assets/desktop-workspace-content.js?v=4" defer></script>
    <script src="/assets/desktop-subscriber-import.js?v=1" defer></script>
    <script src="/assets/desktop-ai-proposal.js?v=1" defer></script>
    <script src="/assets/desktop-migration-wizard.js?v=1" defer></script>
    <script src="/assets/desktop-widget-layout.js?v=1" defer></script>
    <script src="/assets/desktop-content-columns.js?v=2" defer></script>
    <script src="/assets/desktop-project-timeline.js?v=2" defer></script>
    <script src="/assets/desktop-overview.js?v=3" defer></script>
    <script src="/assets/desktop-polls.js?v=2" defer></script>
    <script src="/assets/desktop-document-import.js?v=1" defer></script>
    <script src="/assets/desktop-live-console.js?v=2" defer></script>
    <script src="/assets/desktop-publishing-preview.js?v=2" defer></script>
    <script src="/assets/desktop-community-sync.js?v=1" defer></script>
    <script src="/assets/desktop-workspace-operations.js?v=5" defer></script>
    <script src="/assets/desktop-editor-workflow.js?v=4" defer></script>
    <script src="/assets/desktop-shortcuts.js?v=3" defer></script>
    <link rel="stylesheet" href="/assets/desktop-import-workflow.css?v=3">
    <script src="/assets/desktop-os.js?v=17" defer></script><script src="/assets/settings-tabs.js?v=7" defer></script><script src="/assets/desktop-media-library.js?v=15" defer></script><script src="/assets/desktop-import-center.js?v=12" defer></script><script src="/assets/desktop-live-studio.js?v=6" defer></script><script src="/assets/desktop-publishing.js?v=4" defer></script>
</head>
<body class="os-body">
<main class="os-desktop" data-desktop data-storage-key="atapin.desktop.{{ auth()->id() }}.v1"
      data-wallpaper="{{ $desktopAppearance['wallpaper'] }}" data-accent="{{ $desktopAppearance['accent'] }}"
      data-density="{{ $desktopAppearance['density'] }}" data-shortcut-layout="{{ $desktopAppearance['shortcut_layout'] }}" data-effects="{{ $desktopAppearance['effects'] ? 'on' : 'off' }}"
      @if($wallpaperUrl) style="--desktop-wallpaper: url('{{ $wallpaperUrl }}')" @endif>
    <nav class="os-shortcuts" tabindex="0" aria-label="{{ __('ui.desktop_shortcuts') }}">
        @foreach($programs as $program)
            <button class="os-shortcut" type="button" data-open-app="{{ $program['id'] }}" data-app-name="{{ $program['name'] }}" data-app-icon="{{ $iconSet['path'].'/'.$program['icon'].'.png' }}">
                <span class="os-shortcut-icon"><img src="{{ $iconSet['path'].'/'.$program['icon'].'.png' }}" alt=""></span><span>{{ $program['name'] }}</span>
            </button>
        @endforeach
    </nav>

    <div class="os-shortcut-menu" data-shortcut-menu role="menu" aria-label="{{ __('ui.shortcut_menu') }}"
         data-add-label="{{ __('ui.add_shortcut') }}" data-remove-label="{{ __('ui.remove_shortcut') }}" hidden></div>

    <section class="os-start-menu" data-start-menu hidden>
        <header><img src="/assets/brand/owner/logo-mark.png" alt=""><div><strong>Manna Media</strong><span>Programme</span></div></header>
        <div class="os-program-grid">
            @foreach($programs as $program)
            <button type="button" data-open-app="{{ $program['id'] }}" data-app-name="{{ $program['name'] }}" data-app-icon="{{ $iconSet['path'].'/'.$program['icon'].'.png' }}"><img src="{{ $iconSet['path'].'/'.$program['icon'].'.png' }}" alt=""><span>{{ $program['name'] }}</span></button>
            @endforeach
        </div>
        <footer><button type="button" class="os-account-button" data-open-app="settings" data-settings-section="profile" data-app-name="Einstellungen" data-app-icon="{{ $iconSet['path'].'/Einstellungen.png' }}"><span data-account-name>{{ auth()->user()->name }}</span></button><form method="post" action="{{ route('logout') }}">@csrf<button type="submit">Abmelden</button></form></footer>
    </section>


    <template id="settings-app-template">
        @include('desktop.settings', $settingsPageData)
    </template>
    <template id="media-library-app-template">
        @include('desktop.media-library')
    </template>
    <template id="import-center-app-template">
        @include('desktop.import-center')
    </template>
    <template id="content-library-app-template">@include('desktop.content-library')</template>
    <template id="publishing-app-template">@include('desktop.publishing')</template>
    <template id="live-studio-app-template">
        <section class="desktop-live-studio" data-live-studio data-user-id="{{ auth()->id() }}" data-api-index="{{ route('desktop.live.api.index') }}" data-api-base="{{ url('/api/desktop/live') }}" data-preview-base="{{ url('/live?event=') }}">
            <header class="desktop-live-studio-head">
                <div>
                    <p class="desktop-live-eyebrow">{{ __('desktop-live.eyebrow') }}</p>
                    <h1>{{ __('desktop-live.title') }}</h1>
                    <p>{{ __('desktop-live.intro') }}</p>
                </div>
                <button class="desktop-button is-primary" type="button" data-live-new>{{ __('desktop-live.new') }}</button>
            </header>
            <div class="desktop-live-studio-grid">
                <aside class="desktop-live-events">
                    <section class="desktop-live-current" data-live-current aria-live="polite">
                        <div class="desktop-live-current-heading"><h2>{{ __('desktop-live.live_now') }}</h2><span data-live-now-count>0</span></div>
                        <div class="desktop-live-current-list" data-live-now-events></div>
                    </section>
                    <div class="desktop-live-section-heading"><h2>{{ __('desktop-live.events') }}</h2><span data-live-count>0</span></div>
                    <div class="desktop-live-filters">
                        <label class="desktop-live-filter-control"><span class="sr-only">{{ __('desktop-live.filter_label') }}</span><select data-live-filter aria-label="{{ __('desktop-live.filter_label') }}"><option value="day">{{ __('desktop-live.filter_today') }}</option><option value="scheduled">{{ __('desktop-live.filter_scheduled') }}</option></select></label>
                        <label class="desktop-live-filter-control"><span class="sr-only">{{ __('desktop-live.filter_date') }}</span><input data-live-date type="date" aria-label="{{ __('desktop-live.filter_date') }}"></label>
                    </div>
                    <p class="desktop-live-status" data-live-list-status>{{ __('desktop-live.loading') }}</p>
                    <div class="desktop-live-event-list" data-live-events></div>
                    <nav class="desktop-live-pagination" data-live-pagination aria-label="{{ __('desktop-live.pagination') }}" hidden><button class="desktop-button" type="button" data-live-page-prev aria-label="{{ __('desktop-live.previous') }}">←</button><span data-live-page-info></span><button class="desktop-button" type="button" data-live-page-next aria-label="{{ __('desktop-live.next') }}">→</button></nav>
                </aside>
                <section class="desktop-live-editor" aria-live="polite">
                    <div class="desktop-live-feedback" data-live-feedback role="status" hidden></div>
                    <div class="desktop-live-editor-empty" data-live-editor-empty>
                        <span class="desktop-live-mark">✦</span><strong>{{ __('desktop-live.new') }}</strong><p>{{ __('desktop-live.empty') }}</p>
                    </div>
                    <form data-live-form hidden>
                        <div class="desktop-live-editor-heading"><div><p class="desktop-live-eyebrow" data-live-editor-eyebrow>{{ __('desktop-live.new') }}</p><h2 data-live-editor-title>{{ __('desktop-live.new') }}</h2></div><button class="desktop-button" type="button" data-live-help>{{ __('desktop-live.help') }}</button></div>
                        <input type="hidden" name="id">
                        <input type="hidden" name="cover_media_id">
                        <div class="desktop-live-fields">
                            <label>{{ __('desktop-live.title_label') }}<input name="title" required maxlength="255"></label>
                            <label>{{ __('desktop-live.schedule_label') }}<input name="starts_at" type="datetime-local"></label>
                            <label class="desktop-live-field-wide">{{ __('desktop-live.description_label') }}<textarea name="body" rows="5" maxlength="10000"></textarea></label>
                        </div>
                        <div class="desktop-live-poster">
                            <div><p class="desktop-live-eyebrow">{{ __('desktop-live.poster_eyebrow') }}</p><h3>{{ __('desktop-live.poster_title') }}</h3><p>{{ __('desktop-live.poster_hint') }}</p></div>
                            <div class="desktop-live-poster-row"><div class="desktop-live-poster-drop" data-live-poster-drop tabindex="0" role="button" aria-label="{{ __('desktop-live.poster_drop') }}"><div class="desktop-live-poster-preview" data-live-poster-preview hidden><img data-live-poster-image alt=""></div><div class="desktop-live-poster-empty" data-live-poster-empty>{{ __('desktop-live.poster_empty') }}</div><div class="desktop-live-poster-drop-copy"><strong>{{ __('desktop-live.poster_drop') }}</strong><small>{{ __('desktop-live.poster_rules') }}</small><span class="desktop-button">{{ __('desktop-live.poster_choose') }}</span></div><input type="file" name="poster_file" accept="image/jpeg,image/png,image/webp,image/gif" data-live-poster-file hidden></div></div>
                            <p class="desktop-live-poster-status" data-live-poster-status role="status" aria-live="polite"></p>
                            <progress class="desktop-live-poster-progress" data-live-poster-progress max="100" value="0" hidden aria-label="{{ __('desktop-live.poster_progress') }}"></progress>
                        </div>
                        <div class="desktop-live-options"><label><input name="published" type="checkbox">{{ __('desktop-live.publish') }}</label><label><input name="enabled" type="checkbox">{{ __('desktop-live.enable') }}</label><label data-live-rotate-wrap hidden><input name="rotate_key" type="checkbox">{{ __('desktop-live.rotate') }}</label></div>
                        <div class="desktop-live-actions"><button class="desktop-button is-primary" type="submit">{{ __('desktop-live.save') }}</button><button class="desktop-button" type="button" data-live-preview hidden>{{ __('desktop-live.preview') }}</button></div>
                    </form>
                    <section class="desktop-live-ingest" data-live-ingest hidden><div><p class="desktop-live-eyebrow">{{ __('desktop-live.connection') }}</p><h2>{{ __('desktop-live.connection') }}</h2></div><div class="desktop-live-connection-grid"><div><span>{{ __('desktop-live.server') }}</span><code data-live-server></code></div><div><span>{{ __('desktop-live.stream_key') }}</span><code>{{ __('desktop-live.stream_key_empty') }}</code></div></div><div class="desktop-live-url-row"><code data-live-url></code><button class="desktop-button" type="button" data-live-copy>{{ __('desktop-live.copy') }}</button></div></section>
                </section>
            </div>
        </section>
    </template>
    @if($canModerateCommunity ?? false)
    <template id="community-app-template">
        @include('desktop.community', ['entries' => $communityEntries, 'stats' => $communityStats])
    </template>
    @endif
    <script>window.desktopImportLabels = @json(__('imports')); window.desktopLiveLabels = @json(__('desktop-live')); window.desktopPublishingLabels = @json(__('publishing'));</script>
    <script src="/assets/desktop-import-workflow.js?v=2" defer></script>
    <script src="/assets/desktop-content-lifecycle.js?v=2" defer></script>
    <script src="/assets/desktop-content-composite.js?v=2" defer></script>
    <script src="/assets/desktop-content-library.js?v=11" defer></script>
    <script src="/assets/desktop-content-organization.js?v=3" defer></script>
    <script src="/assets/desktop-content-enhancements.js?v=3" defer></script>
    <script src="/assets/desktop-local-links.js?v=1" defer></script>
    <script src="/assets/desktop-media-technical.js?v=1" defer></script>
    <script src="/assets/desktop-content-assignment.js?v=9" defer></script>
    <script src="/assets/desktop-media-organization.js?v=4" defer></script>
    <script src="/assets/desktop-media-cover.js?v=2" defer></script>
    <script src="/assets/desktop-import-versions.js?v=1" defer></script>
    <script src="/assets/desktop-record-classifications.js?v=1" defer></script>

    <script src="/assets/desktop-media-upload.js?v=3" defer></script>
    @foreach($programs as $program)
    <template data-help-template="{{ $program['id'] }}">
        <section class="desktop-help">
            @foreach(__('desktop-help.apps')[$program['id']] ?? (isset(__('workspaces.intros')[$program['id']])?[__('workspaces.intros')[$program['id']]]:[__('desktop-help.pending')]) as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
            <h3>{{ __('desktop-help.workflow_title') }}</h3><p class="desktop-help-note">{{ __('desktop-help.workflow') }}</p>
            <h3>{{ __('desktop-help.navigation_title') }}</h3><p>{{ __('desktop-help.navigation') }}</p>
        </section>
    </template>
    @endforeach
    <template id="os-window-template">
        <article class="os-window" tabindex="-1">
            <header class="os-window-titlebar" data-drag-handle>
                <span class="os-window-app"><img src="" alt=""><strong></strong></span>
                <span class="os-window-controls">
                    <button type="button" data-window-action="help" title="{{ __('desktop-help.title') }}" aria-label="{{ __('desktop-help.title') }}">?</button>
                    <button type="button" data-window-action="pin" title="Immer im Vordergrund" aria-label="Immer im Vordergrund">◆</button>
                    <button type="button" data-window-action="minimize" title="Minimieren" aria-label="Minimieren">—</button>
                    <button type="button" data-window-action="maximize" title="Maximieren" aria-label="Maximieren">□</button>
                    <button type="button" data-window-action="fullscreen" title="Vollbild" aria-label="Vollbild">⛶</button>
                    <button type="button" data-window-action="close" title="Schließen" aria-label="Schließen">×</button>
                </span>
            </header>
            <div class="os-window-content"></div>
        </article>
    </template>
</main>

<footer class="os-taskbar">
    <button class="os-start-button" type="button" data-start-button aria-expanded="false"><img src="/assets/brand/owner/logo-mark.png" alt=""><span>Start</span></button>
    <div class="os-running-apps" data-running-apps></div>
    <button class="os-close-all" type="button" data-close-all title="{{ __('ui.close_all_windows') }}" aria-label="{{ __('ui.close_all_windows') }}"><b aria-hidden="true">×</b><span>{{ __('ui.close_all_windows') }}</span></button>
    <time data-clock></time>
</footer>
</body>
</html>
