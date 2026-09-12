@php
$programs = [
    ['id'=>'videos','name'=>'Videos','icon'=>'Videos'],
    ['id'=>'posts','name'=>'Beiträge','icon'=>'Beitraege'],
    ['id'=>'books-pdf','name'=>'Bücher & PDF','icon'=>'Buecher'],
    ['id'=>'podcast','name'=>'Podcast','icon'=>'Audio'],
    ['id'=>'live-studio','name'=>'Live Studio','icon'=>'LiveStudio'],
    ['id'=>'media','name'=>'Media Library','icon'=>'MediaLibrary'],
    ['id'=>'projects','name'=>'Projekte','icon'=>'Projekte'],
    ['id'=>'tasks','name'=>'Aufgaben','icon'=>'Aufgaben'],
    ['id'=>'calendar','name'=>'Kalender','icon'=>'Kalender'],
    ['id'=>'community','name'=>'Community','icon'=>'Community'],
    ['id'=>'newsletter','name'=>'Newsletter','icon'=>'Subscribers'],
    ['id'=>'topics','name'=>'Themen & Kategorien','icon'=>'Bilder'],
    ['id'=>'publishing','name'=>'Publishing','icon'=>'Publishing'],
    ['id'=>'shop','name'=>'Shop & Verkäufe','icon'=>'Dateien'],
    ['id'=>'ai-assistant','name'=>'KI-Assistent','icon'=>'KI-Assistent'],
    ['id'=>'analytics','name'=>'Analytics','icon'=>'Analytics'],
    ['id'=>'imports','name'=>'Import Center','icon'=>'ImportCenter'],
    ['id'=>'integrations','name'=>'Integrationen','icon'=>'Integrationen'],
];
if ($canManageSettings) {
    $programs[] = ['id'=>'settings','name'=>'Einstellungen','icon'=>'Einstellungen'];
}
$desktopAppearance = $desktopAppearance ?? ['icon_set' => 'manna', 'wallpaper' => 'mountains', 'accent' => 'gold'];
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
    <link rel="icon" href="/favicon.png"><link rel="stylesheet" href="/assets/fonts/fonts.css"><link rel="stylesheet" href="/assets/brand/ui-kit.css?v=owner-1"><link rel="stylesheet" href="/assets/desktop-os.css?v=4"><link rel="stylesheet" href="/assets/desktop-windows.css?v=3"><link rel="stylesheet" href="/assets/desktop-settings.css?v=3">
    <link rel="stylesheet" href="/assets/desktop-app.css?v=1"><link rel="stylesheet" href="/assets/desktop-shortcuts.css?v=3"><link rel="stylesheet" href="/assets/desktop-media-library.css?v=5"><link rel="stylesheet" href="/assets/desktop-import-center.css?v=2">
    <script src="/assets/desktop-shortcuts.js?v=3" defer></script>
    <script src="/assets/desktop-os.js?v=12" defer></script><script src="/assets/settings-tabs.js?v=5" defer></script><script src="/assets/desktop-media-library.js?v=8" defer></script><script src="/assets/desktop-import-center.js?v=4" defer></script>
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
    <script>window.desktopImportLabels = @json(__('imports'));</script>
    <script src="/assets/desktop-content-library.js?v=3" defer></script>
    <script src="/assets/desktop-content-assignment.js?v=2" defer></script>
    <script src="/assets/desktop-media-organization.js?v=2" defer></script>
    <script src="/assets/desktop-media-cover.js?v=1" defer></script>

    <script src="/assets/desktop-media-upload.js?v=2" defer></script>
    @foreach($programs as $program)
    <template data-help-template="{{ $program['id'] }}">
        <section class="desktop-help">
            @foreach(__('desktop-help.apps')[$program['id']] ?? [__('desktop-help.pending')] as $paragraph)
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
