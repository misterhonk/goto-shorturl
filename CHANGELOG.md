# Changelog

Alle nennenswerten Änderungen an GOTO. Format orientiert sich an
[Keep a Changelog](https://keepachangelog.com/de/).

## [Unreleased]

### Geändert
- **Mobile-Ansicht überarbeitet:** kompakte Topbar (Untertitel ausgeblendet;
  Sprache als Kürzel `DE`/`EN`, Theme-Umschalter und Abmelden als Icon).
  Der Theme-Umschalter ist jetzt generell ein **Icon-Zyklus** (System → Hell →
  Dunkel, mit Monitor-/Sonne-/Mond-Icon) statt Dropdown.
- **Anlege-Formular mobil aufgeräumt** (Feld-Aufzählung neben „Weitere Optionen"
  und der Scan-Hinweis werden auf schmalen Screens ausgeblendet).
- **Einträge & Auswahl mobil:** größere, links stehende **Custom-Checkbox** je
  Karte; alle Checkboxen sind jetzt einheitlich gestaltet (kein natives Kästchen).

## [1.1.0] – 2026-07-10

### Hinzugefügt
- **QR-Code scannen (Reverse-QR):** einen bereits gedruckten GOTO-QR-Code
  fotografieren oder hochladen – GOTO liest den enthaltenen Kurzlink, erkennt
  das Kürzel und springt direkt in dessen **Bearbeiten**-Ansicht (Ziel ändern,
  ohne den Code neu zu drucken). Unbekannte Kürzel lassen sich mit einem Klick
  neu anlegen, fremde URLs als Ziel übernehmen. Dekodierung läuft **lokal im
  Browser** über den mitgelieferten Decoder `assets/jsqr.js` (Apache-2.0, beim
  ersten Scan nachgeladen) – es wird **nichts hochgeladen**, kein externer Dienst,
  funktioniert in allen Browsern; am Handy öffnet die Bildauswahl direkt die Kamera.

## [1.0.0] – 2026-07-10

Erste stabile Version. GOTO ist ein vollständiger, datenbankloser
URL-Shortener & QR-Generator mit Admin-UI im Apple-Look, CRUD-API, 2FA,
Statistik und Server-Support für Apache/nginx/Caddy. Aktualisierter
Screenshot; README/QUICKSTART auf den aktuellen Stand gebracht.

### Hinzugefügt
- **Kopier-Knopf im „angelegt"-Toast:** nach dem Anlegen eines Links steht der
  Kurzlink direkt zum Kopieren im Toast (bleibt länger stehen).
- **Tastatur-Shortcuts:** `/` fokussiert die Suche, `n` das URL-Feld.
- **Einklappbare Gruppen** (Zustand pro Gruppe gemerkt); Sortierung und Filter
  werden ebenfalls pro Browser gemerkt.
- **2FA-Wiederherstellungs-Codes:** bei der Einrichtung werden 8 Einmal-Codes
  erzeugt (nur gehasht gespeichert, einmalig angezeigt) – Login klappt damit
  auch ohne Authenticator-Gerät. Rest-Anzahl sichtbar, neu erzeugbar; „Handy
  verloren" braucht keine FTP-Chirurgie mehr.
- **Aktivitäts-Protokoll:** die letzten ~100 Ereignisse (Anmeldungen inkl.
  Fehlversuche, Link-/Gruppen-/Token-/Geräte-/2FA-Änderungen) mit Zeit und
  Geräte-Kennung, einsehbar und leerbar in der Toolbox. DSGVO-sparsam – **keine
  IP-Adressen**.

- **Voll-Backup:** Export „Voll-Backup (mit Klicks & Papierkorb)" bündelt Links,
  Klick-Zähler und Papierkorb in einer Datei; der Import erkennt dieses Format
  und stellt alles wieder her – für den Umzug auf einen anderen Server samt
  Historie.
- **Toter-Link-Prüfung:** Knopf in der Diagnose prüft alle Ziel-URLs parallel
  (HEAD, SSRF-Host-Schutz); nicht erreichbare (404/410/5xx/Timeout) bekommen in
  der Liste einen „toter Link"-Hinweis. Ist der Server komplett offline, wird
  statt falscher Markierungen gewarnt.

### Geändert
- Toast- und Bulkbar-Animationen respektieren jetzt `prefers-reduced-motion`.

## [0.9.0] – 2026-07-09

### Hinzugefügt
- **Geräte-Verwaltung:** aktive „Angemeldet bleiben"-Geräte (Browser · System,
  angemeldet seit / zuletzt gesehen) im Admin einsehen und einzeln oder „alle
  anderen" abmelden – wichtig, weil diese Geräte auch die 2FA-Abfrage überspringen.
