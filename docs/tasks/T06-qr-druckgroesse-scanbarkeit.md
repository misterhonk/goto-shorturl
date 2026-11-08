---
id: T06
titel: QR-Dialog druckgerecht – Größe in mm/DPI, Kontrast-Warnung, Scan-Selbsttest
prio: P2 (mittel, Kern-Use-Case Print)
aufwand: M (3–4 h)
quelle: usertest-findings.md UT-05, UT-06; research-ux.md Thema 1 (ISO/IEC 18004, Quiet Zone)
dateien: [admin.php (QR-Dialog-Markup), assets/app.js, assets/goto.css, lang.php]
abhängig_von: []
---

# T06 – Druckgerechter QR-Dialog

## Problem
- Der Dialog fragt „Modulgröße (px)“ und „Rand (Module)“ ab. Wer druckt, denkt in mm, cm und DPI. Screenshot `docs/analysis/screenshots/07-qr-dialog.jpg`.
- Es gibt keine Prüfung von Kontrast, invertierten Farben oder zu kleinem Rand (in `app.js` kein Treffer für contrast/luminance).
- Es gibt keinen Hinweis zur Mindestgröße. Faustregel: Kantenlänge ≥ Scanabstand ÷ 10, mindestens ca. 2 cm.

## Umsetzung (alles client-seitig, CSP-konform)
1. **Druckgröße:** Neue Felder „Breite (mm)“ (leer = aus) und „DPI“ (Standard 300). Ist eine Breite gesetzt:
   `modulPx = round(mm / 25.4 * dpi / (anzahlModule + 2*rand))`; das Feld Modulgröße wird dann berechnet und gesperrt.
   Darunter eine Zeile: „≈ 25 × 25 mm bei 300 dpi – gut lesbar bis ca. 25 cm Abstand“.
2. **SVG-Export:** Bei gesetzter Breite `width="<mm>mm" height="<mm>mm"` ins `<svg>`-Root schreiben (viewBox bleibt), dann übernehmen
   Layout-Programme die Größe direkt.
3. **Kontrast-Check:** relative Luminanz nach WCAG für Vorder- und Hintergrund berechnen.
   - Vordergrund heller als Hintergrund → Warnung „Invertierte Farben – viele Scanner-Apps lesen das nicht“.
   - Verhältnis < 4:1 → Warnung „Kontrast zu gering“.
   - Rand < 4 Module → Hinweis „Ruhezone unter 4 Modulen (ISO/IEC 18004)“.
   Warnungen erscheinen als `role="status"`-Zeile, die den Download nicht blockiert.
4. **Selbsttest:** Nach jedem Rendern das Canvas durch `jsQR` (`assets/jsqr.js`, lazy wie beim Scan) dekodieren; Ergebnis ✓ „scanbar“
   oder ⚠ „nicht lesbar – Logo kleiner, ECC höher oder Kontrast erhöhen“. Das dekodierte Ergebnis muss der Kurz-URL entsprechen.
5. Die neuen Felder in die QR-Voreinstellungen (`goto-qr-defaults`) aufnehmen.
6. Optional: Die Kurz-URL als Klartextzeile unter dem QR mit exportieren (Checkbox „URL darunter drucken“). Begründung siehe research-ux.md:
   Eine sichtbare eigene Domain schafft Vertrauen gegen „Quishing“.

## Akzeptanzkriterien
- 30 mm bei 300 dpi → PNG ≈ 354 px Kantenlänge (±1 Modul); SVG hat `width="30mm"`.
- Weiß auf Schwarz → Warnung „invertiert“; #777 auf #888 → Warnung „Kontrast“.
- Großes Logo (falls möglich) → Selbsttest zeigt ⚠.
- `node tests/qr.test.js` bleibt grün.
