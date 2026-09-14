<?php

return [
    'network' => 'Network', 'required' => 'required', 'optional' => 'optional',
    'keep_secret' => 'Leave blank to keep the saved credentials.',
    'connect_oauth' => 'Connect account', 'setup_saved' => 'Configuration saved. This does not yet confirm API permission.',
    'mini_app_entry' => 'Mini App Website URL for BotFather',
    'youtube' => ['public_url' => 'Public channel URL', 'hint' => 'Authorize the account using “Connect account”. Channel ID and tokens are obtained automatically. An administrator configures the Google app on the server; an API key cannot authorize publishing.'],
    'x' => ['public_url' => 'Public profile URL', 'hint' => 'Authorize the account using “Connect account”. User ID and tokens are obtained automatically. The OAuth app, write permissions and API credits are configured separately.'],
    'facebook' => ['public_url' => 'Public Page URL', 'external_id' => 'Facebook Page ID', 'access_token' => 'Page Access Token', 'hint' => 'Enter the Page ID and an authorized Page Access Token with publishing permissions. Profile URL is optional. OAuth app ID and webhook are not input fields for this connector.'],
    'instagram' => ['public_url' => 'Public profile URL', 'external_id' => 'Instagram Business Account ID', 'access_token' => 'Authorized Access Token', 'hint' => 'Enter the Business/Creator account ID and a token for the configured Meta connection with publishing permissions. Profile URL is optional. An app ID alone does not connect the account.'],
    'telegram' => ['public_url' => 'Public channel URL', 'external_id' => 'Destination channel / Chat ID (e.g. @my_channel)', 'api_key' => 'Bot Token', 'bot_username' => 'Bot username without @', 'mini_app_enabled' => 'Main Mini App is configured in BotFather',
        'hint' => 'For channel posts: Bot Token from BotFather and public channel Chat ID; add the bot as an administrator with posting permissions. Channel URL is optional. For the Mini App, configure the Website URL as the Main Mini App in BotFather and save the bot username. A Mini App can work without a channel. Enable the checkbox only after setup; it does not register the app. OAuth and webhook are not needed here.'],
    'tiktok' => ['public_url' => 'Public profile URL', 'hint' => 'Profile link only. Automatic publishing is unavailable in this installation; do not enter tokens.'],
    'linkedin' => ['public_url' => 'Public profile/Page URL', 'hint' => 'Profile link only. No publishing connector is implemented; do not enter tokens.'],
];