- **Ablauf-Ersatz-URL:** je Link optional ein Ziel, auf das nach Ablauf statt
  der `410`-Seite weitergeleitet wird (Admin-Feld und API-Feld `expires_url`;
  Query-Parameter werden mitgereicht).
- **Titel-Autofill:** Knopf beim Anlegen holt den `<title>` der Zielseite
  (opt-in via `title_fetch`, standardmäßig an). Serverseitig **SSRF-geschützt**
  (nur öffentliche Hosts, keine Redirects, Größen-/Zeitlimit).
- **Duplikat-Hinweis:** beim Eingeben einer Ziel-URL, die es schon als anderes
  Kürzel gibt, erscheint ein dezenter Hinweis (rein im Browser).
- **Update-Hinweis in der Diagnose:** prüft opt-in (`update_check`) die neueste
  Release-Version bei GitHub und meldet, wenn eine neuere verfügbar ist.
- **Bulk-QR im gewählten Stil:** der ZIP-Export übernimmt jetzt Farben, Rand,
  Modulgröße und Logo aus dem QR-Dialog (bisher immer schwarz/weiß).

### Behoben
- **CSP blockierte die Diagnose-Browser-Checks:** der Admin-CSP fehlte
  `connect-src`, wodurch `fetch()` auf `default-src 'none'` fiel. Die
  Datenschutz- und Rewriting-Prüfung liefen dadurch ins Leere (falsches
  „OK"/„Fehler"). Jetzt `connect-src 'self'` (plus `api.github.com` bei
  aktiviertem Update-Check) – die Checks prüfen wieder echt.

## [0.8.0] – 2026-07-09

### Hinzugefügt
- **Gesamt-Statistik:** die Kachel „Aufrufe gesamt" ist jetzt anklickbar und
  öffnet den Klick-Verlauf **aller Links zusammen** (14/30/90 Tage, gleicher
  Dialog wie je Link).
- **CSV-Export der Klick-Statistik** unter „Export / Import": Tageswerte je
  Kürzel plus Gesamt-Zeilen, Semikolon-getrennt (Excel-freundlich), DE/EN.
- **Footer** im Admin mit Version, Links zu GitHub / Changelog / Handbuch /
  Issues; Versionskonstante `GOTO_VERSION` (erscheint auch in der Diagnose).

### Geändert
- **Anmelde-, 2FA- und Einrichtungs-Seite neu gestaltet:** zentriertes Layout
  mit großer Logo-Kachel und Wortmarke, dezentem Farb-Glow und sanfter
  Einblend-Animation (respektiert `prefers-reduced-motion`) – hell und dunkel.
- **UI-Feinschliff:** destruktive Buttons sind jetzt ruhig (rotes Icon,
  Füllung erst beim Hover); die **Bulk-Leiste erscheint erst, wenn Links
  markiert sind**; Gruppen-Aktionen zeigen sich beim Hover/Fokus (auf Touch
  immer); die Einstellungs-Sektionen sind als Karte **„Einstellungen &
  Werkzeuge"** mit Icons gruppiert; Topbar-Umschalter randlos; die
  „Aufrufe gesamt"-Kachel zeigt ihre Klickbarkeit per Mini-Icon; freundlicher
  Empty-State beim ersten Start.

## [0.7.1] – 2026-07-09

### Sicherheit
- **Öffentliche Seiten gehärtet:** Passwort-, Vorschau- und Fehlerseiten senden
  jetzt `X-Frame-Options: DENY`, `X-Content-Type-Options`, `Referrer-Policy`,
  `X-Robots-Tag`, HSTS (bei HTTPS) und eine strenge nonce-basierte CSP mit
  `frame-ancestors 'none'`. Behebt insbesondere die **Einbettbarkeit der
  Passwort-Seite in fremde iframes** (Clickjacking).
