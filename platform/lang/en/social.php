<?php

return [
    'network' => 'Network', 'required' => 'required', 'optional' => 'optional',
    'keep_secret' => 'Leave blank to keep the saved credentials.',
    'connect_oauth' => 'Connect account', 'setup_saved' => 'Configuration saved. This does not yet confirm API permission.',
    'status_not_configured' => 'Not configured', 'status_configured' => 'Configured – account not connected yet',
    'status_connected' => 'Connected', 'status_expired' => 'Connection expired', 'status_permission' => 'Permission missing', 'status_error' => 'Error',
    'redirect_uri' => 'OAuth redirect URI', 'redirect_uri_hint' => 'Register this URI with the provider as an authorized redirect URI.',
    'copy' => 'Copy', 'copied' => 'Redirect URI copied.', 'copy_failed' => 'The redirect URI could not be copied.', 'connect_requires_save' => 'Save the Client ID and Client Secret first.',
    'edit' => 'Edit', 'check_connection' => 'Check connection', 'disconnect' => 'Disconnect', 'account_id' => 'Account/Page ID',
    'connection_checked' => ':provider connection was verified.', 'connection_check_failed' => ':provider connection could not be verified.', 'connection_disconnected' => ':provider was disconnected.',
    'select_page' => 'Select Facebook Page', 'select_page_hint' => 'Choose the Page to use for Facebook and its linked Instagram professional account.',
    'page_without_instagram' => 'no linked Instagram account', 'use_page' => 'Use this Page',
    'mini_app_entry' => 'Mini App Website URL for BotFather',
    'youtube' => ['public_url' => 'Public channel URL', 'oauth_client_id' => 'Google OAuth Client ID', 'oauth_client_secret' => 'Google OAuth Client Secret', 'hint' => 'An administrator enters the Client ID and Client Secret first. Then authorize the account using “Connect account”; channel ID and tokens are obtained automatically.'],
    'x' => ['public_url' => 'Public profile URL', 'oauth_client_id' => 'X OAuth Client ID', 'oauth_client_secret' => 'X OAuth Client Secret', 'hint' => 'An administrator enters the Client ID first, plus the Client Secret for a confidential OAuth app. Then authorize the account using “Connect account”; user ID and tokens are obtained automatically.'],
    'facebook' => ['public_url' => 'Public Page URL', 'oauth_client_id' => 'Meta App ID', 'oauth_client_secret' => 'Meta App Secret', 'hint' => 'Save the Meta App ID and App Secret, register the displayed redirect URI in the Meta app, then connect the account. OAuth securely obtains the Page, permissions, and Page token.'],
    'instagram' => ['public_url' => 'Public profile URL', 'hint' => 'Instagram uses the same Meta connection and protected Page token as Facebook. The professional account must be linked to the selected Facebook Page; duplicate credentials are not stored.'],
    'telegram' => ['public_url' => 'Public channel URL', 'external_id' => 'Destination channel / Chat ID (e.g. @my_channel)', 'api_key' => 'Bot Token', 'bot_username' => 'Bot username without @', 'mini_app_enabled' => 'Main Mini App is configured in BotFather',
        'hint' => 'For channel posts: Bot Token from BotFather and public channel Chat ID; add the bot as an administrator with posting permissions. Channel URL is optional. For the Mini App, configure the Website URL as the Main Mini App in BotFather and save the bot username. A Mini App can work without a channel. Enable the checkbox only after setup; it does not register the app. OAuth and webhook are not needed here.'],
    'tiktok' => ['public_url' => 'Public profile URL', 'hint' => 'Profile link only. Automatic publishing is unavailable in this installation; do not enter tokens.'],
    'linkedin' => ['public_url' => 'Public profile/Page URL', 'hint' => 'Profile link only. No publishing connector is implemented; do not enter tokens.'],
];
