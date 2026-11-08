---
id: T02
titel: Ziel-URLs tolerant normalisieren (Schema ergänzen, IDN, Umlaute) und Eingaben bei Fehler behalten
prio: P1 (hoch)
aufwand: S (1–2 h)
quelle: usertest-findings.md UT-01, UT-02
dateien: [lib.php, admin.php, api.php, assets/app.js, lang.php, tests/lib.test.php]
abhängig_von: []
---

# T02 – URL-Normalisierung

## Problem
- `de.wikipedia.org/wiki/X` (ohne Schema) wird abgelehnt: `admin.php:973`, `valid_url()` in `lib.php:141`.
- `FILTER_VALIDATE_URL` lehnt `https://müller.de/` und `https://de.wikipedia.org/wiki/Äpfel` ab (verifiziert).
- Nach dem Fehler-Redirect ist das Formular leer und die Eingabe weg.

## Umsetzung
1. **`lib.php`: neuer Helfer `normalize_url(string $u): string`**
   - `trim`; wenn kein `scheme://` vorhanden und der Text mit einem Host-artigen Muster beginnt
     (`/^[\p{L}0-9.-]+\.[\p{L}]{2,}(\/|$|\?|#|:)/u`) → `https://` voranstellen.
   - Host per `idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46)` umwandeln, wenn `function_exists('idn_to_ascii')`.
   - Nicht-ASCII-Bytes in Pfad, Query und Fragment per `preg_replace_callback('/[^\x21-\x7E]/', fn($m)=>rawurlencode($m[0]), …)` kodieren
     (Leerzeichen → `%20`).
   - Rückgabe des normalisierten Strings; `valid_url()` bleibt die Prüfung **danach** (nur http/https).
2. Alle Stellen, die eine Ziel-URL annehmen, zuerst durch `normalize_url()` schicken: `add`/`update` in `admin.php`
   (Hauptziel, `expires_url`, Rotationsziele via `parse_alts_text`), Import, `api.php` (POST/PATCH), Titel-Fetch.
   Grep: `grep -n "valid_url(" *.php`.
3. **Eingaben behalten:** Bei Fehler in `add` die POST-Werte (ohne Passwort) in `$_SESSION['addform']` ablegen und beim Rendern
   des Anlegeformulars vorbelegen, danach aus der Session löschen.
4. **Client (`assets/app.js`)**: Beim `blur` des Ziel-URL-Felds fehlendes Schema ergänzen (gleiche Regel), damit der Nutzer es sieht.
5. Diagnose-Ampel: Warnung „PHP-Erweiterung intl fehlt – Umlaut-Domains werden nicht unterstützt“, wenn `idn_to_ascii` fehlt.
6. Neue Strings in `lang.php` (DE/EN).

## Tests (`tests/lib.test.php`)
| Eingabe | Erwartet |
|---|---|
| `de.wikipedia.org/wiki/QR-Code` | `https://de.wikipedia.org/wiki/QR-Code` |
| `https://müller.de/` | `https://xn--mller-kva.de/` |
| `https://de.wikipedia.org/wiki/Äpfel` | `https://de.wikipedia.org/wiki/%C3%84pfel` |
| `javascript:alert(1)` | bleibt ungültig für `valid_url` |
| `mailto:a@b.de` | bleibt ungültig |
| `ftp://x.de` | bleibt ungültig |

## Akzeptanzkriterien
- Alle Tests grün, `docker exec goto php -l` für alle PHP-Dateien sauber.
- Im Browser: `de.wikipedia.org` eingeben → Link wird angelegt, Ziel zeigt `https://…`.
- Fehlerfall (z. B. `xyz`) → Fehlermeldung, Felder bleiben befüllt.

## Achtung
Die Normalisierung darf keine neuen Schemata durchlassen (Open-Redirect/XSS-Schutz bleibt in `valid_url`).
