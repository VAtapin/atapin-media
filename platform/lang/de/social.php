<?php

return [
    'network' => 'Netzwerk', 'required' => 'erforderlich', 'optional' => 'optional',
    'keep_secret' => 'Leer lassen, um den gespeicherten Zugang zu behalten.',
    'connect_oauth' => 'Konto verbinden', 'setup_saved' => 'Konfiguration gespeichert. Dies bestätigt noch keine API-Berechtigung.',
    'status_not_configured' => 'Nicht eingerichtet', 'status_configured' => 'Konfiguriert – Konto noch nicht verbunden',
    'status_connected' => 'Verbunden', 'status_expired' => 'Verbindung abgelaufen', 'status_permission' => 'Berechtigung fehlt', 'status_error' => 'Fehler',
    'redirect_uri' => 'OAuth Redirect URI', 'redirect_uri_hint' => 'Diesen URI beim Anbieter als autorisierte Weiterleitungs-URI eintragen.',
    'copy' => 'Kopieren', 'copied' => 'Redirect URI kopiert.', 'copy_failed' => 'Die Redirect URI konnte nicht kopiert werden.', 'connect_requires_save' => 'Zuerst Client-ID und Client-Secret speichern.',
    'edit' => 'Bearbeiten', 'check_connection' => 'Verbindung prüfen', 'disconnect' => 'Trennen', 'account_id' => 'Konto-/Seiten-ID',
    'connection_checked' => ':provider-Verbindung wurde erfolgreich geprüft.', 'connection_check_failed' => ':provider-Verbindung konnte nicht geprüft werden.', 'connection_disconnected' => ':provider wurde getrennt.',
    'select_page' => 'Facebook-Seite auswählen', 'select_page_hint' => 'Wählen Sie die Seite, die für Facebook und das verknüpfte Instagram-Professional-Konto verwendet werden soll.',
    'page_without_instagram' => 'ohne verknüpftes Instagram-Konto', 'use_page' => 'Diese Seite verwenden',
    'mini_app_entry' => 'Mini App Website-Adresse für BotFather',
    'youtube' => ['public_url' => 'Öffentliche Kanal-URL', 'oauth_client_id' => 'Google OAuth Client-ID', 'oauth_client_secret' => 'Google OAuth Client-Secret', 'hint' => 'Der Administrator hinterlegt zuerst Client-ID und Client-Secret. Danach kann das Konto über „Konto verbinden“ autorisiert werden; Kanal-ID und Tokens werden automatisch übernommen.'],
    'x' => ['public_url' => 'Öffentliche Profil-URL', 'oauth_client_id' => 'X OAuth Client-ID', 'oauth_client_secret' => 'X OAuth Client-Secret', 'hint' => 'Der Administrator hinterlegt zuerst die Client-ID; bei einer vertraulichen OAuth-App zusätzlich das Client-Secret. Danach kann das Konto über „Konto verbinden“ autorisiert werden; Benutzer-ID und Tokens werden automatisch übernommen.'],
    'facebook' => ['public_url' => 'Öffentliche Seiten-URL', 'oauth_client_id' => 'Meta App-ID', 'oauth_client_secret' => 'Meta App-Secret', 'hint' => 'Meta App-ID und App-Secret speichern, die angezeigte Redirect URI in der Meta-App eintragen und dann das Konto verbinden. Seite, Berechtigungen und Page Token werden sicher über OAuth übernommen.'],
    'instagram' => ['public_url' => 'Öffentliche Profil-URL', 'hint' => 'Instagram nutzt dieselbe Meta-Verbindung und denselben geschützten Page Token wie Facebook. Das Professional-Konto muss mit der ausgewählten Facebook-Seite verknüpft sein; es werden keine doppelten Zugangsdaten gespeichert.'],
    'telegram' => ['public_url' => 'Öffentliche Kanal-URL', 'external_id' => 'Zielkanal / Chat-ID (z. B. @mein_kanal)', 'api_key' => 'Bot Token', 'bot_username' => 'Bot-Benutzername ohne @', 'mini_app_enabled' => 'Main Mini App ist in BotFather eingerichtet',
        'hint' => 'Für Kanal-Posts: Bot Token aus BotFather und Chat-ID des öffentlichen Kanals; den Bot als Administrator mit Veröffentlichungsrechten hinzufügen. Die Kanal-URL ist optional. Für die Mini App in BotFather die Website-Adresse als Main Mini App einrichten und den Bot-Benutzernamen speichern. Mini App ohne Kanal ist möglich. Das Häkchen erst nach Einrichtung aktivieren; es registriert die App nicht. OAuth und Webhook werden hier nicht benötigt.'],
    'tiktok' => ['public_url' => 'Öffentliche Profil-URL', 'hint' => 'Nur ein Profil-Link. Automatisches Publishing ist in dieser Installation nicht verfügbar; keine Tokens eingeben.'],
    'linkedin' => ['public_url' => 'Öffentliche Profil-/Seiten-URL', 'hint' => 'Nur ein Profil-Link. Ein Publishing-Connector ist nicht implementiert; keine Tokens eingeben.'],
];
