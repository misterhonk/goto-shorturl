---
id: T01
titel: Kürzel beim Aufruf unabhängig von Groß-/Kleinschreibung auflösen
prio: P1 (hoch)
aufwand: XS (< 30 min)
quelle: usertest-findings.md UT-08
dateien: [index.php, tests/lib.test.php, CHANGELOG.md]
abhängig_von: []
---

# T01 – Kürzel case-insensitive auflösen

## Problem
Kürzel werden beim Anlegen durch `clean_slug()` (`lib.php:147`) kleingeschrieben, beim Aufruf in `index.php:26–42`
aber unverändert gesucht. `/goto/Z9GEZE` liefert 404, `/goto/z9geze` 302. Abgetippte Kurzlinks von Plakaten
(„goto/Sommerfest“) scheitern.

## Umsetzung
1. In `index.php` direkt nach dem Ermitteln von `$slug` (nach Zeile 31) einfügen: `$slug = strtolower($slug);`
   – **nicht** `clean_slug()`, damit ungültige Zeichen weiterhin zu 404 führen statt still auf ein anderes Kürzel zu zeigen.
2. Prüfen, dass `count_click`, Passwort-Lockout-Schlüssel und OG-URL denselben (kleingeschriebenen) `$slug` nutzen.
3. CHANGELOG `[Unreleased]` → „Behoben: Kurzlinks funktionieren auch in Großschreibung“.

## Akzeptanzkriterien
- `curl -s -o /dev/null -w '%{http_code}' http://localhost:8088/goto/Z9GEZE` → `302` (für einen existierenden Link).
- Unbekanntes Kürzel in Großschreibung → `404`.
- Klick wird unter dem kleingeschriebenen Kürzel gezählt.

## Nicht-Ziele
Kein Fuzzy-Matching, keine Tippfehler-Vorschläge (würde Kürzel enumerierbar machen).
