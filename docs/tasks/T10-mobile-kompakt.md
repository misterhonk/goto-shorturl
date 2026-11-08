---
id: T10
titel: Mobile Admin-Ansicht kompakter – Liste früher sichtbar, Karten flacher
prio: P2 (mittel)
aufwand: M (2–4 h)
quelle: usertest-findings.md UT-11; Memory goto-revision-2026-10 (UX mobil)
dateien: [assets/goto.css, admin.php, assets/app.js]
abhängig_von: [T04]
---

# T10 – Mobile kompakt

## Messwerte (375 × 812, 4 Links)
- Die Liste beginnt bei **1769 px** (über 2 Bildschirmhöhen); die Seite ist 3747 px hoch.
- Eine Link-Karte ist **268 px** hoch, d. h. etwa 3 Links pro Bildschirm.
- Screenshots: `docs/analysis/screenshots/12-mobil-start.jpg`, `13-mobil-liste.jpg`.

## Umsetzung (nur `@media (max-width: 640px)`, sofern nicht anders gesagt)
1. **Stat-Kacheln**: eine horizontal scrollbare Zeile (`overflow-x:auto; scroll-snap-type:x`), Kachelhöhe ca. 64 px.
2. **Anlege-Panel**: „Weitere Optionen“ mobil immer geschlossen starten (localStorage-Zustand nur am Desktop beachten). URL-Feld und Knopf in eine Zeile, der Knopf nur als Icon mit `aria-label`.
3. **Gruppen-Kopf**: „Umbenennen/Löschen“ in ein `<details>`-„…“-Menü statt dauerhaft sichtbar.
4. **Link-Karte**:
   - Zeile 1: Checkbox, Kürzel, Klickzahl.
   - Zeile 2: Titel oder Ziel-URL einzeilig mit Ellipsis.
   - Zeile 3: Aktionen (QR, Kopieren, Bearbeiten, Löschen) in einer Reihe.
   - Der Gruppen-Select wandert in die Bearbeiten-Ansicht; Verschieben bleibt über Bulk-Aktionen möglich.
   - Ziel: ≤ 120 px je Karte.
5. Die Suche bleibt sticky unter der Topbar (optional), damit man bei langer Liste nicht zurückscrollen muss.

## Akzeptanzkriterien
- Bei 375 px beginnt die Liste spätestens bei ~700 px, und eine Karte ist ≤ 130 px hoch.
- Alle Aktionen haben eine Trefferfläche ≥ 24 px (besser 44 px), kein horizontales Scrollen der Seite.
- Desktop-Layout unverändert (Screenshot-Vergleich 1280 px vorher/nachher).
