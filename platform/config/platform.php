<?php
return [
    'brand' => env('PLATFORM_BRAND', 'Atapin Media'),
    'theme' => env('PLATFORM_THEME', 'manna'),
    'timezone' => env('PLATFORM_TIMEZONE', 'Europe/Berlin'),
    'locales' => ['de', 'en'],
    'media_disk' => env('MEDIA_DISK', 'local'),
    'intake_root' => env('INTAKE_ROOT', dirname(base_path(), 2).'/private/manna-intake'),
    'youtube_root' => env('YOUTUBE_ROOT', dirname(base_path(), 2).'/private/manna-youtube'),
];
