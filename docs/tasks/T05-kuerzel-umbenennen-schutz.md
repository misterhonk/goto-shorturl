---
id: T05
titel: Umbenennen eines Kürzels darf gedruckte Codes nicht brechen (Warnung und Alias)
prio: P1 (hoch)
aufwand: M (2–4 h)
quelle: usertest-findings.md UT-07; research-ux.md („gedruckt“-Markierung)
dateien: [lib.php, admin.php, api.php, index.php, assets/app.js, lang.php, tests/lib.test.php, README.md]
abhängig_von: []
---

# T05 – Schutz beim Umbenennen von Kürzeln

## Problem
In der Bearbeiten-Zeile ist `newslug` ein ganz normales Feld (`admin.php:516`). Beim Speichern (`admin.php:991–1017`) verschwindet
das alte Kürzel, die Klicks wandern mit, und der alte Kurzlink liefert danach 404. Gedruckte QR-Codes sind damit tot, ohne jede Warnung.

## Umsetzung
1. **Datenmodell** (single source in `lib.php::normalize_data`): neues optionales Feld `aliases: string[]` je Link.
2. **Auflösung** in `index.php`: Ist das Kürzel nicht in `links`, wird über alle `aliases` gesucht. Die Alias-Map ist klein,
   eine lineare Suche reicht.
   `shortcut: lineare Suche, bei >5000 Links eine Map in normalize_data cachen`.
   Treffer leiten wie das Hauptkürzel weiter und zählen auf das Hauptkürzel.
3. **Umbenennen** in `admin.php`: Beim Wechsel des Kürzels ist die Checkbox „Alten Kurzlink weiterhin weiterleiten (empfohlen)“
   standardmäßig angehakt, das alte Kürzel landet dann in `aliases`. Kollisionsprüfung beim Anlegen und Umbenennen auch gegen alle Aliase.
4. **Warnung im UI** (`app.js`): Sobald `newslug` vom Original abweicht, erscheint ein Inline-Hinweis:
   „Gedruckte QR-Codes und geteilte Links zeigen auf das alte Kürzel.“
5. Anzeige in der Liste: kleine Zeile „auch: alt1, alt2“ mit Entfernen-Knopf pro Alias (CSRF-geschützte Aktion `alias_remove`).
6. **API**: `aliases` in GET ausgeben, PATCH mit `newslug` legt den Alias standardmäßig an (`keep_alias=false` schaltet das ab).
7. Löschen eines Links nimmt die Aliase mit in den Papierkorb, Wiederherstellen stellt sie wieder her.

## Tests
- `lib.test.php`: normalize_data behält/säubert `aliases` (nur gültige Slugs, keine Duplikate, nicht gleich dem eigenen Kürzel).
- Manuell: `a` → `b` umbenennen, Häkchen gesetzt → `/goto/a` 302, Klick bei `b`; Häkchen aus → `/goto/a` 404.
- Neues Kürzel `a` anlegen, während `a` ein Alias ist → Fehlermeldung „bereits vergeben“.

## Nicht-Ziele
Kein automatisches „gedruckt“-Flag (siehe Backlog B05).
