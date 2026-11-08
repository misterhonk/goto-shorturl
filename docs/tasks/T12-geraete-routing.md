---
id: T12
titel: Ziel nach Gerät/Betriebssystem (iOS / Android / Rest)
prio: P3 (Feature)
aufwand: M (2–3 h)
quelle: research-competitors.md 5.2 Should (Dub #27, Sink #184)
dateien: [lib.php, index.php, admin.php, api.php, lang.php, README.md, tests/lib.test.php]
abhängig_von: [T02]
---

# T12 – Geräte-Routing

## Ziel
Ein gedruckter QR für eine App: iPhone → App Store, Android → Play Store, alle anderen → Webseite.

## Umsetzung
- Datenmodell: optional `url_ios`, `url_android` (leer = aus), validiert wie `url` (in `normalize_data`).
- `lib.php`: `device_target(array $link, string $ua): string`. Auf iPhone/iPad (`/iPhone|iPad|iPod/`; iPadOS meldet sich
  als Mac, deshalb zusätzlich `Macintosh` + `Mobile/` prüfen) gilt `url_ios`, bei `/Android/` gilt `url_android`, sonst `url`.
- Reihenfolge in `index.php`: Ablauf/Aktiv-ab → Passwort → **Gerät** → Rotation (`alts` nur für das Standardziel) → Query-Durchreichung.
- Admin: zwei Felder unter „Weitere Optionen“ und in der Bearbeiten-Zeile; die API nimmt `url_ios`/`url_android` an.
- Datenschutz: Der User-Agent wird nur zur Laufzeit gelesen und nicht gespeichert.

## Tests
- UA-Tabelle in `lib.test.php` (iPhone Safari, iPadOS-Desktop-UA, Android Chrome, Desktop Chrome, leer).

## Akzeptanzkriterien
- `curl -A "<iPhone-UA>"` → `Location` = `url_ios`; ohne gesetztes `url_ios` → `url`.