- **Sessions:** `session.use_strict_mode` und `use_only_cookies` explizit
  aktiviert (keine von außen vorgegebenen Session-IDs).
- **Ersteinrichtung:** deutlicher Hinweis auf dem Setup-Bildschirm, das
  Passwort sofort zu setzen; README um Absicherung per `GOTO_PASSWORD_HASH`
  ergänzt.

## [0.7.0] – 2026-07-08

### Hinzugefügt
- **API-CRUD:** die HTTP-API kann jetzt vollständig lesen und schreiben –
  `GET` (Liste mit Klick-Summen bzw. `?slug=…` mit Tageswerten), `PATCH`
  (Felder ändern, Passwort setzen/entfernen, Kürzel umbenennen inkl.
  Klick-Migration) und `DELETE` (in den Papierkorb). Formular-, JSON- und
  Query-Eingaben; Passwort-Hashes werden nie ausgegeben.

### Geändert
- Klick- und Papierkorb-Helfer von `admin.php` nach `lib.php` verschoben
  (werden jetzt auch von der API genutzt).

## [0.6.0] – 2026-07-08

### Hinzugefügt
- **Caddy-Unterstützung:** neues, getestetes [`docs/Caddyfile.example`](docs/Caddyfile.example)
  (automatisches HTTPS, FPM-Adresse per `GOTO_FPM`-ENV überschreibbar).
- **Server-Testumgebung:** `docker compose --profile servers up -d` startet
  nginx (Port 8089) und Caddy (Port 8090) mit PHP-FPM gegen die echten
  Beispiel-Configs; README-Abschnitt „Serverkonfiguration".

### Behoben
- **nginx-Beispiel-Config lieferte unter `/admin` den PHP-Quelltext aus**
  (`try_files` im `location =`-Block serviert statisch statt an PHP zu
  übergeben) – jetzt `rewrite … last`. Außerdem gingen Query-Parameter im
  `try_files`-Fallback verloren (`$is_args$args` ergänzt) und der
  HTTPS-Hinweis (`fastcgi_param HTTPS on`) fehlte.

## [0.5.0] – 2026-07-08

### Hinzugefügt
- **QR-Codes mit Logo:** im QR-Dialog optional das GOTO-Logo oder ein eigenes
  Bild in der Mitte platzieren (gilt für Vorschau, PNG-/SVG-Download und
  ZIP-Batch). Das Bild bleibt komplett im Browser – kein Upload. Die
  Fehlerkorrektur wird automatisch auf H angehoben; Scanbarkeit maschinell
  verifiziert.
- **Zwei-Faktor-Authentifizierung (TOTP)** fürs Admin: Einrichtung per
  QR-Code (client-seitig gerendert) oder manuellem Secret, Codes aus jeder
  Authenticator-App (RFC 6238; SHA1/6 Stellen/30 s, ±30 s Toleranz). Secret
  liegt in `.ht_auth.json` (file-only), Login fragt nach korrektem Passwort
  zusätzlich den Code ab (mit Brute-Force-Bremse); „Angemeldet
  bleiben"-Geräte überspringen die Abfrage. Deaktivieren per Passwort;
  Notfall-Ausstieg: `totp`-Eintrag aus `.ht_auth.json` entfernen.
- **Query-Parameter-Durchreichung:** `goto/kürzel?utm_source=…` hängt die
  Parameter an die Ziel-URL an (Fragment im Ziel bleibt erhalten; funktioniert
  auch auf Passwort- und Vorschau-Seiten sowie in Rewrite-Variante B).
- **Tages-Backups:** beim ersten Speichern eines Tages wandert der Stand nach
  `backups/urls-JJJJ-MM-TT.json`; die letzten 7 Generationen bleiben erhalten
  (zusätzlich zur bisherigen `.bak`-Sicherung je Schreibvorgang).
- **Diagnose-Bereich im Admin:** Ampel-Selbsttest für Schreibrechte,
  PHP-Version, `mbstring`, Gültigkeit der `urls.json`, HTTPS und Backup-Stand;
  dazu zwei Browser-Checks (Dateischutz per `.htaccess`, URL-Rewriting –
  erkennt GOTO-Antworten am neuen `X-Goto-App`-Header).

