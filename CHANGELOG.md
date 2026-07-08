# Changelog

Alle nennenswerten Änderungen an GOTO. Format orientiert sich an
[Keep a Changelog](https://keepachangelog.com/de/).

## [Unreleased]

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

[Unreleased]: https://github.com/misterhonk/goto-shorturl/compare/v0.7.0...HEAD
[0.7.0]: https://github.com/misterhonk/goto-shorturl/compare/v0.6.0...v0.7.0
[0.6.0]: https://github.com/misterhonk/goto-shorturl/compare/v0.5.0...v0.6.0
[0.5.0]: https://github.com/misterhonk/goto-shorturl/compare/v0.4.0...v0.5.0
[0.4.0]: https://github.com/misterhonk/goto-shorturl/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/misterhonk/goto-shorturl/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/misterhonk/goto-shorturl/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/misterhonk/goto-shorturl/releases/tag/v0.1.0
