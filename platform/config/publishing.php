<?php

return [
    'x' => [],
    'youtube' => [
        'api_base' => 'https://www.googleapis.com/youtube/v3',
        'oauth_authorize' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'oauth_token' => 'https://oauth2.googleapis.com/token',
        'oauth_token_info' => 'https://oauth2.googleapis.com/tokeninfo',
        'scope' => 'https://www.googleapis.com/auth/youtube',
        'downloader' => env('YOUTUBE_SYNC_DOWNLOADER', 'yt-dlp'),
    ],
    'meta' => [
        'api_version' => env('META_GRAPH_API_VERSION', 'v23.0'),
        'api_base' => 'https://graph.facebook.com/'.env('META_GRAPH_API_VERSION', 'v23.0'),
        'oauth_authorize' => 'https://www.facebook.com/'.env('META_GRAPH_API_VERSION', 'v23.0').'/dialog/oauth',
        'oauth_token' => 'https://graph.facebook.com/'.env('META_GRAPH_API_VERSION', 'v23.0').'/oauth/access_token',
    ],
    'telegram' => [
        'api_base' => 'https://api.telegram.org',
    ],
    'live_relay_ffmpeg' => env('LIVE_RELAY_FFMPEG', 'ffmpeg'),
];
