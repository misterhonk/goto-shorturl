---
id: T03
titel: Klickzähler gegen Bots, HEAD-Requests, Prefetch und Link-Scanner absichern
prio: P1 (hoch)
aufwand: S (1 h)
quelle: usertest-findings.md UT-12, research-competitors.md (Must), research-ux.md (Empfehlung 1)
dateien: [index.php, lib.php, tests/lib.test.php, README.md, CHANGELOG.md]
abhängig_von: []
---

# T03 – Bot-Filter für den Klickzähler

## Problem
`index.php:92–96` nimmt nur Social-Preview-Bots aus; `count_click()` (Aufruf `index.php:148`) zählt sonst alles:
HEAD, `curl`, `python-requests`, Googlebot, Outlook SafeLinks/Proofpoint, Browser-Prefetch. Im Test haben
mehrere `curl`-Aufrufe die Tageszahl auf 8 gebracht. Laut Imperva Bad Bot Report 2025 sind rund 51 % des
Web-Traffics automatisiert, und die Zähler sind GOTOs einzige Kennzahl.

## Umsetzung
1. `lib.php`: `function is_nonhuman_request(): bool`
   - `true`, wenn `$_SERVER['REQUEST_METHOD'] === 'HEAD'`
   - `true`, wenn `Sec-Purpose`/`Purpose`/`X-Purpose`/`X-Moz` den Wert `prefetch`/`preview` enthält
   - `true`, wenn der User-Agent leer ist
   - `true`, wenn der UA auf `/bot|crawl|spider|slurp|preview|scan|fetch|monitor|curl|wget|python-|go-http|java\/|okhttp|httpclient|axios|node-fetch|headless|phantom|lighthouse|safelinks|proofpoint|mimecast|barracuda/i` passt
2. In `index.php`: Die Weiterleitung bleibt für alle gleich, **nur** `count_click()` wird übersprungen, wenn `is_nonhuman_request()`.
   Die bestehende `$isPreviewBot`-Logik (OG-Seite) bleibt unverändert.
3. README, Abschnitt „Klick-Zähler“: kurz erklären, was nicht gezählt wird.

## Tests
- `tests/lib.test.php`: Tabelle aus UA/Methode/Header → erwartetes Ergebnis (mind. 10 Fälle inkl. echtem Chrome-, Safari-iOS- und Android-UA = `false`).
- Manuell: `curl -I …/goto/<slug>` und `curl …/goto/<slug>` → Zähler unverändert; Aufruf im Browser → +1.

## Nicht-Ziele
Keine IP-basierte Dublettenerkennung (DSGVO-Versprechen), keine externen Bot-Listen.
