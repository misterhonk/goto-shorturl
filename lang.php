<?php
// Englische Übersetzungen. Schlüssel = deutscher Originaltext (so wie er an t() übergeben
// wird). Fehlt ein Eintrag, fällt t() automatisch auf Deutsch zurück.

return [
    // Kopf / allgemein / Switcher
    'URL-Weiterleitungen &amp; QR-Codes' => 'URL redirects &amp; QR codes',
    'Einrichtung'                        => 'Setup',
    'Abmelden'                           => 'Log out',
    'Anmelden'                           => 'Log in',
    'Passwort'                           => 'Password',
    'Angemeldet bleiben'                 => 'Stay logged in',
    'System'                             => 'System',
    'Hell'                               => 'Light',
    'Dunkel'                             => 'Dark',
    'Darstellung'                        => 'Appearance',
    'Sprache'                            => 'Language',

    // Login / Setup
    'Sitzung abgelaufen – bitte erneut anmelden.' => 'Session expired – please log in again.',
    'Falsches Passwort.'                          => 'Wrong password.',
    'Lege ein Passwort fest – es wird sicher (bcrypt) gespeichert. Danach kannst du dich direkt anmelden.'
        => 'Set a password – it is stored securely (bcrypt). Then you can log in right away.',
    'Passwort festlegen (mind. 8 Zeichen)' => 'Set password (min. 8 characters)',
    'Passwort speichern'                   => 'Save password',
    'Bitte mindestens 8 Zeichen wählen.'   => 'Please choose at least 8 characters.',
    'Passwort gespeichert – du kannst dich jetzt anmelden.' => 'Password saved – you can log in now.',

    // Anlege-Formular
    'Ziel-URL'                           => 'Target URL',
    'Gewünschte Short-URL'               => 'Desired short URL',
    'leer lassen für zufälligen Wert'    => 'leave empty for a random value',
    'Gruppe'                             => 'Group',
    '– ohne Gruppe –'                    => '– no group –',
    'Ablaufdatum (optional)'             => 'Expiry date (optional)',
    'Ablauf entfernen'                   => 'Remove expiry',
    'Titel / Notiz'                      => 'Title / note',
    'Hinzufügen'                         => 'Add',
    'Neue Gruppe / Projekt'              => 'New group / project',
    'Gruppe anlegen'                     => 'Create group',
    'Nach diesem Tag ist der Link gesperrt' => 'After this day the link is disabled',

    // Listen-Steuerung / Bulk
    'Verschieben'                        => 'Move',
    'Löschen'                            => 'Delete',
    '%d markiert'                        => '%d selected',
    'Zähler&nbsp;0'                      => 'Reset count',
    'Sortieren'                          => 'Sort',
    'Anzeigen'                           => 'Show',
    'Neueste zuerst'                     => 'Newest first',
    'Älteste zuerst'                     => 'Oldest first',
    'Meiste Aufrufe'                     => 'Most clicks',
    'A – Z (Kürzel)'                     => 'A – Z (slug)',
    'Alle'                               => 'All',
    'Nur aktive'                         => 'Active only',
    'Nur abgelaufene'                    => 'Expired only',
    'Suchen … Kürzel, Titel, URL oder Gruppe' => 'Search … slug, title, URL or group',
    'Keine Treffer für die Suche.'       => 'No matching links.',

    // Gruppen / Zeilen
    'Umbenennen'                         => 'Rename',
    'Speichern'                          => 'Save',
    'Abbrechen'                          => 'Cancel',
    'Ohne Gruppe'                        => 'No group',
    'Keine Links in dieser Gruppe.'      => 'No links in this group.',
    'Noch keine Links angelegt.'         => 'No links yet.',
    'QR-Code'                            => 'QR code',
    'Kurzlink kopieren'                  => 'Copy short link',
    'Bearbeiten'                         => 'Edit',
    'Aufrufe gesamt (anonym) – Verlauf der letzten 14 Tage' => 'Total clicks (anonymous) – last 14 days',

    // Werkzeuge
    'Export / Import'                    => 'Export / import',
    'Export – JSON herunterladen'        => 'Export – download JSON',
    'Alle QR-Codes (ZIP)'                => 'All QR codes (ZIP)',
    'mit Bestand zusammenführen'         => 'merge with existing',
    'Importieren'                        => 'Import',
    'Passwort ändern'                    => 'Change password',
    'Aktuelles Passwort'                 => 'Current password',
    'Neues Passwort (mind. 8 Zeichen)'   => 'New password (min. 8 characters)',
    'Papierkorb'                         => 'Trash',
    'Wiederherstellen'                   => 'Restore',
    'Endgültig löschen'                  => 'Delete permanently',
    'Papierkorb leeren'                  => 'Empty trash',
    '„%s“ wirklich löschen?'             => 'Delete “%s”?',
    'Markierte Links wirklich löschen?'  => 'Delete the selected links?',
    'Gruppe „%s“ löschen? Links wandern zu „ohne Gruppe".' => 'Delete group “%s”? Links move to “no group”.',
    '„%s“ endgültig löschen?'            => 'Delete “%s” permanently?',
    'Papierkorb endgültig leeren?'       => 'Empty the trash permanently?',
    'Gelöschte Links landen hier und leiten nicht mehr weiter. Wiederherstellen bringt auch den Klick-Zähler zurück.'
        => 'Deleted links land here and no longer redirect. Restoring also brings back the click counter.',

    // QR-Dialog
    'Schließen'                          => 'Close',
    'Fehlerkorrektur'                    => 'Error correction',
    'Modulgröße (px)'                    => 'Module size (px)',
    'Rand (Module)'                      => 'Margin (modules)',
    'Vordergrund'                        => 'Foreground',
    'Hintergrund'                        => 'Background',
    'L – niedrig (7 %)'                  => 'L – low (7%)',
    'M – mittel (15 %)'                  => 'M – medium (15%)',
    'Q – hoch (25 %)'                    => 'Q – high (25%)',
    'H – maximal (30 %)'                 => 'H – maximum (30%)',

    // Flash – Links
    '„%s“ angelegt.'                     => '“%s” created.',
    'Kürzel „%s“ existiert bereits.'     => 'Slug “%s” already exists.',
    '„%s“ ist reserviert – bitte ein anderes Kürzel wählen.' => '“%s” is reserved – please choose another slug.',
    'Bitte eine gültige http(s)-URL angeben.' => 'Please provide a valid http(s) URL.',
    'Bitte eine gültige Ziel-URL angeben.'    => 'Please provide a valid target URL.',
    'Konnte nicht speichern – Schreibrechte für urls.json prüfen.' => 'Could not save – check write permissions for urls.json.',
    'Konnte nicht speichern.'            => 'Could not save.',
    'Unbekanntes Kürzel.'                => 'Unknown slug.',
    'Bitte eine Short-URL angeben.'      => 'Please provide a short URL.',
    '„%s“ ist reserviert.'               => '“%s” is reserved.',
    '„%s“ ist bereits vergeben.'         => '“%s” is already taken.',
    'Kürzel „%s“ ist bereits vergeben.'  => 'Slug “%s” is already taken.',
    'Konnte nicht speichern – Schreibrechte prüfen.' => 'Could not save – check write permissions.',
    '„%s“ aktualisiert.'                 => '“%s” updated.',
    '„%s“ verschoben.'                   => '“%s” moved.',
    '„%s“ in den Papierkorb verschoben.' => '“%s” moved to trash.',
    'Keine Links markiert.'              => 'No links selected.',
    '%d Link(s) verschoben.'             => '%d link(s) moved.',
    '%d Link(s) in den Papierkorb verschoben.' => '%d link(s) moved to trash.',
    'Zähler von %d Link(s) zurückgesetzt.'     => 'Counter of %d link(s) reset.',
    '%d Link(s) importiert.'             => '%d link(s) imported.',
    'Keine gültigen Einträge gefunden (JSON oder CSV erwartet).' => 'No valid entries found (JSON or CSV expected).',
    'Keine Datei empfangen.'             => 'No file received.',
    '„%s“ wiederhergestellt.'            => '“%s” restored.',
    'Eintrag nicht im Papierkorb.'       => 'Entry not in trash.',
    '„%s“ endgültig gelöscht.'           => '“%s” permanently deleted.',
    'Papierkorb geleert.'                => 'Trash emptied.',

    // Flash – Gruppen
    'Bitte einen Gruppennamen angeben.'  => 'Please provide a group name.',
    'Gruppe „%s“ existiert bereits.'     => 'Group “%s” already exists.',
    'Gruppe „%s“ angelegt.'              => 'Group “%s” created.',
    'Unbekannte Gruppe.'                 => 'Unknown group.',
    'Bitte einen Namen angeben.'         => 'Please provide a name.',
    'Gruppe umbenannt.'                  => 'Group renamed.',
    'Gruppe „%s“ gelöscht.'              => 'Group “%s” deleted.',

    // Flash – Passwort / Login
    'Das Passwort ist in config.php/ENV festgelegt und hier nicht änderbar.'
        => 'The password is set in config.php/ENV and cannot be changed here.',
    'Aktuelles Passwort ist falsch.'     => 'Current password is wrong.',
    'Neues Passwort: mindestens 8 Zeichen.' => 'New password: at least 8 characters.',
    'Passwort geändert.'                 => 'Password changed.',
    'Zu viele Fehlversuche. Bitte %d Min. warten.' => 'Too many failed attempts. Please wait %d min.',

    // API-Zugang
    'API-Zugang'                         => 'API access',
    'Token erstellt – jetzt kopieren, er wird nur dieses eine Mal angezeigt:'
        => 'Token created – copy it now, it is shown only this once:',
    'Token kopieren'                     => 'Copy token',
    'Token-Name'                         => 'Token name',
    'z. B. Doku-Skript'                  => 'e.g. docs script',
    'Token erstellen'                    => 'Create token',
    'Widerrufen'                         => 'Revoke',
    'erstellt %s'                        => 'created %s',
    'zuletzt %s'                         => 'last used %s',
    'nie genutzt'                        => 'never used',
    '%d Aufrufe'                         => '%d calls',
    'Token „%s“ widerrufen?'             => 'Revoke token “%s”?',
    'Kurz-URLs per Skript anlegen (POST an %sapi.php):'
        => 'Create short URLs via script (POST to %sapi.php):',
    'Felder: url (Pflicht), slug, group, title, expires (JJJJ-MM-TT). Antwort als JSON, Limit %d Anfragen/Minute je Token.'
        => 'Fields: url (required), slug, group, title, expires (YYYY-MM-DD). Response is JSON, limit %d requests/minute per token.',
    'API-Token „%s“ erstellt.'          => 'API token “%s” created.',
    'API-Token widerrufen.'             => 'API token revoked.',
    'Bitte einen Namen für den Token angeben.' => 'Please provide a name for the token.',

    // Statistik-Kacheln & Klick-Verlauf
    'Aufrufe gesamt'                     => 'Total clicks',
    'Heute'                              => 'Today',
    'Letzte 7 Tage'                      => 'Last 7 days',
    'Top-Link'                           => 'Top link',
    'Gesamt'                             => 'Total',
    'Aufrufe'                            => 'Clicks',
    'Klick-Verlauf'                      => 'Click history',
    'Klick-Verlauf anzeigen'             => 'Show click history',
    'Noch keine Aufrufe im Zeitraum.'    => 'No clicks in this range.',
    'Zeitraum'                           => 'Time range',

    // Bot-Vorschau / Link-Preview (index.php)
    'Kurzlink'                           => 'Short link',
    'Weiterleitung zu %s'                => 'Redirecting to %s',
    'Weiter zur Zielseite'               => 'Continue to destination',

    // Passwortgeschützte Links
    'Passwortgeschützter Link'           => 'Password-protected link',
    'Dieser Link ist passwortgeschützt.' => 'This link is password-protected.',
    'Weiter'                             => 'Continue',
    'Passwort (optional)'                => 'Password (optional)',
    'Link-Passwort'                      => 'Link password',
    'leer = ohne'                        => 'empty = none',
    'gesetzt – neues eingeben zum Ändern' => 'set – type a new one to change it',
    'Passwort entfernen'                 => 'Remove password',
    'passwortgeschützt'                  => 'password-protected',

    // Öffentliche Fehlerseiten (index.php)
    'Nicht gefunden'                     => 'Not found',
    'Abgelaufen'                         => 'Expired',
    'Kurz-URL nicht gefunden'            => 'Short URL not found',
    'Link abgelaufen'                    => 'Link expired',
    'Diese Kurz-URL existiert nicht (mehr).' => 'This short URL does not exist (anymore).',
    'Dieser Link ist abgelaufen.'        => 'This link has expired.',
];
