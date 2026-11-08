---
id: T04
titel: Nach Anlegen und Bearbeiten direkt zur betroffenen Zeile springen; QR-Knopf im Toast
prio: P2 (mittel)
aufwand: S (1–2 h)
quelle: usertest-findings.md UT-03, UT-04, UT-13
dateien: [admin.php, assets/app.js, assets/goto.css, lang.php]
abhängig_von: []
---

# T04 – Kurze Wege nach Anlegen und Bearbeiten

## Problem
- Nach „Hinzufügen“ bleibt die Seite oben, der neue Link steht unten in seiner Gruppe (Liste beginnt erst bei ~1200 px am Desktop
  und 1769 px mobil). Screenshot `docs/analysis/screenshots/04-link-angelegt-toast.jpg`.
- Der Stift-Link `admin.php:593` (`?edit=<slug>`) lädt neu und landet bei Scroll-Position 0; die Bearbeiten-Zeile liegt etwa 1500 px tiefer.
- Der Toast (9 s, oben rechts) verdeckt den Theme- und den Abmelden-Knopf (WCAG 2.4.11).

## Umsetzung
1. Jede Tabellenzeile bekommt `id="row-<slug>"` (prüfen, ob schon vorhanden).
2. Stift-Link: `?edit=<slug>#row-<slug>`. In `app.js` beim Laden: wenn `tr.editrow` existiert → `scrollIntoView({block:'center'})`
   und Fokus auf das Ziel-URL-Feld.
3. Nach erfolgreichem `add`/`update`: Redirect auf `admin?hl=<slug>#row-<slug>`; `app.js` hebt die Zeile 2 s hervor
   (CSS-Klasse `.row--flash` mit Hintergrund-Animation, `prefers-reduced-motion` beachten).
4. Toast nach `add` bekommt neben „Kopieren“ einen Knopf „QR“, der den QR-Dialog der Zeile öffnet (vorhandenen Öffner wiederverwenden).
5. „Weitere Optionen“ nach erfolgreichem Anlegen zuklappen (localStorage-Key `goto-addopts` respektieren: nur einmalig schließen).
6. Toast-Container unter die Topbar setzen (`top: calc(var(--topbar-h) + 8px)`) **oder** unten rechts; zusätzlich
   `html{scroll-padding-top: calc(var(--topbar-h) + 16px)}`, damit Sprungziele nicht unter der Sticky-Topbar liegen.

## Akzeptanzkriterien
- Link anlegen → Ansicht steht auf dem neuen Link, Zeile kurz hervorgehoben, Toast verdeckt keine Bedienelemente.
- Stift → Bearbeiten-Zeile mittig, Fokus im Feld; Speichern → zurück zur Zeile.
- Funktioniert mit Tastatur, mobil (375 px) und mit reduzierter Bewegung.
- Keine Inline-Styles (CSP).
