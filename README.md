# GOTO — selbstgehosteter URL-Shortener & QR-Generator

Schlanker, datenbankloser Kurz-URL-Dienst in PHP. Verwaltet Weiterleitungen
(`deine-domain.de/goto/kürzel` → Ziel-URL), erzeugt QR-Codes lokal im Browser
und zählt Aufrufe DSGVO-konform — alles in ein paar Dateien, ohne Datenbank.

---

## Inhalt

- [Funktionen](#funktionen)
- [Aufbau & Dateien](#aufbau--dateien)
- [Installation](#installation)
- [Konfiguration](#konfiguration)
- [Bedienung](#bedienung)
- [Datenmodell](#datenmodell)
- [Sicherheit](#sicherheit)
- [Lokal testen (Docker)](#lokal-testen-docker)
- [Wartung & Fehlersuche](#wartung--fehlersuche)

---

## Funktionen

- **Kurz-URLs** mit eigenem Kürzel oder automatisch generiertem Zufalls-Slug
- **Gruppen / Projekte** zum Ordnen, inkl. Verschieben per Dropdown oder **Drag & Drop**
- **QR-Codes** pro Link — lokal erzeugt (kein externer Dienst), als **PNG oder SVG**,
  mit einstellbarer Fehlerkorrektur (L/M/Q/H), Größe, Rand und Farben
- **Klick-Zähler** — rein anonym (keine IPs, Zeitstempel oder User-Agents)
- **Titel / Notiz** je Link, **Ablaufdatum** (abgelaufene Links liefern `410 Gone`)
- **Live-Suche**, **Bulk-Aktionen** (verschieben / löschen / Zähler zurücksetzen)
- **Import / Export** als JSON
- **Inline-Validierung** der URL, **Toast-Meldungen**
- **„Angemeldet bleiben"** (sichere Token)
- Modernes, responsives UI inkl. **Dark Mode**

---

## Aufbau & Dateien

| Datei | Zweck |
|---|---|
| `index.php` | Öffentliche Weiterleitung + Klick-Zähler + Ablauf-Prüfung |
| `admin.php` | Komplettes Admin-Interface und gesamte Logik |
| `config.php` | Konfiguration (Passwort-Hash, Timeouts, Datenpfad) |
| `qr.js` | Eigenständiger QR-Code-Encoder (Client-seitig) |
| `.htaccess` | URL-Rewriting + Schutz sensibler Dateien |
| `urls.json` | Datenbestand (Gruppen + Links) |
| `clicks.json` | Aufruf-Zähler *(wird automatisch angelegt)* |
| `.ht_attempts.json` | Login-Fehlversuche (Rate-Limiting) *(automatisch)* |
| `.ht_tokens.json` | „Angemeldet bleiben"-Token, nur gehasht *(automatisch)* |
| `.ht_auth.json` | Passwort-Hash (vom Setup gesetzt) *(automatisch)* |

Es wird **keine Datenbank** benötigt — alle Daten liegen in JSON-Dateien.

---

## Installation

1. Den Ordner `goto/` per FTP/SSH in den Web-Root laden
   (Pfad frei wählbar; Beispiele hier nutzen `/goto/`).
2. Sicherstellen, dass der Ordner für den Webserver **beschreibbar** ist
   (für `urls.json`, `clicks.json` usw.):
   ```bash
   chmod 664 urls.json
   chmod 775 .          # falls der Webserver Dateien anlegen muss
   ```
3. Im Browser `deine-domain.de/goto/admin` öffnen → **Setup-Bildschirm**
   erscheint (siehe [Konfiguration](#konfiguration)).
   (`admin.php` leitet automatisch auf die saubere URL `/admin` um.)

**Voraussetzungen:** PHP 8.0+ mit aktiviertem `mod_rewrite` (Apache).
Ohne `mod_rewrite` funktionieren Links in der Form
`…/goto/index.php?slug=kürzel`.

---

## Konfiguration

Alle Einstellungen in `config.php`:

```php
return [
    'password_hash'   => '',      // bcrypt-Hash (siehe Setup) oder leer = Setup
    'idle_timeout'    => 1800,    // Auto-Logout nach Inaktivität (Sekunden)
    'max_attempts'    => 5,       // Login-Fehlversuche bis zur Sperre
    'lockout_seconds' => 900,     // Sperrdauer nach zu vielen Versuchen
    'data_dir'        => __DIR__, // Speicherort der Datendateien
];
```

### Passwort setzen (Ersteinrichtung)

Beim ersten Aufruf von `admin.php` erscheint ein **Setup-Bildschirm**:
einfach Wunschpasswort eingeben → es wird sicher (bcrypt) gespeichert
(in `.ht_auth.json` im Datenverzeichnis) → fertig, direkt anmelden.
Kein Datei-Editieren nötig.

Später im Admin unter **„Passwort ändern"** jederzeit änderbar.

**Optional** lässt sich der Hash stattdessen fest in `config.php` oder per
Umgebungsvariable `GOTO_PASSWORD_HASH` „festnageln" (hat Vorrang und deaktiviert
das Ändern im UI) — praktisch für Container-Deployments:
```bash
php -r "echo password_hash('MEIN_PASSWORT', PASSWORD_DEFAULT), PHP_EOL;"
```

> Voraussetzung fürs automatische Speichern: Das Datenverzeichnis muss
> beschreibbar sein. Andernfalls zeigt das Setup als Fallback die Zeile zum
> manuellen Eintragen in `config.php`.

### Datenverzeichnis verschieben (empfohlen)

Für maximale Sicherheit die Datendateien **außerhalb des Web-Roots** ablegen,
dann sind sie unabhängig von Apache/`.htaccess` nicht abrufbar:

```php
'data_dir' => __DIR__ . '/../goto-data',
```

Den Ordner anlegen, beschreibbar machen und vorhandene `urls.json` dorthin
verschieben. `index.php` und `admin.php` nutzen automatisch denselben Pfad.

---

## Bedienung

Admin-Oberfläche: `deine-domain.de/goto/admin`

- **Link anlegen:** Ziel-URL eingeben; Kürzel optional (leer = zufälliges
  6-Zeichen-Kürzel). Optional Gruppe, Ablaufdatum, Titel/Notiz.
- **QR-Code:** QR-Symbol in der Zeile öffnet den Dialog mit Vorschau,
  Einstellungen und PNG-/SVG-Download.
- **Verschieben:** per Gruppen-Dropdown oder Drag & Drop auf eine Gruppe.
- **Suche:** Feld über der Liste filtert live nach Kürzel, Titel, URL, Gruppe.
- **Mehrfachauswahl:** Checkboxen markieren → verschieben / löschen /
  Zähler zurücksetzen.
- **Export/Import:** unter „Export / Import" (JSON). Beim Import wahlweise
  ersetzen oder zusammenführen; Klick-Zähler werden nicht exportiert.

Öffentlicher Link-Aufbau: `deine-domain.de/goto/kürzel`

---

## Datenmodell

`urls.json`:

```json
{
  "groups": ["Bachelorarbeit"],
  "links": {
    "intro": {
      "url": "https://www.youtube.com/watch?v=…",
      "group": "Bachelorarbeit",
      "title": "Intro-Video",
      "expires": "2026-12-31",
      "created": 1781695468
    }
  }
}
```

`clicks.json` — nur Zähler, sonst nichts:

```json
{ "intro": 42 }
```

> Hinweis: Das alte flache Format `{ "kürzel": "url" }` wird weiterhin gelesen
> und beim ersten Speichern automatisch ins neue Format überführt.

---

## Sicherheit

- **Passwörter** nur als **bcrypt-Hash** (`password_hash`/`password_verify`)
- **CSRF-Schutz** auf allen schreibenden Aktionen (`hash_equals`)
- **Brute-Force-Bremse**: Sperre nach `max_attempts`, IP nur gehasht gespeichert
- **Session-Härtung**: `HttpOnly`, `SameSite=Strict`, `Secure` (bei HTTPS),
  `session_regenerate_id` gegen Session-Fixation, Idle-Timeout
- **„Angemeldet bleiben"**: Selector\:Validator-Token, serverseitig nur als
  Hash gespeichert, bei jeder Nutzung rotiert, beim Logout gelöscht
- **Content-Security-Policy** (Nonce-basiert, kein `unsafe-inline`) plus
  `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, **HSTS**
- **URL-Validierung**: nur `http`/`https` werden gespeichert und weitergeleitet
  (auch in `index.php` erneut geprüft)
- **Dateischutz**: `.htaccess` sperrt `*.json`, `config.php` und Dotfiles;
  `display_errors` ist deaktiviert

Mehr zum optionalen Auslagern der Daten unter
[Datenverzeichnis verschieben](#datenverzeichnis-verschieben-empfohlen).

---

## Lokal testen (Docker)

Im übergeordneten Ordner liegen `Dockerfile`, `docker-compose.yml` und eine
Anleitung (`README-docker.md`):

```bash
docker compose up -d --build
# -> http://localhost:8088/goto/admin
```

Bildet PHP 8.2 + Apache mit `mod_rewrite` ab (wie typisches Shared-Hosting).
Diese Dateien liegen **außerhalb** von `goto/` und gehören **nicht** zum
Produktiv-Upload.

---

## Wartung & Fehlersuche

| Problem | Ursache / Lösung |
|---|---|
| „Konnte nicht speichern" | Schreibrechte auf `urls.json` bzw. `data_dir` prüfen |
| `500`-Fehler nach Upload | evtl. `Options -Indexes` in `.htaccess` nicht erlaubt → Zeile entfernen |
| Weiterleitung 404 statt Ziel | Kürzel existiert nicht oder `mod_rewrite` fehlt |
| Link liefert `410` | Ablaufdatum überschritten — im Admin anpassen/entfernen |
| QR-Code zu „voll" | längere URLs → kürzeres Kürzel oder Fehlerkorrektur senken |
| Favicons fehlen | werden von `icons.duckduckgo.com` geladen; bei Blockade einfach ignorierbar |

Backups: einfach `urls.json` sichern (oder Export-Funktion nutzen).

---

*Erstellt mit Claude Code.*
