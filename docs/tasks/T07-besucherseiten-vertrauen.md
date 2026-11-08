---
id: T07
titel: Besucherseiten – Vorschau ohne 3-s-Zwang mit voller Ziel-URL, hilfreiche 404, Passwortfeld mit Label
prio: P2 (mittel)
aufwand: S (1–2 h)
quelle: usertest-findings.md UT-09, UT-10; research-ux.md Empfehlung 3 (WCAG 2.2.1 / F41, USEC 2024)
dateien: [index.php, config.php, lang.php, README.md]
abhängig_von: []
---

# T07 – Vertrauen und Hilfe auf den Besucherseiten

## Problem
- Die Vorschau-Seite leitet per `<meta http-equiv="refresh" content="3;…">` nach 3 s weiter (`index.php:302`). Das ist zu kurz zum Prüfen und
  verletzt WCAG 2.2.1 (Failure F41). Angezeigt wird nur die Domain. Screenshot `10-besucher-vorschau.jpg`.
- Die 404-Seite ist eine Sackgasse ohne Hinweis. Screenshot `11-besucher-404.jpg`.

## Umsetzung
1. **Vorschau:** Meta-Refresh entfernen. Stattdessen
   - Domain fett, darunter die vollständige Ziel-URL (`e()`-escaped, `word-break: break-all`, auf 160 Zeichen gekürzt mit „…“),
   - Primärknopf „Weiter zu <domain>“,
   - optional (Konfig `preview_autoredirect` in Sekunden, Standard `0` = aus): Countdown per nonce-Script mit Knopf „Anhalten“.
   Der Klick wird wie bisher beim Aufruf der Vorschau gezählt.
2. **404/410:** Satz „Bitte Schreibweise prüfen.“ ergänzen; neue Konfig `fallback_url` (Standard leer). Ist sie gesetzt, gibt es einen Knopf
   „Zur Startseite“. Der Status bleibt 404/410.
3. Die Passwort-Seite hat bereits `aria-label`. Zusätzlich ein sichtbares `<label>` setzen (WCAG 3.3.2) und `autocomplete="current-password"` nicht setzen
   (Link-Passwörter gehören nicht in den Passwortmanager), dafür `autocomplete="off"`.
4. Neue Strings in `lang.php`, die Konfig-Werte in README und `config.php` dokumentieren.

## Akzeptanzkriterien
- Vorschau bleibt stehen, bis geklickt wird (Standard). Mit `preview_autoredirect=5` erscheinen Countdown und „Anhalten“.
- Ziel-URL mit `<script>` im Pfad wird escaped angezeigt.
- 404 mit `fallback_url` zeigt den Knopf, Status weiterhin 404.
- Die CSP der Besucherseiten bleibt nonce-basiert, kein Inline-Style.
