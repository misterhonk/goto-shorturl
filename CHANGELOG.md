# Changelog

Alle nennenswerten Änderungen an GOTO. Format orientiert sich an
[Keep a Changelog](https://keepachangelog.com/de/).

## [Unreleased]

### Hinzugefügt
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
