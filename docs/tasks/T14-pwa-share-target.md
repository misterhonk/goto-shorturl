---
id: T14
titel: PWA-Manifest mit Share-Target und Bookmarklet – Links vom Handy aus kürzen
prio: P3 (Feature)
aufwand: S–M (2–3 h)
quelle: research-competitors.md 5.2 Should (YOURLS-Bookmarklet, Slash #31)
dateien: [assets/manifest.webmanifest (neu), admin.php, assets/app.js, .htaccess, docs/nginx.example.conf, docs/Caddyfile.example, deploy.sh]
abhängig_von: [T02, T04]
---

# T14 – Teilen-Ziel und Bookmarklet

## Umsetzung
1. `assets/manifest.webmanifest` mit `name`, `icons` (vorhandenes `apple-touch-icon.png`), `start_url: "../admin"`, `display: "standalone"`,
   `share_target: {action: "../admin", method: "GET", params: {url: "url", text: "text", title: "title"}}`.
   `<link rel="manifest">` im Admin-`<head>`. Den MIME-Typ `application/manifest+json` in den Server-Configs prüfen.
2. `admin.php`: Ist `?url=` (oder eine URL in `?text=`, wie Android sie oft liefert) gesetzt und der Nutzer eingeloggt, wird das Anlegeformular vorbefüllt
   und fokussiert. Es wird **nicht** automatisch angelegt (CSRF/GET).
   Ist der Nutzer nicht eingeloggt: Nach dem Login zurück mit dem Parameter (nur gleiche Herkunft, sonst wäre das ein Open Redirect).
3. Bookmarklet in der Toolbox: `javascript:location.href='<base>/admin?url='+encodeURIComponent(location.href)` zum Ziehen in die Lesezeichenleiste.
4. Kein Service Worker nötig (Share-Target braucht ihn in Chrome nicht mehr zwingend; prüfen). Falls doch nötig: ein minimaler SW ohne Caching von Admin-HTML.
5. `deploy.sh` nimmt die neue Datei mit (prüfen, ob `assets/*` schon pauschal erfasst ist).

## Akzeptanzkriterien
- Android Chrome: „Zum Startbildschirm“ und dann im Teilen-Menü „GOTO“ → Admin öffnet sich mit vorbefülltem URL-Feld.
- Desktop: Bookmarklet auf einer beliebigen Seite → Admin mit vorbefüllter URL.
- Die CSP bleibt `'self'`. Das Bookmarklet läuft auf der fremden Seite und braucht keine CSP-Änderung.
