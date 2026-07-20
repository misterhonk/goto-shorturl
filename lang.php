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
    'Wichtig: Lege das Passwort jetzt sofort fest. Solange keines gesetzt ist, könnte jede Person mit dieser Adresse es festlegen.'
        => 'Important: set the password right now. As long as none is set, anyone with this address could set it.',
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
    'Aktiv ab (optional)'                => 'Active from (optional)',
    'Weitere Ziele – Rotation (optional)' => 'Additional targets – rotation (optional)',
    'eine URL je Zeile; optional Gewicht anhängen, z. B. „https://ziel.de 3“'
        => 'one URL per line; optionally append a weight, e.g. “https://target.com 3”',
    'Ist mindestens ein weiteres Ziel gesetzt, wird bei jedem Aufruf zufällig (gewichtet) eines gewählt.'
        => 'If at least one additional target is set, one is picked at random (weighted) on each visit.',
    'Rotation: Ziel wird bei jedem Aufruf gewählt' => 'Rotation: target chosen on each visit',
    'Rotation'                           => 'Rotation',
    'Startdatum entfernen'               => 'Remove start date',
    'Vor diesem Tag ist der Link noch nicht aktiv' => 'Before this day the link is not active yet',
    'Wird erst ab diesem Tag aktiv'      => 'Becomes active on this day',
    'ab'                                 => 'from',
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
    'QR-Etikettenbogen drucken'          => 'Print QR label sheet',
    'Ausgewählte oder alle Links als Etikettenbogen drucken' => 'Print selected or all links as a label sheet',
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
    'Nur Lesen (read-only)'              => 'Read only',
    'Nur-Lese-Token'                     => 'Read-only token',
    'nur Lesen'                          => 'read only',
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

    // Geräte, Ablauf-Ersatz, Titel-Fetch, Duplikat, Update-Check
    'Angemeldete Geräte'                 => 'Signed-in devices',
    'Geräte mit aktivem „Angemeldet bleiben"-Zugang. Diese überspringen beim Login auch die 2FA-Abfrage – unbekannte hier abmelden.'
        => 'Devices with active “stay logged in” access. These also skip the 2FA prompt at login – sign out any you don’t recognise.',
    'Unbekanntes Gerät'                  => 'Unknown device',
    'dieses Gerät'                       => 'this device',
    'seit %s'                            => 'since %s',
    'Gerät abgemeldet.'                  => 'Device signed out.',
    'Andere Geräte abgemeldet.'          => 'Other devices signed out.',
    'Dieses Gerät abmelden? Du wirst hier ausgeloggt.' => 'Sign out this device? You will be logged out here.',
    'Alle anderen Geräte abmelden?'      => 'Sign out all other devices?',
    'Alle anderen abmelden'              => 'Sign out all others',
    'Ziel nach Ablauf (optional)'        => 'Destination after expiry (optional)',
    'z. B. https://ziel-adresse.de/aktion-vorbei' => 'e.g. https://example.com/campaign-ended',
    'Titel von der Zielseite holen'      => 'Fetch title from the destination page',
    'Diese Ziel-URL gibt es schon als „%s".' => 'This destination URL already exists as “%s”.',
    'Aktualität'                         => 'Up to date',
    'aktuell'                            => 'up to date',
    'Version %s verfügbar'               => 'version %s available',

    // Workflow
    'Kopieren'                           => 'Copy',

    // QR-Code scannen (Reverse-QR)
    'QR-Code scannen'                    => 'Scan QR code',
    'Ziel eines bereits gedruckten Codes finden & ändern' => 'Find & change the target of an already printed code',
    'Bild wählen / Kamera'               => 'Choose image / camera',
    'Lade ein Foto/Bild eines GOTO-QR-Codes hoch – oder scanne ihn am Handy. Das Bild bleibt im Browser, es wird nichts hochgeladen.'
        => 'Upload a photo/image of a GOTO QR code – or scan it on your phone. The image stays in your browser; nothing is uploaded.',
    'QR-Decoder konnte nicht geladen werden – ist assets/jsqr.js hochgeladen?'
        => 'QR decoder could not be loaded – is assets/jsqr.js uploaded?',
    'Kein QR-Code im Bild erkannt. Versuche ein schärferes/größeres Foto.'
        => 'No QR code detected in the image. Try a sharper/larger photo.',
    'Kürzel „%s" gefunden – hier ist der zugehörige Eintrag:'
        => 'Found slug “%s” – here is the matching entry:',
    'Dieser GOTO-Code zeigt auf „%s", aber dazu gibt es (noch) keinen Eintrag.'
        => 'This GOTO code points to “%s”, but there is no entry for it (yet).',
    'Der QR-Code enthält diese Adresse:' => 'The QR code contains this address:',
    'Eintrag bearbeiten'                 => 'Edit entry',
    'Mit diesem Kürzel neu anlegen'      => 'Create with this slug',
    'Als Ziel-URL übernehmen'            => 'Use as destination URL',

    // Voll-Backup & Toter-Link-Check
    'Voll-Backup (mit Klicks & Papierkorb)' => 'Full backup (with clicks & trash)',
    'Voll-Backup wiederhergestellt (%d Link(s)).' => 'Full backup restored (%d link(s)).',
    'toter Link'                         => 'dead link',
    'Ziel nicht erreichbar (letzte Prüfung)' => 'Destination unreachable (last check)',
    'Ziel-Links auf Erreichbarkeit prüfen' => 'Check destination links',
    'noch nie geprüft'                   => 'never checked',
    '%d Link(s) geprüft – %d nicht erreichbar.' => '%d link(s) checked – %d unreachable.',
    '%d Link(s) geprüft – alle erreichbar.' => '%d link(s) checked – all reachable.',
    'Prüfung fehlgeschlagen – der Server konnte keine der Ziel-Adressen erreichen (Internetverbindung?).'
        => 'Check failed – the server could not reach any destination (no internet connection?).',

    // 2FA-Wiederherstellungs-Codes
    'Mit Wiederherstellungs-Code angemeldet. Noch %d Code(s) übrig.'
        => 'Signed in with a recovery code. %d code(s) remaining.',
    'Kein Zugriff? Nutze einen Wiederherstellungs-Code.'
        => 'No access? Use a recovery code instead.',
    'Bewahre diese Wiederherstellungs-Codes sicher auf – jeder funktioniert einmal, wenn du keinen App-Code hast. Sie werden nur jetzt angezeigt.'
        => 'Keep these recovery codes safe – each works once when you have no app code. They are shown only now.',
    'Codes kopieren'                     => 'Copy codes',
    'Wiederherstellungs-Codes übrig: %d.' => 'Recovery codes left: %d.',
    'Codes neu erzeugen'                 => 'Regenerate codes',
    'Neue Codes erzeugen? Die bisherigen werden ungültig.'
        => 'Generate new codes? The current ones become invalid.',
    'Neue Wiederherstellungs-Codes erzeugt – die alten sind ungültig.'
        => 'New recovery codes generated – the old ones are now invalid.',
    '2FA ist nicht aktiv.'               => '2FA is not active.',

    // Audit-Protokoll
    'Protokoll'                          => 'Activity log',
    'Die letzten Ereignisse (Anmeldungen, Änderungen). Nur Ereignis, Zeit und Geräte-Kennung – keine IP-Adressen.'
        => 'Recent events (sign-ins, changes). Only event, time and device label – no IP addresses.',
    'Protokoll wirklich leeren?'         => 'Really clear the activity log?',
    'Protokoll leeren'                   => 'Clear log',
    'Protokoll geleert.'                 => 'Activity log cleared.',
    'Anmeldung'                          => 'Sign-in',
    'Fehlgeschlagene Anmeldung'          => 'Failed sign-in',
    'Anmeldung per Wiederherstellungs-Code' => 'Sign-in via recovery code',
    'Falscher 2FA-Code'                  => 'Wrong 2FA code',
    'Abmeldung'                          => 'Sign-out',
    'Passwort eingerichtet'              => 'Password set up',
    'Link angelegt'                      => 'Link created',
    'Link geändert'                      => 'Link changed',
    'Link gelöscht'                      => 'Link deleted',
    'Sammel-Aktion'                      => 'Bulk action',
    'Wiederhergestellt'                  => 'Restored',
    'Endgültig gelöscht'                 => 'Permanently deleted',
    'Import'                             => 'Import',
    'Gruppe angelegt'                    => 'Group created',
    'Gruppe umbenannt'                   => 'Group renamed',
    'Gruppe gelöscht'                    => 'Group deleted',
    'Passwort geändert'                  => 'Password changed',
    'API-Token erstellt'                 => 'API token created',
    'API-Token widerrufen'               => 'API token revoked',
    'Gerät abgemeldet'                   => 'Device signed out',
    'Andere Geräte abgemeldet'           => 'Other devices signed out',
    '2FA aktiviert'                      => '2FA enabled',
    '2FA deaktiviert'                    => '2FA disabled',
    'Recovery-Codes erneuert'            => 'Recovery codes renewed',

    // Werkzeugkasten & Empty-State
    'Einstellungen & Werkzeuge'          => 'Settings & tools',
    'Füge oben deine erste Ziel-URL ein – ein Kürzel wird automatisch erzeugt.'
        => 'Paste your first destination URL above – a slug is generated automatically.',

    // Gesamt-Statistik, CSV & Footer
    'Alle Links'                         => 'All links',
    'Klick-Statistik (CSV)'              => 'Click statistics (CSV)',
    'datum;kuerzel;aufrufe'              => 'date;slug;clicks',
    'gesamt'                             => 'total',
    'GOTO-Version'                       => 'GOTO version',
    'Handbuch'                           => 'Manual',
    'Problem melden'                     => 'Report an issue',
    'datenbanklos · DSGVO-freundlich · MIT-Lizenz' => 'no database · GDPR-friendly · MIT license',

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
    'Als Standard merken'                => 'Save as default',
    'Standard zurücksetzen'              => 'Reset default',
    'Aktuelle Einstellungen (Farben, Größe, Rand, Fehlerkorrektur, Logo) für neue QR-Codes merken'
        => 'Remember the current settings (colours, size, margin, error correction, logo) for new QR codes',
    'Aufrufe nach Quelle'                => 'Clicks by source',
    'Quelle (für Statistik)'             => 'Source (for stats)',
    'z. B. flyer, plakat'                => 'e.g. flyer, poster',
    'Der QR zählt Aufrufe unter dieser Quelle – sichtbar im Klick-Verlauf. Der Marker wird nicht an das Ziel weitergegeben.'
        => 'The QR counts clicks under this source – shown in the click history. The marker is not passed on to the target.',

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

    // Zwei-Faktor-Authentifizierung
    'Zwei-Faktor-Authentifizierung (2FA)' => 'Two-factor authentication (2FA)',
    '2FA ist aktiv. Beim Anmelden wird zusätzlich ein Code aus deiner Authenticator-App abgefragt. „Angemeldet bleiben“-Geräte überspringen die Abfrage.'
        => '2FA is enabled. Signing in additionally asks for a code from your authenticator app. “Stay logged in” devices skip the prompt.',
    '2FA deaktivieren'                   => 'Disable 2FA',
    '2FA einrichten'                     => 'Set up 2FA',
    'Scanne den QR-Code mit deiner Authenticator-App (z. B. Apple Passwörter, Google Authenticator) und bestätige mit einem Code:'
        => 'Scan the QR code with your authenticator app (e.g. Apple Passwords, Google Authenticator) and confirm with a code:',
    'Oder Secret manuell eintragen:'     => 'Or enter the secret manually:',
    'Code aus der App'                   => 'Code from the app',
    'Aktivieren'                         => 'Enable',
    'Kein Einrichtungsvorgang aktiv.'    => 'No setup in progress.',
    'Falscher Code.'                     => 'Wrong code.',
    'Zwei-Faktor-Authentifizierung aktiviert.'   => 'Two-factor authentication enabled.',
    'Zwei-Faktor-Authentifizierung deaktiviert.' => 'Two-factor authentication disabled.',
    'Schütze die Anmeldung zusätzlich mit Einmal-Codes aus einer Authenticator-App (TOTP). Funktioniert komplett offline und ohne Datenbank.'
        => 'Additionally protect sign-in with one-time codes from an authenticator app (TOTP). Works fully offline, no database needed.',
    'Sicherheits-Code aus der Authenticator-App' => 'Security code from your authenticator app',
    'Bestätigen'                         => 'Confirm',
    'Zurück zur Anmeldung'               => 'Back to login',

    // QR-Logo
    'Logo in der Mitte'                  => 'Center logo',
    '– ohne –'                           => '– none –',
    'Eigenes Bild …'                     => 'Custom image …',
    'Mit Logo wird Fehlerkorrektur H verwendet. Das Bild bleibt im Browser – es wird nichts hochgeladen.'
        => 'With a logo, error correction H is used. The image stays in your browser – nothing is uploaded.',

    // Diagnose
    'Diagnose'                           => 'Diagnostics',
    'Fehler'                             => 'Error',
    'PHP-Version (mind. 8.0)'            => 'PHP version (8.0+)',
    'mbstring-Erweiterung'               => 'mbstring extension',
    'vorhanden'                          => 'available',
    'fehlt – Texte mit Umlauten werden ggf. falsch gekürzt' => 'missing – non-ASCII text may be truncated incorrectly',
    'Datenverzeichnis beschreibbar'      => 'Data directory writable',
    'urls.json lesbar, gültig & beschreibbar' => 'urls.json readable, valid & writable',
    'HTTPS aktiv'                        => 'HTTPS active',
    'empfohlen für den Produktivbetrieb' => 'recommended for production',
    'Backups'                            => 'Backups',
    '%d Link(s)'                         => '%d link(s)',
    '%d Tages-Backup(s)'                 => '%d daily backup(s)',
    'noch keine – entstehen beim ersten Speichern' => 'none yet – created on first save',
    'Datenschutz: urls.json ist per HTTP gesperrt' => 'Privacy: urls.json is blocked over HTTP',
    'URL-Rewriting: Kurzlinks erreichen GOTO' => 'URL rewriting: short links reach GOTO',
    'Die letzten beiden Prüfungen laufen beim Öffnen dieses Bereichs direkt im Browser.'
        => 'The last two checks run in your browser when this section is opened.',

    // Vorschau-Zwischenseite & Anlege-Formular
    'Weitere Optionen'                   => 'More options',
    'Kürzel · Gruppe · Ablauf · Titel · Passwort · Vorschau' => 'slug · group · expiry · title · password · preview',
    'Vorschau-Seite vor der Weiterleitung' => 'Preview page before redirecting',
    'Vorschau-Seite aktiv'               => 'Preview page enabled',
    'Jetzt weiter'                       => 'Continue now',
    'Automatische Weiterleitung in %s Sekunden …' => 'Redirecting automatically in %s seconds …',

    // Öffentliche Fehlerseiten (index.php)
    'Nicht gefunden'                     => 'Not found',
    'Abgelaufen'                         => 'Expired',
    'Kurz-URL nicht gefunden'            => 'Short URL not found',
    'Link abgelaufen'                    => 'Link expired',
    'Diese Kurz-URL existiert nicht (mehr).' => 'This short URL does not exist (anymore).',
    'Dieser Link ist abgelaufen.'        => 'This link has expired.',
    'Noch nicht aktiv'                   => 'Not active yet',
    'Link noch nicht aktiv'              => 'Link not active yet',
    'Dieser Link ist noch nicht aktiv.'  => 'This link is not active yet.',
];
