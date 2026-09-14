<?php

return [
    // Exact third-party HTTPS hosts approved by the installation owner. No wildcards.
    'allowed_embed_hosts' => array_values(array_filter(array_map('trim',explode(',',env('POLL_EMBED_HOSTS',''))))),
];
