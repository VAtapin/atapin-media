@php
$programs = [
    ['id'=>'videos','name'=>'Videos','icon'=>'Videos'],
    ['id'=>'posts','name'=>'Beiträge','icon'=>'Beitraege'],
    ['id'=>'images','name'=>'Bilder','icon'=>'Bilder'],
    ['id'=>'audio','name'=>'Audio','icon'=>'Audio'],
    ['id'=>'books','name'=>'Bücher','icon'=>'Buecher'],
    ['id'=>'media','name'=>'Media Library','icon'=>'MediaLibrary'],
    ['id'=>'projects','name'=>'Projekte','icon'=>'Projekte'],
    ['id'=>'tasks','name'=>'Aufgaben','icon'=>'Aufgaben'],
    ['id'=>'calendar','name'=>'Kalender','icon'=>'Kalender'],
    ['id'=>'community','name'=>'Community','icon'=>'Community'],
    ['id'=>'subscribers','name'=>'Subscribers','icon'=>'Subscribers'],
    ['id'=>'live-studio','name'=>'Live Studio','icon'=>'LiveStudio'],
    ['id'=>'publishing','name'=>'Publishing','icon'=>'Publishing'],
    ['id'=>'ai-assistant','name'=>'KI-Assistent','icon'=>'KI-Assistent'],
    ['id'=>'analytics','name'=>'Analytics','icon'=>'Analytics'],
    ['id'=>'imports','name'=>'Import Center','icon'=>'ImportCenter'],
    ['id'=>'files','name'=>'Dateien','icon'=>'Dateien'],
    ['id'=>'integrations','name'=>'Integrationen','icon'=>'Integrationen'],
    ['id'=>'settings','name'=>'Einstellungen','icon'=>'Einstellungen','url'=>route('settings')],
];
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
    <title>Desktop · {{ config('platform.brand') }}</title>
    <link rel="icon" href="/favicon.png"><link rel="stylesheet" href="/assets/fonts/fonts.css"><link rel="stylesheet" href="/assets/desktop-os.css?v=3"><link rel="stylesheet" href="/assets/desktop-windows.css?v=3">
    <link rel="stylesheet" href="/assets/desktop-shortcuts.css?v=3">
    <script src="/assets/desktop-shortcuts.js?v=1" defer></script>
    <script src="/assets/desktop-os.js?v=8" defer></script>
</head>
<body class="os-body">
<main class="os-desktop" data-desktop data-storage-key="atapin.desktop.{{ auth()->id() }}.v1"
      data-wallpaper="{{ $desktopAppearance['wallpaper'] }}" data-accent="{{ $desktopAppearance['accent'] }}"
      data-density="{{ $desktopAppearance['density'] }}" data-effects="{{ $desktopAppearance['effects'] ? 'on' : 'off' }}"
      @if($wallpaperUrl) style="--desktop-wallpaper: url('{{ $wallpaperUrl }}')" @endif>
    <nav class="os-shortcuts" tabindex="0" aria-label="{{ __('ui.desktop_shortcuts') }}">
        @foreach($programs as $program)
            <button class="os-shortcut" type="button" data-open-app="{{ $program['id'] }}" @isset($program['url']) data-app-url="{{ $program['url'] }}" @endisset data-app-name="{{ $program['name'] }}" data-app-icon="{{ $iconSet['path'].'/'.$program['icon'].'.png' }}">
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
            <button type="button" data-open-app="{{ $program['id'] }}" @isset($program['url']) data-app-url="{{ $program['url'] }}" @endisset data-app-name="{{ $program['name'] }}" data-app-icon="{{ $iconSet['path'].'/'.$program['icon'].'.png' }}"><img src="{{ $iconSet['path'].'/'.$program['icon'].'.png' }}" alt=""><span>{{ $program['name'] }}</span></button>
            @endforeach
        </div>
        <footer><span>{{ auth()->user()->name }}</span><form method="post" action="{{ route('logout') }}">@csrf<button type="submit">Abmelden</button></form></footer>
    </section>

    <template id="os-window-template">
        <article class="os-window" tabindex="-1">
            <header class="os-window-titlebar" data-drag-handle>
                <span class="os-window-app"><img src="" alt=""><strong></strong></span>
                <span class="os-window-controls">
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