## [0.4.0] – 2026-07-07

### Hinzugefügt
- **Vorschau-Zwischenseite** (opt-in je Link, Häkchen im Admin oder API-Feld
  `preview`): Besucher sehen Titel + Ziel-Domain im GOTO-Look und werden nach
  3 Sekunden automatisch (oder sofort per Button) weitergeleitet.
- **Passwortgeschützte Links:** je Link optional ein Passwort (im Admin beim
  Anlegen/Bearbeiten oder per API-Feld `password`). Besucher sehen eine
  Passwort-Seite im GOTO-Look; nach korrekter Eingabe folgt die Weiterleitung.
  Gespeichert wird nur ein bcrypt-Hash; 8 Fehlversuche → 15 Min. Sperre je
  Besucher; die Ziel-URL wird vor der Freigabe nirgends preisgegeben (auch
  nicht an Link-Preview-Bots); Klicks zählen erst nach Freigabe.

### Geändert
- **Anlege-Formular entschlackt** (Progressive Disclosure): eine Zeile
  „Ziel-URL + Hinzufügen" für den Schnellfall; Wunsch-Kürzel, Gruppe, Ablauf,
  Titel, Passwort, Vorschau und „Neue Gruppe" liegen unter dem aufklappbaren
  Bereich **„Weitere Optionen"** (Auf/Zu-Zustand wird pro Browser gemerkt).

## [0.3.0] – 2026-07-07

### Geändert
- **UI-Redesign im Apple-Look** (HIG-inspiriert): ruhige Flächen (`#f5f5f7`),
  Hairline-Borders, **Glas-Topbar** (sticky, Blur), **Pill-Buttons**, Apple-Blau
  als Akzent, weiche gestaffelte Schatten, Segmented Controls, eigene
  Select-Chevrons, sanfte Micro-Interactions; Dark Mode mit `#1c1c1e`-Flächen.
  Verhalten und Struktur unverändert (nur `goto.css` + additive Elemente).
- **Interne Aufräumung:** gemeinsame Basis (Bootstrap, Datenmodell, Validierungs-
  und Daten-Helfer) in neue `lib.php` ausgelagert; `index.php`, `admin.php` und
  `api.php` binden sie ein. Entfernt rund 150 Zeilen Duplikat – das Datenmodell
  ist jetzt an einer einzigen Stelle definiert. Verhalten unverändert.
- Statische Dateien (`goto.css`, `app.js`, `qr.js`) in den Unterordner `assets/`
  verschoben (CSP unverändert `'self'`). `deploy.sh` legt sie entsprechend unter
  `dist/assets/` ab.

### Hinzugefügt
- **Statistik-Kacheln** im Admin: Links gesamt, Aufrufe gesamt / heute /
  letzte 7 Tage sowie Top-Link auf einen Blick (rein lokal aus `clicks.json`).
- **Klick-Verlauf-Dialog:** Klick auf die Sparkline öffnet ein Balken-Diagramm
  der letzten 14 / 30 / 90 Tage (umschaltbar) mit Hover-Tooltip je Tag.
- **Favicon & Browser-Meta:** Favicon (SVG + ICO + Apple-Touch-Icon im
  Marken-Design) und `theme-color` (hell/dunkel) für Admin- und öffentliche Seiten.
- **Social-Media-Vorschau:** Link-Preview-Bots (WhatsApp, Slack, Telegram,
  Facebook, X, Discord, …) erhalten statt der Weiterleitung eine Open-Graph-Seite
  mit dem **Titel aus dem Eintrag**, Ziel-Host und Vorschaubild (`assets/og.png`).
  Menschen bekommen weiterhin sofort das 302; Bot-Aufrufe zählen nicht als Klick.
- **Fehlerseiten im GOTO-Look:** 404/410 jetzt als gestaltete Karte mit Logo,
  Hell-/Dunkel-Modus und Favicon (weiterhin zweisprachig).
- `deploy.sh` baut einen vollständigen Upload-Satz nach `dist/` (verhindert das
  versehentliche Vergessen einzelner Dateien beim FTP-Upload).

## [0.2.0] – 2026-06-24

