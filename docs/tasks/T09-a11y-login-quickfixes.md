---
id: T09
titel: Barrierefreiheit und Login – Passwortmanager zulassen, Zielgrößen, Labels, Kontraste
prio: P2 (mittel)
aufwand: S (1–2 h)
quelle: research-ux.md Thema 5 und 6 (WCAG 2.2: 2.4.11, 2.5.8, 3.3.8); Memory goto-revision-2026-10 (A1–A5)
dateien: [admin.php, assets/goto.css, assets/app.js, lang.php]
abhängig_von: []
---

# T09 – A11y- und Login-Quick-Fixes

## Befunde
| # | Befund | Fundstelle | Fix |
|---|---|---|---|
| a | Login-Formular `autocomplete="off"`, Passwortfeld ohne `autocomplete="current-password"`. Passwortmanager werden behindert (WCAG 3.3.8) | `admin.php:1323–1325` | `autocomplete="off"` vom `<form>` entfernen, Feld `autocomplete="current-password"`; zusätzlich ein verstecktes `<input type="text" name="username" autocomplete="username" value="admin" hidden>`, damit Passwortmanager einen Eintrag speichern können |
| b | Passwort-Ändern-Formular: neue Felder ohne `autocomplete="new-password"` | Toolbox „Passwort ändern“ | ergänzen |
| c | Zeilen-Checkbox am Desktop 20 px, WCAG 2.5.8 verlangt ≥ 24 px Zielgröße | `goto.css` `input.rowchk` | 24 px bzw. Padding-Trefferfläche |
| d | Sticky-Topbar verdeckt Fokus-/Sprungziele (2.4.11) | `goto.css` | `html{scroll-padding-top:…}` (falls nicht schon in T04 erledigt) |
| e | Ohne Label: Suchfeld, QR-Logo-Datei, Import-Datei, eine Textarea | im Browser per Script gefunden | `<label class="sr-only">` oder `aria-label` |
| f | „Angemeldet bleiben“ ohne Dauer | Login | „Angemeldet bleiben (30 Tage)“ – Wert aus der Config einsetzen |
| g | Offen aus der Revision: Fokusring-Kontrast < 3:1, Eingabe-Rahmen `--line-strong` 1,45:1, Tabellen ohne `<th>`, Gruppen-Collapse nicht per Tastatur, 14/30/90-Knöpfe ohne `aria-pressed`, Chart ohne Textalternative | siehe Memory-Liste | einzeln vor Umsetzung gegen aktuellen Code prüfen |

## Akzeptanzkriterien
- Chrome/Safari bieten nach dem Login „Passwort speichern“ an; der Login per Passwortmanager-Autofill funktioniert.
- axe-core (per CDP oder Browser-Extension) meldet für `/admin` keine „serious/critical“-Befunde in diesen Kategorien.
- Kontraste per Script gemessen: Fokusring ≥ 3:1, Eingaberahmen ≥ 3:1 (WCAG 1.4.11).
