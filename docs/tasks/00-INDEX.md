# GOTO – Arbeitspakete (Stand 2026-11-08, Basis v1.3.3)

Kleine, in sich geschlossene Aufträge für LLM-Sessions. **Ein Task pro Session/Branch/PR.**
Hintergrund steht in `docs/analysis/`:
- `app-overview.md` – Ist-Analyse, Kategorie, Architektur
- `usertest-findings.md` – lokaler User-Test (UT-01…13) mit Screenshots in `screenshots/`
- `research-competitors.md` – Wettbewerb, OSS, Community-Wünsche, Gap-Liste (MoSCoW)
- `research-ux.md` – Studien und Daten zu QR, Vertrauen, Admin-UX, WCAG 2.2, Auth
- `research-security.md` – Security-Audit SEC-01…21 und Stack-CVEs

## So arbeitest du einen Task ab (für jede Session gleich)
1. `CLAUDE.md` lesen (Konventionen: file-only, `t()` + `lang.php` für **jede** Zeichenkette, nonce-CSP ohne Inline-Styles, Helfer nach `lib.php`).
2. Den Task lesen; dann die genannten Fundstellen **gegen den aktuellen Code prüfen**, denn Zeilennummern können verrutscht sein.
3. Branch `feat/<id>-<kurz>` bzw. `fix/<id>-<kurz>`. Nur die Dateien im Front-Matter `dateien` anfassen. Muss mehr geändert werden, das in der PR begründen.
4. Testen: `docker compose up -d` im Elternordner → `http://localhost:8088/goto/admin` (Passwort `test1234`),
   `docker exec goto php -l <datei>` für alle geänderten PHP-Dateien, `docker exec goto php /var/www/html/goto/tests/lib.test.php`,
   `node --check assets/app.js && node tests/qr.test.js`. UI-Änderungen im Browser prüfen (Desktop 1280 px **und** Mobil 375 px, hell **und** dunkel).
5. `CHANGELOG.md` → `[Unreleased]` ergänzen. Commit-Nachricht auf Deutsch ohne Umlaute, mit einem Absatz „Geprueft:“.
6. Nicht mergen oder releasen ohne Martins Freigabe (PR mit Rebase-Merge).

## Reihenfolge-Vorschlag

### Welle 1 – Fehler und Sicherheit (klein, hoher Nutzen)
| ID | Titel | Prio | Aufwand |
|---|---|---|---|
| [T01](T01-slug-case-insensitive.md) | Kürzel case-insensitive auflösen | P1 | XS |
| [S01](S01-server-configs-haerten.md) | Server-Configs: Groß-/Kleinschreibung, Punkt-Ordner, Header | P1 | S |
| [S02](S02-ssrf-guard-vervollstaendigen.md) | SSRF: Linkcheck-Redirects, AAAA, Rebinding | P1 | S–M |
| [T03](T03-bot-filter-klickzaehler.md) | Bot/HEAD/Prefetch nicht zählen | P1 | S |
| [T02](T02-url-normalisierung.md) | URL-Normalisierung (Schema, IDN, Umlaute), Eingaben behalten | P1 | S |
| [T05](T05-kuerzel-umbenennen-schutz.md) | Umbenennen bricht keine gedruckten Codes (Alias) | P1 | M |
| [S03](S03-bruteforce-auth-haerten.md) | Brute-Force: IPv6-/64, globale Bremse, TOTP-Replay | P1 | M |

### Welle 2 – Bedienung und Print-Workflow
| ID | Titel | Prio | Aufwand |
|---|---|---|---|
| [T04](T04-navigation-nach-anlegen-bearbeiten.md) | Nach Anlegen/Bearbeiten zur Zeile springen, Toast-Position | P2 | S |
| [T06](T06-qr-druckgroesse-scanbarkeit.md) | QR: Druckgröße mm/DPI, Kontrast, Scan-Selbsttest | P2 | M |
| [T07](T07-besucherseiten-vertrauen.md) | Vorschau ohne 3-s-Zwang, volle Ziel-URL, 404-Hilfe | P2 | S |
| [T09](T09-a11y-login-quickfixes.md) | A11y + Login (Passwortmanager, Zielgrößen, Labels) | P2 | S |
| [T10](T10-mobile-kompakt.md) | Mobile kompakt | P2 | M |
| [S05](S05-stack-mindestversionen.md) | PHP ≥ 8.2, EOL-Warnung in der Diagnose | P2 | S |
| [S04](S04-haertung-kleinkram.md) | Härtung Kleinkram (Cookies, Import, HSTS, Host, Token) | P2 | M |
| [T08](T08-undo-statt-confirm.md) | Undo statt confirm beim Löschen | P3 | S |

### Welle 3 – Features (Lücken gegenüber dem Wettbewerb)
| ID | Titel | Prio | Aufwand |
|---|---|---|---|
| [T11](T11-yourls-import.md) | YOURLS-Import | P2 | M |
| [T12](T12-geraete-routing.md) | Geräte-Routing iOS/Android | P3 | M |
| [T13](T13-utm-builder-og-felder.md) | UTM-Builder + OG-Beschreibung je Link | P3 | S–M |
| [T14](T14-pwa-share-target.md) | PWA-Share-Target + Bookmarklet | P3 | S–M |

Alles Weitere: [BACKLOG.md](BACKLOG.md).

## Parallelisierbarkeit
Konflikte entstehen vor allem in `admin.php`, `index.php`, `lib.php` und `assets/app.js`. Gut parallel laufen:
- T01 + S01 + S05: verschiedene Dateien bzw. Stellen.
- T03 + S02: beide `lib.php`, aber verschiedene Funktionen. Danach rebasen.

Nacheinander: T02 → T11/T12/S04d (nutzen `normalize_url`), T04 → T08/T10 (Toast/Navigation), S03 → S04e (`trusted_proxies`).
