@php
$programs = [
    ['id'=>'videos','name'=>'Videos','icon'=>'/assets/brand/owner/icon-play.png','shortcut'=>true],
    ['id'=>'posts','name'=>'Beiträge','icon'=>'/assets/ui/icons/article.png','shortcut'=>true],
    ['id'=>'images','name'=>'Bilder','icon'=>'/assets/ui/sidebar-icons/media.png','shortcut'=>true],
    ['id'=>'audio','name'=>'Audio','icon'=>'/assets/ui/icons/podcast.png','shortcut'=>true],
    ['id'=>'books','name'=>'Bücher','icon'=>'/assets/brand/owner/icon-book.png','shortcut'=>true],
    ['id'=>'media','name'=>'Media Library','icon'=>'/assets/ui/sidebar-icons/files.png','shortcut'=>false],
    ['id'=>'projects','name'=>'Projekte','icon'=>'/assets/ui/sidebar-icons/projects.png','shortcut'=>false],
    ['id'=>'tasks','name'=>'Aufgaben','icon'=>'/assets/ui/sidebar-icons/tasks.png','shortcut'=>true],
    ['id'=>'calendar','name'=>'Kalender','icon'=>'/assets/ui/sidebar-icons/calendar.png','shortcut'=>false],
    ['id'=>'community','name'=>'Community','icon'=>'/assets/ui/sidebar-icons/community.png','shortcut'=>true],
    ['id'=>'statistics','name'=>'Statistiken','icon'=>'/assets/ui/sidebar-icons/analytics.png','shortcut'=>true],
    ['id'=>'imports','name'=>'Import Center','icon'=>'/assets/ui/sidebar-icons/imports.png','shortcut'=>false],
    ['id'=>'settings','name'=>'Einstellungen','icon'=>'/assets/brand/owner/icon-settings.png','shortcut'=>true],
];
@endphp
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Desktop · {{ config('platform.brand') }}</title>
    <link rel="icon" href="/favicon.png"><link rel="stylesheet" href="/assets/fonts/fonts.css"><link rel="stylesheet" href="/assets/desktop-os.css?v=2"><link rel="stylesheet" href="/assets/desktop-windows.css?v=3">
    <script src="/assets/desktop-os.js?v=6" defer></script>
</head>
<body class="os-body">
<main class="os-desktop" data-desktop data-storage-key="atapin.desktop.{{ auth()->id() }}.v1">
    <nav class="os-shortcuts" aria-label="Programme auf dem Desktop">
        @foreach($programs as $program)
            @if($program['shortcut'])
            <button class="os-shortcut" type="button" data-open-app="{{ $program['id'] }}" data-app-name="{{ $program['name'] }}" data-app-icon="{{ $program['icon'] }}">
                <span class="os-shortcut-icon"><img src="{{ $program['icon'] }}" alt=""></span><span>{{ $program['name'] }}</span>
            </button>
            @endif
        @endforeach
    </nav>

    <section class="os-start-menu" data-start-menu hidden>
        <header><img src="/assets/brand/owner/logo-mark.png" alt=""><div><strong>Manna Media</strong><span>Programme</span></div></header>
        <div class="os-program-grid">
            @foreach($programs as $program)
            <button type="button" data-open-app="{{ $program['id'] }}" data-app-name="{{ $program['name'] }}" data-app-icon="{{ $program['icon'] }}"><img src="{{ $program['icon'] }}" alt=""><span>{{ $program['name'] }}</span></button>
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
