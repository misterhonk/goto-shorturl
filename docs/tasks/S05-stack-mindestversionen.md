---
id: S05
titel: Stack – PHP-Mindestversion anheben, EOL-Warnung in der Diagnose, Server-Versionen dokumentieren
prio: P2 (Security/Betrieb)
aufwand: S (1 h)
quelle: docs/analysis/research-security.md SEC-20, SEC-21, Teil B
dateien: [lib.php, admin.php (Diagnose), README.md, Dockerfile, .github/workflows/ci.yml, CHANGELOG.md]
abhängig_von: []
---

# S05 – Stack und Mindestversionen

## Fakten (Stand Nov 2026, Quellen in research-security.md Teil B; vor dem Übernehmen gegen php.net/supported-versions prüfen)
- PHP 8.1 ist seit 31.12.2025 EOL, PHP 8.2 wird am 31.12.2026 EOL. Das README nennt noch 8.0 als Minimum (seit 2023 EOL).
- Der Container läuft mit php:8.2-apache, also in 7 Wochen ebenfalls EOL.
- Apache ≥ 2.4.67 (CVE-2026-23918, HTTP/2), nginx ≥ 1.30.1, Caddy: CVE-2026-27585 (Pfad-Matcher-Bypass) betrifft genau die Sperrregeln. Laut Agent stammen diese
  CVE-Angaben teils aus Sekundärquellen; vor der Veröffentlichung gegen die offiziellen Advisories prüfen.
- jsQR 1.4.0 wird nicht mehr gepflegt, es sind keine CVEs bekannt. Es läuft nur clientseitig auf eigene Bilder, das Risiko ist gering.

## Umsetzung
1. `lib.php`: Konstante `PHP_EOL_DATES = ['8.0'=>'2023-11-26','8.1'=>'2025-12-31','8.2'=>'2026-12-31','8.3'=>'2027-12-31','8.4'=>'2028-12-31']`.
   `shortcut: Tabelle statisch, bei jedem Release prüfen`.
2. Die Diagnose-Ampel zeigt: rot bei EOL, gelb ab 90 Tagen vor EOL, grün sonst, mit Hinweis „Beim Hoster PHP-Version umstellen“.
3. README: Mindestversion **8.2**, empfohlen **8.4**. Den Abschnitt „Sicherheit“ um Server-Mindestversionen und den Hinweis zu case-insensitiven Dateisystemen (S01) ergänzen.
4. `Dockerfile`: `php:8.4-apache`; die CI-Matrix lintet mit 8.2, 8.3 und 8.4.
5. Optional: Ein `composer.json`-loser Hinweis `if (PHP_VERSION_ID < 80200)` in `lib.php` mit einer verständlichen Fehlerseite statt eines Fatal Errors.

## Akzeptanzkriterien
- Die Diagnose im Docker (8.4) ist grün. Simuliert mit 8.2 ist sie nach dem 2026-10-02 gelb.
- Die CI läuft auf allen drei Versionen grün.
