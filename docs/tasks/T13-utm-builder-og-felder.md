---
id: T13
titel: UTM-Builder im QR-Dialog und editierbare OG-Beschreibung je Link
prio: P3 (Feature)
aufwand: S–M (2–3 h)
quelle: research-competitors.md 5.2 Should
dateien: [admin.php, assets/app.js, index.php (OG-Seite), lib.php, api.php, lang.php]
abhängig_von: []
---

# T13 – UTM-Builder und OG-Felder

## A) UTM-Builder (rein clientseitig)
- Im QR-Dialog neben „Quelle (für Statistik)“ ein aufklappbares „Kampagnen-Parameter“ mit `utm_source`, `utm_medium` und `utm_campaign`.
- Die QR-URL wird `…/<slug>?gq=<quelle>&utm_source=…`. Die Durchreichung existiert schon (`merge_query`), `gq` wird intern gezählt.
- „Quelle“ füllt `utm_source` vor, wenn dieses leer ist. Die Werte landen in den QR-Voreinstellungen.
- Achtung: Ein längerer Inhalt ergibt einen dichteren QR-Code. Die Modulzahl live anzeigen (passt zu T06).

## B) OG-Beschreibung je Link
- Optionales Feld `og_desc` (max. 200 Zeichen, `clean_text`) im Modell.
- `index.php`, OG-Seite: `og:description` = `og_desc`, falls gesetzt, sonst wie bisher „Weiterleitung zu <domain>“.
- Hinweis im UI: „Titel und Beschreibung sind beim Teilen öffentlich sichtbar.“
- Ein eigenes OG-Bild pro Link kommt erst später (Upload-Handling und Speicherplatz, siehe Backlog).

## Akzeptanzkriterien
- QR mit Quelle `flyer` und Medium `print` → gescannte URL leitet auf das Ziel mit `utm_source=flyer&utm_medium=print`, Zähler `s.flyer` steigt.
- `curl -A WhatsApp/2 …` zeigt die gesetzte Beschreibung, escaped.
