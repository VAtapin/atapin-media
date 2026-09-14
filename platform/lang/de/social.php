<?php

return [
    'network' => 'Netzwerk', 'required' => 'erforderlich', 'optional' => 'optional',
    'keep_secret' => 'Leer lassen, um den gespeicherten Zugang zu behalten.',
    'connect_oauth' => 'Konto verbinden', 'setup_saved' => 'Konfiguration gespeichert. Dies bestätigt noch keine API-Berechtigung.',
    'mini_app_entry' => 'Mini App Website-Adresse für BotFather',
    'youtube' => ['public_url' => 'Öffentliche Kanal-URL', 'hint' => 'Das Konto über „Konto verbinden“ autorisieren. Kanal-ID und Tokens werden automatisch übernommen. Die Google-App wird vom Administrator auf dem Server eingerichtet; ein API-Key reicht nicht zum Veröffentlichen.'],
    'x' => ['public_url' => 'Öffentliche Profil-URL', 'hint' => 'Das Konto über „Konto verbinden“ autorisieren. Benutzer-ID und Tokens werden automatisch übernommen. Die OAuth-App, Schreibrechte und API-Guthaben werden separat eingerichtet.'],
    'facebook' => ['public_url' => 'Öffentliche Seiten-URL', 'external_id' => 'Facebook Page-ID', 'access_token' => 'Page Access Token', 'hint' => 'Die Page-ID und einen autorisierten Page Access Token mit Veröffentlichungsrechten eintragen. Profil-URL ist optional. OAuth-App-ID und Webhook sind für diesen Connector keine Eingabefelder.'],
    'instagram' => ['public_url' => 'Öffentliche Profil-URL', 'external_id' => 'Instagram Business Account-ID', 'access_token' => 'Autorisierter Access Token', 'hint' => 'Business-/Creator-Konto-ID und einen Token für die eingerichtete Meta-Verbindung mit Veröffentlichungsrechten eintragen. Profil-URL ist optional. Eine App-ID allein verbindet das Konto nicht.'],
    'telegram' => ['public_url' => 'Öffentliche Kanal-URL', 'external_id' => 'Zielkanal / Chat-ID (z. B. @mein_kanal)', 'api_key' => 'Bot Token', 'bot_username' => 'Bot-Benutzername ohne @', 'mini_app_enabled' => 'Main Mini App ist in BotFather eingerichtet',
        'hint' => 'Für Kanal-Posts: Bot Token aus BotFather und Chat-ID des öffentlichen Kanals; den Bot als Administrator mit Veröffentlichungsrechten hinzufügen. Die Kanal-URL ist optional. Für die Mini App in BotFather die Website-Adresse als Main Mini App einrichten und den Bot-Benutzernamen speichern. Mini App ohne Kanal ist möglich. Das Häkchen erst nach Einrichtung aktivieren; es registriert die App nicht. OAuth und Webhook werden hier nicht benötigt.'],
    'tiktok' => ['public_url' => 'Öffentliche Profil-URL', 'hint' => 'Nur ein Profil-Link. Automatisches Publishing ist in dieser Installation nicht verfügbar; keine Tokens eingeben.'],
    'linkedin' => ['public_url' => 'Öffentliche Profil-/Seiten-URL', 'hint' => 'Nur ein Profil-Link. Ein Publishing-Connector ist nicht implementiert; keine Tokens eingeben.'],
];
