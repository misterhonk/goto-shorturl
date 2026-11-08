# GOTO – Ist-Analyse (Stand v1.3.3, 2026-11-08)

## Kategorie
**Selbstgehostetes Link-Management-Tool für eine Person bzw. ein kleines Team**: URL-Shortener mit
dynamischen QR-Codes. Gehört zur Klasse von YOURLS, Shlink, Kutt und Chhoto URL, kommerziell zu Bitly
oder QR-Code-Generator Pro („dynamic QR“). Was GOTO von allen abhebt:
- **Keine Datenbank, kein Build und kein Daemon**: PHP-Dateien per FTP hochladen, Daten liegen in JSON-Dateien.
- **Fokus auf gedruckte QR-Codes**: Design, Logo, Etikettenbogen, Reverse-Scan, „Aktiv ab“, Ersatz-URL nach Ablauf, Rotation.
- **Datenschutz als Feature**: nur Tageszähler je Link und Quelle, keine IP-Adressen, keine User-Agents.

## Architektur in Kürze
| Datei | Rolle | Größe |
|---|---|---|
| `index.php` | öffentliche Weiterleitung (Hot Path): Ablauf, Passwort, Vorschau, OG-Seite für Bots, Query-Durchreichung, Klickzähler | 352 Z. |
| `admin.php` | Admin-UI und alle POST-Aktionen (25 Aktionen, CSRF-geschützt), Login/TOTP/Remember-Me, HTML-Rendering | 2068 Z. |
| `api.php` | REST-API (GET/POST/PATCH/DELETE) mit Bearer-Token, Read-only-Scope | 306 Z. |
| `lib.php` | Bootstrap, Datenmodell, Validierung, atomare JSON-I/O mit Locks, Klick-, Papierkorb- und Backup-Helfer | 402 Z. |
| `lang.php` | EN-Wörterbuch (der deutsche Text ist der Schlüssel) | 444 Z. |
| `assets/app.js` | Admin-Interaktion: Dialoge, QR-Rendering, ZIP-Writer, Charts, Scan | 763 Z. |
| `assets/qr.js` / `jsqr.js` | QR-Encoder (eigener Code) / QR-Decoder (jsQR 1.4.0, vendored) | |

Datenmodell (`urls.json`):
`{groups:[], links:{slug:{url, group, title, expires, starts, created, pass, preview, expires_url, alts:[{url,weight}]}}}`.
Klicks liegen in `clicks.json` im Format `{slug:{t, d:{YYYY-MM-DD:n}, s:{quelle:n}}}`.

## Funktionsumfang (Admin)
Links anlegen (eigenes oder zufälliges Kürzel), Gruppen (mit Drag & Drop), Titel, Ablauf und Aktiv-ab,
Passwort, Vorschau-Seite, Rotation, Ersatz-URL; Suche, Sortierung, Filter, Bulk-Aktionen; QR-Dialog (ECC, Größe,
Rand, Farben, Logo, Voreinstellungen, Quelle), ZIP-Export aller QR-Codes, Etikettenbogen, Reverse-QR-Scan; Statistik-Kacheln,
Verlaufs-Chart, CSV-Export; Import/Export (JSON, CSV, Voll-Backup); Papierkorb; Passwort ändern, TOTP-2FA mit
Recovery-Codes, Geräteverwaltung, API-Tokens, Diagnose (Ampel und Toter-Link-Check), Audit-Log; DE/EN; Hell/Dunkel.

## Grundsätze des Projekts (aus CLAUDE.md/Memory, nicht verhandelbar)
- Datei-basiert, **kein SQLite**, keine selbstzerstörenden Max-Klick-Links.
- Zur Laufzeit wird **kein externer Dienst** angesprochen (Ausnahmen nur als Opt-in).
- Strenge nonce-CSP, keine Inline-Styles, alle Texte über `t()` und `lang.php`.
- Gemeinsame Helfer gehören nach `lib.php`.

## Größte Probleme aus Nutzersicht (Kurzfassung, Details in `usertest-findings.md`)
1. Eingaben scheitern unnötig: URL ohne `https://`, Umlaut-Domains, Kürzel in Großbuchstaben.
2. Ein umbenanntes Kürzel macht gedruckte Codes stillschweigend kaputt.
3. Der Druck-Workflow spricht Pixel statt Millimeter und prüft weder Kontrast noch Scanbarkeit.
4. Wege sind zu lang: Nach Anlegen oder Bearbeiten sucht man die Zeile, mobil kommt die Liste erst nach mehr als 2 Bildschirmhöhen.
5. Die Statistik zählt Bots, HEAD-Requests und Scanner mit; dabei ist sie die einzige Kennzahl.
