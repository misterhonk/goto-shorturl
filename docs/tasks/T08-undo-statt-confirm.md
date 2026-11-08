---
id: T08
titel: Löschen in den Papierkorb ohne confirm(), dafür „Rückgängig“ im Toast
prio: P3 (mittel-niedrig)
aufwand: S (1–2 h)
quelle: research-ux.md Empfehlung 5 (NN/g: Undo statt Bestätigungsdialog)
dateien: [admin.php, assets/app.js, lang.php]
abhängig_von: [T04 (Toast-Position)]
---

# T08 – Undo statt Bestätigungsdialog

## Problem
Einzel- und Bulk-Löschen (`admin.php:594`, `admin.php:1532`) fragen per `confirm()` nach, obwohl beides nur in den Papierkorb verschiebt und
damit umkehrbar ist. Das bringt Dialog-Müdigkeit, und echte Gefahren gehen unter.

## Umsetzung
1. `data-confirm` bei **diesen beiden** Formularen entfernen. Endgültige Aktionen (`trash_delete`, `trash_empty`, Import ersetzen, Codes neu erzeugen)
   behalten ihren Dialog.
2. Nach `delete`/`bulk op=delete` enthält der Flash-Toast einen Knopf „Rückgängig“. Er postet die vorhandene Aktion `restore`
   (für Bulk: mehrere Slugs; `restore` ggf. auf `slugs[]` erweitern), mit CSRF-Token.
3. Toast-Laufzeit bei Undo: 10 s, und bei Fokus oder Hover pausieren.

## Akzeptanzkriterien
- Löschen → kein Dialog, Toast mit „Rückgängig“ → Klick stellt den Link mit Klicks und Gruppe wieder her.
- Bulk-Löschen von 3 Links → ein Undo stellt alle 3 wieder her.
- Papierkorb leeren fragt weiterhin nach.