### Hinzugefügt
- **HTTP-API** (`api.php`) zum Anlegen von Kurz-URLs per Skript, abgesichert
  über **API-Token** (Bearer). Token werden im Admin unter **„API-Zugang"**
  erstellt/widerrufen, serverseitig nur als Hash gespeichert und einmalig im
  Klartext angezeigt. Inkl. **Rate-Limit** (120 Anfragen/Min. je Token).
- **Papierkorb**: gelöschte Links landen im Papierkorb und lassen sich samt
  Klick-Zähler **wiederherstellen** oder endgültig löschen.
- **Klick-Statistik** als Mini-**Sparkline** (Verlauf der letzten 14 Tage,
  DSGVO-konform – nur Tageszähler, keine personenbezogenen Daten).
- **CSV-Import** zusätzlich zu JSON (Spalten `url,slug,group,title,expires`).
- **Mehrsprachigkeit (i18n)**: Oberfläche in **Deutsch oder Englisch**,
  umschaltbar im Admin (pro Browser gespeichert), Standard via `config.php`.
- **Sortieren** (Neueste / Älteste / Meiste Aufrufe / A–Z) und **Filtern**
  (alle / nur aktive / nur abgelaufene) der Linkliste.
- **Batch-Export**: alle QR-Codes auf einmal als **ZIP** herunterladen
  (lokal erzeugt, ohne externen Dienst).
- **Favicons** per `config.php` abschaltbar (`'favicons' => false`).
- **Atomare Schreibvorgänge** (Temp-Datei + `rename`) inkl. Backup-Generation
  (`urls.json.bak`) und Fallback bei beschädigter Datei.
- **Fehler-Logging** in eine geschützte `.ht_error.log`.
- **GitHub-Actions-CI** (PHP-/JS-Syntaxprüfung + QR-Encoder-Tests).

### Geändert
- **Öffentliche Fehlerseiten** (`index.php`, 404/410) sind jetzt ebenfalls
  **mehrsprachig** (DE/EN, gesteuert über `goto_lang`-Cookie bzw. `config.php`);
  die Sprache wird nur im Fehlerfall geladen, der Weiterleitungs-Pfad bleibt schlank.
- CSS/JS in `goto.css` und `app.js` ausgelagert (Browser-Caching, schlankeres `admin.php`).
- `.htaccess` sperrt nun auch `.bak`/`.tmp`; Lockout-Datei gegen Aufblähen begrenzt.

## [0.1.0]

### Erste Version
- URL-Weiterleitungen mit eigenen oder zufälligen Kürzeln, Gruppen, Titel/Notiz
  und optionalem Ablaufdatum.
- **QR-Codes** pro Link (lokal erzeugt, PNG/SVG, einstellbare Fehlerkorrektur).
- **DSGVO-konformer Klick-Zähler** (reine Zähler, keine IPs/Zeitstempel).
- Sicherheit: bcrypt-Passwort, CSRF-Schutz, Brute-Force-Bremse, gehärtete Session,
  strenge CSP, HSTS, „Angemeldet bleiben".
- Modernes, responsives UI mit Theme-Umschalter (System/Hell/Dunkel),
  Drag & Drop, Live-Suche sowie Import/Export.
- Datenbanklos – alle Daten in JSON-Dateien.

[Unreleased]: https://github.com/misterhonk/goto-shorturl/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/misterhonk/goto-shorturl/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/misterhonk/goto-shorturl/compare/v0.9.0...v1.0.0
[0.9.0]: https://github.com/misterhonk/goto-shorturl/compare/v0.8.0...v0.9.0
[0.8.0]: https://github.com/misterhonk/goto-shorturl/compare/v0.7.1...v0.8.0
[0.7.1]: https://github.com/misterhonk/goto-shorturl/compare/v0.7.0...v0.7.1
[0.7.0]: https://github.com/misterhonk/goto-shorturl/compare/v0.6.0...v0.7.0
[0.6.0]: https://github.com/misterhonk/goto-shorturl/compare/v0.5.0...v0.6.0
[0.5.0]: https://github.com/misterhonk/goto-shorturl/compare/v0.4.0...v0.5.0
[0.4.0]: https://github.com/misterhonk/goto-shorturl/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/misterhonk/goto-shorturl/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/misterhonk/goto-shorturl/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/misterhonk/goto-shorturl/releases/tag/v0.1.0
