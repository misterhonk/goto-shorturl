---
id: S01
titel: Server-Configs härten – Groß-/Kleinschreibung, Punkt-Ordner, Pfad-Anhängsel, Header
prio: P1 (Security, mittel)
aufwand: S (1–2 h)
quelle: docs/analysis/research-security.md SEC-01, SEC-02, SEC-17, SEC-18
dateien: [.htaccess, docs/nginx.example.conf, docs/Caddyfile.example, index.php (goto_page-Header), admin.php (Header), README.md]
abhängig_von: []
---

# S01 – Server-Konfigurationen härten

## Befunde (Details und Reproduktion: research-security.md)
- **SEC-01 (mittel, lokal verifiziert):** `.htaccess:6` `<FilesMatch "\.(json|md|bak|tmp)$">` und die Caddy-Matcher unterscheiden Groß-/Kleinschreibung.
  Auf Dateisystemen ohne diese Unterscheidung (macOS-Mount, manche Windows- oder NAS-Hoster) liefert der Server `URLS.JSON`, `CLICKS.JSON` und `backups/…` aus,
  `CONFIG.PHP` sogar als Quelltext.
- **SEC-02 (niedrig):** Punkt-**Verzeichnisse** (`.git/`) sind erreichbar; `FilesMatch "^\."` greift nur bei Dateien.
- **SEC-18 (info):** `admin.php/<irgendwas>` liefert das Admin aus (PATH_INFO), was relative Assetpfade verwirrt.
- **SEC-17 (info):** `X-Powered-By` verrät die PHP-Version, eine `Permissions-Policy` fehlt.

## Umsetzung
1. `.htaccess`: Muster case-insensitiv: `<FilesMatch "(?i)\.(json|md|bak|tmp|log|lock)$">`. Zusätzlich
   `RedirectMatch 404 "(?i)/(\.|backups/)"` bzw. `RewriteRule "(^|/)\." - [F]` für Punkt-Pfade (`.well-known` ausnehmen).
   `AcceptPathInfo Off`.
2. `docs/nginx.example.conf`: `location ~* \.(json|md|bak|tmp|log|lock)$ { return 404; }`, `location ~ /\.(?!well-known) { return 404; }`,
   `location ^~ /goto/backups/ { return 404; }`.
3. `docs/Caddyfile.example`: `path_regexp` mit `(?i)` für dieselben Muster; Punkt-Pfade sperren.
4. In PHP (für alle Server gleich): `header_remove('X-Powered-By')` am Anfang von `lib.php`; `Permissions-Policy: camera=(self), geolocation=(), microphone=()`
   im Admin (die Kamera braucht der QR-Scan) bzw. `camera=(), …` auf den Besucherseiten.
5. Die **Diagnose-Ampel** um einen Selbsttest erweitern: Abruf von `URLS.JSON` und `.git/HEAD` per Browser-`fetch` muss 403/404 ergeben.

## Tests
- Docker (Mac-Mount = case-insensitiv!): `curl -s -o /dev/null -w '%{http_code}' localhost:8088/goto/URLS.JSON` → 403/404;
  ebenso `CONFIG.PHP` (darf weder Quelltext liefern noch ausgeführt werden – 403), `.git/config`, `backups/`.
- `docker compose --profile servers up -d` → dieselben Checks gegen :8089 (nginx) und :8090 (Caddy).
- Normale Kurzlinks, `/admin` und `/assets/*.css` funktionieren weiter.
