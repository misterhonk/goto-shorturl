---
id: T11
titel: Import aus YOURLS (und generischem CSV anderer Shortener)
prio: P2 (Feature, Wechselhürde senken)
aufwand: M (3–4 h)
quelle: research-competitors.md 5.2 Must (Shlink #835/#1242)
dateien: [admin.php (Aktion import), lib.php, lang.php, README.md, tests/lib.test.php]
abhängig_von: [T02 (normalize_url)]
---

# T11 – YOURLS-Import

## Ziel
Wer YOURLS auf demselben Shared Hosting betreibt, soll ohne Datenverlust zu GOTO wechseln können.

## Eingabeformate
1. **YOURLS-SQL-Dump** der Tabelle `yourls_url`: Zeilen `INSERT INTO `yourls_url` VALUES ('keyword','url','title','timestamp','ip',clicks)`.
   Mit einem Regex parsen, **kein** SQL ausführen. Die Spalte `ip` wird verworfen (DSGVO).
2. **YOURLS-CSV** (z. B. aus phpMyAdmin oder dem Plugin „Export“): Kopfzeile erkennen (`keyword,url,title,timestamp,clicks`).
3. Den vorhandenen GOTO-CSV-Import beibehalten.

## Umsetzung
- `lib.php`: `parse_yourls_dump(string $sql): array` → `[slug => [url,title,created,clicks]]`.
  Kürzel durch `clean_slug`; YOURLS erlaubt Großbuchstaben, daher Kollisionen nach dem Kleinschreiben melden.
- Import-Dialog: Formatauswahl „Automatisch erkennen“ (Inhalt beginnt mit `INSERT`/`--`/`CREATE` → YOURLS-SQL).
- Klicks: Die Gesamtzahl landet als `t` in `clicks.json`, ohne Tageswerte (`d` leer) – im UI als „importiert“ kennzeichnen.
- Zusammenführen: Bestehende Kürzel werden **nicht** überschrieben; am Ende steht ein Bericht „X importiert, Y übersprungen (Liste)“
  (behebt nebenbei „CSV-Import verwirft ungültige Zeilen still“ aus der Revision).
- Größenlimit: bestehende Upload-Grenze respektieren; Zeilen > 10 000 → Fehlermeldung statt Timeout.

## Tests
- Fixture `tests/fixtures/yourls.sql` mit 5 Einträgen (inkl. Umlaut-URL, Großbuchstaben-Kürzel, Duplikat, `javascript:`-URL) → Erwartung im Test.

## Akzeptanzkriterien
- Import einer YOURLS-Datei legt die Links an, und die Klicksumme stimmt.
- Ungültige Zeilen erscheinen im Bericht, statt still zu verschwinden.
