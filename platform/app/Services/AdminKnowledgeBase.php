<?php

namespace App\Services;

/**
 * Curated, permission-safe operating manual for the Media Desktop.
 * It is intentionally explicit: the admin assistant must answer from this
 * knowledge only and must not infer access to private credentials or data.
 */
class AdminKnowledgeBase
{
    public function entries(): array
    {
        return [
            ['id'=>'overview','title'=>'Übersicht','keywords'=>['dashboard','übersicht','aufgaben','fortschritt','nächste schritte'],'content'=>'Die Übersicht bündelt Projekte, Aufgaben, Medien, Prüfungen, Veröffentlichungen, Live, Posteingang und Systemstatus. Widgets können verschoben, verbreitert und individuell angeordnet werden. Eine Empfehlung ändert oder veröffentlicht nichts automatisch.'],
            ['id'=>'videos','title'=>'Videos','keywords'=>['video','short','playlist','wiedergabe','prüfung'],'content'=>'Videos zeigt lokal importierte Videos und Shorts. Zwischen Karten- und Listenansicht wechseln, über Grundfilter suchen und ein Material öffnen. Bearbeitung erfolgt im eigenen Editorfenster. Lokale Videos prüfen Dateien und Wiedergabe; Import oder Prüfung veröffentlicht nichts.'],
            ['id'=>'posts','title'=>'Beiträge','keywords'=>['beitrag','artikel','text','struktur','seo','thema'],'content'=>'Beiträge enthalten Titel, Originaltext, Medien, Themen, SEO, Plattformtexte und Veröffentlichungsstatus. Ein Material wird geöffnet und im separaten Editorfenster bearbeitet. Die KI-Strukturprüfung formatiert nur HTML-Struktur und Absätze; sie verändert nicht den Textinhalt.'],
            ['id'=>'books-pdf','title'=>'Bücher und PDF','keywords'=>['buch','pdf','preis','leseprobe','verkauf'],'content'=>'Bücher und PDF verwaltet Produkte, PDF-Dateien, Cover, Leseprobe, Preis und öffentliche Metadaten. Ein PDF-Upload erzeugt zunächst einen Entwurf. Der private vollständige Download bleibt geschützt; die eigene Leseprobe darf nicht zugleich der bezahlte vollständige Download sein.'],
            ['id'=>'media','title'=>'Media Library','keywords'=>['datei','original','upload','media','bild','audio'],'content'=>'Die Media Library verwaltet gespeicherte Originale und deren Verbindungen zu Materialien. Importieren registriert Dateien, ersetzt keine manuellen Texte und veröffentlicht nicht automatisch. Dateien können geprüft, verbunden und aus dem jeweiligen Editor verwendet werden.'],
            ['id'=>'imports','title'=>'Import Center','keywords'=>['import','youtube','takeout','csv','archiv','migration'],'content'=>'Das Import Center übernimmt lokale Archive, öffentliche Links, Serverordner und Google Takeout. Der Import bewahrt Originale, überspringt bereits erledigte Objekte und veröffentlicht nicht. Nach dem Import werden Ergebnisse und unklare Objekte in der Review-Warteschlange angezeigt.'],
            ['id'=>'publishing','title'=>'Publishing','keywords'=>['publishing','veröffentlichen','youtube','telegram','facebook','instagram','x','kanal'],'content'=>'Publishing ist nur das Register externer Veröffentlichungen. Neue Veröffentlichungen werden am Material oder im Live-Bereich ausgelöst. Hier sieht man Ziel, Status, Datum und verfügbare Aktivierung, Deaktivierung, Wiederholung oder Löschung. Website-Veröffentlichungen gehören nicht in dieses Register.'],
            ['id'=>'live-studio','title'=>'Live Studio','keywords'=>['live','stream','obs','browser','rtmp','whip'],'content'=>'Live Studio verwaltet Live-Ereignisse, Zeitplan, Poster, OBS- oder Browser-Studio-Verbindung und die öffentliche Vorschau. Vorbereiten startet noch keinen Stream. Aktive Sessions schützen das Fenster vor versehentlichem Schließen.'],
            ['id'=>'projects','title'=>'Projekte','keywords'=>['projekt','team','fortschritt','timeline'],'content'=>'Projekte bündeln Material, Aufgaben und Bücher. Status, Verantwortliche, Termine, Tags, nächste Aktion und Fortschritt werden am Projekt geführt. Verknüpfte Einträge öffnen jeweils ihr eigenes Arbeitsfenster.'],
            ['id'=>'tasks','title'=>'Aufgaben','keywords'=>['aufgabe','frist','priorität','checkliste','verantwortlich'],'content'=>'Aufgaben haben Status, Priorität, Verantwortliche, Frist, Inhalt, Projekt und Checkliste. Die Board-Ansicht zeigt die aktuelle Ergebnisseite; Verschieben ändert den Aufgabenstatus und bewahrt die Historie.'],
            ['id'=>'calendar','title'=>'Kalender','keywords'=>['kalender','termin','planung','woche','monat'],'content'=>'Der Kalender bündelt Projektfristen, Aufgaben, geplante Veröffentlichungen und Live-Termine. Ansichten sind Monat, Woche und Liste. Filter schränken nur die Anzeige ein; sie ändern keine Termine.'],
            ['id'=>'topics','title'=>'Themen und Kategorien','keywords'=>['thema','kategorie','taxonomie','tag','filter'],'content'=>'Themen und Kategorien strukturieren Beiträge, Videos und Bücher. Kategorien können hierarchisch sein. Das Zuordnen eines Themas veröffentlicht keinen Inhalt; öffentliche Filter nutzen die gespeicherten Zuordnungen.'],
            ['id'=>'community','title'=>'Community','keywords'=>['community','kommentar','moderation','antwort'],'content'=>'Community ist der Posteingang für importierte und lokale Nachrichten. Nachrichten können gelesen, moderiert und – nur für unterstützte Ziele und Berechtigungen – beantwortet werden. Ein Archivimport selbst sendet keine Antwort an externe Dienste.'],
            ['id'=>'newsletter','title'=>'Newsletter','keywords'=>['newsletter','abonnent','email','kampagne','double opt in'],'content'=>'Newsletter verwaltet bestätigte Abonnenten und Kampagnen. CSV-Importe aktivieren keine Kontakte automatisch. Eine Kampagne wird vor dem Versand ausdrücklich bestätigt und ist nach dem Start nicht frei editierbar.'],
            ['id'=>'analytics','title'=>'Analytics','keywords'=>['analytics','statistik','grafik','besucher','ereignis'],'content'=>'Analytics zeigt ausschließlich First-Party-Daten der eigenen Website: Ereignisse, Seiten, Materialien, Video-Wiedergaben, Registrierungen und Verkäufe. Externe Kanalzahlen werden nicht erfunden. Rohdaten bleiben hinter aufklappbaren Details verfügbar.'],
            ['id'=>'integrations','title'=>'Integrationen','keywords'=>['integration','stripe','google','verbindung','token'],'content'=>'Integrationen zeigt Bereitschaft und Status externer Dienste. Zugangsdaten werden nur in den geschützten Einstellungen gespeichert und nicht im Dashboard oder in Antworten angezeigt.'],
            ['id'=>'settings','title'=>'Einstellungen','keywords'=>['einstellungen','ki','api key','rechte','benutzer','branding'],'content'=>'Einstellungen trennen Desktop, KI, Social Media, Publishing, Integrationen, Benutzerrechte und System. Änderungen werden erst nach Speichern aktiv. API-Schlüssel und Tokens bleiben verschlüsselt; sie gehören niemals in eine Frage an die KI.'],
            ['id'=>'ai-dashboard','title'=>'KI-Dashboard','keywords'=>['ki','ai','assistent','anfrage','token','kosten','modell'],'content'=>'Das KI-Dashboard ist ein schreibgeschütztes Nutzungsprotokoll. Es zeigt Anzahl, Status, Modell, Tokenverbrauch und – sofern Preise hinterlegt sind – geschätzte Kosten. Eine Anfrage an die KI wird oben gestartet; Ergebnisse werden hier nur gelesen, nicht bearbeitet oder angewendet.'],
            ['id'=>'workflow','title'=>'Grundworkflow','keywords'=>['workflow','wie','anfangen','veröffentlichen','editor'],'content'=>'Datei oder Archiv importieren, Original in Media Library prüfen, Material im eigenen Editor bearbeiten, Freigabe und Plattformtexte prüfen, Veröffentlichung am Material oder im Live-Bereich starten und danach Publishing/Analytics zur Kontrolle verwenden.'],
        ];
    }

    public function context(string $question): array
    {
        $needle = mb_strtolower(trim($question));
        if ($needle === '') return $this->entries();
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $needle, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return collect($this->entries())->map(function (array $entry) use ($tokens) {
            $haystack = mb_strtolower($entry['title'].' '.implode(' ', $entry['keywords']).' '.$entry['content']);
            $score = collect($tokens)->sum(fn ($token) => str_contains($haystack, $token) ? 1 : 0);
            return [...$entry, '_score'=>$score];
        })->sortByDesc('_score')->take(8)->map(fn ($entry) => collect($entry)->except('_score')->all())->values()->all();
    }
}
