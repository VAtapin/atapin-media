<?php

return [
    'x' => [
        'client_id' => env('X_OAUTH_CLIENT_ID'), 'client_secret' => env('X_OAUTH_CLIENT_SECRET'),
        'redirect_uri' => env('X_OAUTH_REDIRECT_URI', rtrim(env('APP_URL', 'http://localhost'), '/').'/desktop/publishing/x/callback'),
    ],
    'youtube' => [
        'client_id' => env('YOUTUBE_OAUTH_CLIENT_ID'),
        'client_secret' => env('YOUTUBE_OAUTH_CLIENT_SECRET'),
        'redirect_uri' => env('YOUTUBE_OAUTH_REDIRECT_URI', rtrim(env('APP_URL', 'http://localhost'), '/').'/desktop/publishing/youtube/callback'),
        'api_base' => 'https://www.googleapis.com/youtube/v3',
        'oauth_authorize' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'oauth_token' => 'https://oauth2.googleapis.com/token',
        'scope' => 'https://www.googleapis.com/auth/youtube',
        'downloader' => env('YOUTUBE_SYNC_DOWNLOADER', 'yt-dlp'),
    ],
    'meta' => [
        'api_version' => env('META_GRAPH_API_VERSION', 'v23.0'),
    ],
    'telegram' => [
        'api_base' => 'https://api.telegram.org',
    ],
    'live_relay_ffmpeg' => env('LIVE_RELAY_FFMPEG', 'ffmpeg'),
];
