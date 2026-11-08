# GOTO – Security-Audit (Stand 2026-11-08, Version 1.3.3)

Umfang: Code-Review von `index.php`, `admin.php`, `api.php`, `lib.php`, `config.php`,
`.htaccess`, `docs/nginx.example.conf`, `docs/Caddyfile.example`, `assets/app.js`
(+ QR-Logo-Pfad), Stack-Recherche (Teil B) und vorsichtige dynamische Tests gegen
den Docker-Container `goto` (php:8.2-apache, Apache 2.4.67, PHP 8.2.31) unter
`http://localhost:8088/goto/`. Die nginx- (:8089) und Caddy-Container (:8090) liefen
nicht – deren Configs sind **nur per Review** geprüft.

Dynamisch angelegte Test-Links (`sec-a`, `sec-b`, `sec-c`, `sec-u*`) wurden danach
gelöscht und einzeln aus dem Papierkorb entfernt (`urls.json`, `clicks.json`,
`.ht_trash.json` enthalten keine `sec-`-Einträge mehr). Kein Passwortwechsel, keine
2FA-Aktivierung, kein Login-Brute-Force, kein Import, kein Linkcheck (der hätte alle
Links des parallelen Testers abgefragt).

Bereits auditierte und behobene Themen (Clickjacking, Setup-Race mit leerer
`.ht_auth.json`, Locks, Remember-Me-Bindung, SSRF-Guard, nginx-Quelltext-Leak) sind
nicht erneut aufgeführt – **außer SEC-03**, weil der SSRF-Fix im Linkcheck unvollständig ist.

Legende: **[verifiziert]** = dynamisch reproduziert oder per Laufzeittest belegt;
**[Code]** = eindeutig aus dem Quelltext, nicht live ausgelöst; **[vermutet]** =
hängt von Umgebung/externer Infrastruktur ab.

---

## Übersicht

| ID | Schwere | Status | Kurz |
|---|---|---|---|
| SEC-01 | mittel | verifiziert (lokal) | Case-insensitive Dateisysteme: `URLS.JSON`, `CONFIG.PHP` (Quelltext!), Backups abrufbar |
| SEC-02 | niedrig | verifiziert (lokal) | Apache sperrt Punkt-**Verzeichnisse** nicht (`.git/config` → 200) |
| SEC-03 | mittel | Code | Linkcheck folgt Redirects → SSRF-Guard umgehbar (Fix aus früherem Audit unvollständig) |
| SEC-04 | niedrig | vermutet/Code | SSRF-Guard: nur A-Records, DNS-Rebinding (TOCTOU), Lücken bei 100.64/10, 198.18/15, 192.0.0/24 |
| SEC-05 | mittel | Code | Brute-Force-Bremse nur je IP: IPv6-/64-Rotation, Überlauf-Reset bei >2000 Einträgen |
| SEC-06 | niedrig | vermutet | Hinter Reverse-Proxy teilen sich alle eine IP → Admin-Aussperrung (DoS) |
| SEC-07 | niedrig | Code | TOTP-Codes innerhalb ±30 s wiederverwendbar (kein Replay-Schutz) |
| SEC-08 | niedrig | Code | Passwortprüfung bei `change_password`/`totp_disable` ohne Rate-Limit |
| SEC-09 | niedrig | Code | Remember-Cookie `SameSite=Lax` + GET-Endpunkte mit Wirkung (Logout-CSRF, Titel-Fetch) |
| SEC-10 | niedrig | Code | Setup: parallele Erst-Passwörter überschreiben sich (kein „nur wenn leer") |
| SEC-11 | niedrig | Code | Voll-Backup-Import/Restore: Papierkorb/Klicks ungeprüft, Restore ohne `valid_url`, kein Größenlimit |
| SEC-12 | niedrig | verifiziert | HSTS `includeSubDomains` aus einem Unterordner-Tool gilt für die ganze Domain; `X-Forwarded-Proto` ungeprüft |
| SEC-13 | info | verifiziert | Host-Header wird in `og:url`, `og:image`, Kurzlinks/QR, API `short_url` übernommen |
| SEC-14 | info | verifiziert | Query-Durchreichung: beliebige Parameter ans Ziel (Parameter-Pollution), `?slug=` übersteuert Pfad |
| SEC-15 | info | verifiziert | Phishing-taugliche Ziele erlaubt (`https://paypal.com@evil.example`, Punycode) |
| SEC-16 | info | Code | API-Token auch per `?token=` (landet in Logs/History) |
| SEC-17 | info | verifiziert | Versions-Leak (`X-Powered-By`, `Server`), keine `Permissions-Policy` |
| SEC-18 | info | verifiziert | `admin.php/<irgendwas>` liefert das Admin (PATH_INFO) |
| SEC-19 | info | Code | Klick-Zählung: Schreibzugriff je Aufruf (Flood-DoS), Bot-Ausnahme per UA trivial |
| SEC-20 | info | Code | Diagnose prüft nur `urls.json`; Mindest-PHP 8.0 ist EOL |
| SEC-21 | info | Code | jsQR 1.4.0 ungewartet (keine CVEs); SVG-Logo sicher |

Positiv bestätigt (verifiziert): Titel-/OG-/Vorschau-/Passwort-Seite und Admin escapen
konsequent (`"><script>…`, `</title><script>` landen als Entities); `javascript:`,
`data:`, `file:`, Leerzeichen-Hosts und Unicode-IDN werden abgewiesen; `%0d%0a` im Ziel
bleibt kodiert (keine Header-Injection); 9-KB-Query → 414; kleingeschriebene
Datendateien, `.ht_*`, `config.php`, `*.bak`, `*.md` → 403; `backups/` ohne Listing;
Titel-Fetch auf `127.0.0.1`, `2130706433`, `127.1`, `localhost.`, `[::1]`, `0` erzeugt
**keinen** Request (Apache-Log ohne `GOTO-TitleFetch`); CSRF auf allen POST-Aktionen;
`hash_equals` bei CSRF/Token/Remember/TOTP; Zufall durchgängig `random_bytes`/
`random_int` (Slug 6 Zeichen aus 32 ≈ 30 Bit – für Kurzlinks ok, API-Token 192 Bit);
CSV-Formel-Injection nicht ausnutzbar (Slug nur `[a-z0-9-]`, Titel nicht im CSV);
QR-Logo-Upload (auch SVG) wird nur als `<img>` → Canvas → PNG verarbeitet, kein Script-Kontext;
Farbwerte stammen aus `<input type=color>` (vom Browser auf `#rrggbb` normalisiert).

---

## Befunde

### SEC-01 – Dateischutz greift nur bei exakter Kleinschreibung (Daten + PHP-Quelltext)
- **Schwere:** mittel (auf case-insensitiven Dateisystemen hoch; auf Linux-Produktion – Uberspace, IONOS – nicht ausnutzbar)
- **Status:** verifiziert (Docker auf macOS-Bind-Mount = case-insensitive)
- **Fundstelle:** `.htaccess:6`, `.htaccess:12`; `docs/Caddyfile.example:18` (Regex ohne `(?i)`); nginx ist mit `~*` korrekt, aber `location = /goto/config.php` (`docs/nginx.example.conf:30`) ist ebenfalls case-sensitiv.
- **Beschreibung:** `FilesMatch "\.(json|md|bak|tmp)$"` und `<Files "config.php">` vergleichen case-sensitiv. Liegt GOTO auf einem case-insensitiven Dateisystem (macOS, Windows-Hosting, manche NAS/SMB-Mounts – und genau die dokumentierte lokale Docker-Umgebung), liefert Apache die Dateien unter anderer Schreibweise aus. Bei `.PHP` greift zudem der PHP-Handler nicht → **Quelltext**-Ausgabe.
- **Reproduktion:**
  ```
  curl -i http://localhost:8088/goto/URLS.JSON          # 200, komplette urls.json (inkl. Link-Passwort-Hashes)
  curl -i http://localhost:8088/goto/CLICKS.JSON        # 200
  curl -i http://localhost:8088/goto/URLS.JSON.BAK      # 200
  curl -i http://localhost:8088/goto/backups/URLS-2026-11-08.JSON  # 200
  curl -s http://localhost:8088/goto/CONFIG.PHP | head  # PHP-Quelltext von config.php
  curl -s http://localhost:8088/goto/ADMIN.PHP | head   # Quelltext admin.php
  ```
  (`.HT_AUTH.JSON` bleibt durch `^\.` gesperrt.)
- **Fix:**
  ```apache
  <FilesMatch "(?i)\.(json|md|bak|tmp|log|lock)$">
      Require all denied
  </FilesMatch>
  <FilesMatch "(?i)^(config|lib|lang)\.php$">   # lib/lang zusätzlich: nie direkt nötig
      Require all denied
  </FilesMatch>
  ```
  Caddy: `path_regexp (?i)^/goto/(…)$`. nginx: `location ~* ^/goto/(config|lib|lang)\.php$ { return 404; }` **vor** dem PHP-Block. Zusätzlich README-Hinweis: Daten außerhalb des Web-Roots (`data_dir`) ist die eigentliche Lösung.
- **Test:** Obige curl-Aufrufe → 403/404; Diagnose um eine Prüfung „`URLS.JSON` (Großschreibung) gesperrt" ergänzen (siehe SEC-20).

### SEC-02 – Punkt-Verzeichnisse werden unter Apache ausgeliefert
- **Schwere:** niedrig (Produktion nur betroffen, wenn jemand das ganze Repo per FTP hochlädt; `deploy.sh` tut das nicht)
- **Status:** verifiziert (lokal)
- **Fundstelle:** `.htaccess:9` – `<FilesMatch "^\.">` prüft nur den **Dateinamen**, nicht Pfadsegmente.
- **Beschreibung:** `/goto/.git/config` und `/goto/.git/HEAD` liefern 200 → mit Tools wie git-dumper lässt sich das Repo samt Historie ziehen. Gleiches gilt für `.github/`, `.claude/`. Lokal außerdem ungeschützt: `deploy.sh`, `Dockerfile`, `docker-compose.yml`, `tests/`, `docs/*.conf`, `dist/*.zip`, `screenshot.png` (kein Geheimnis, aber Fingerprinting).
- **Reproduktion:** `curl -i http://localhost:8088/goto/.git/config` → 200.
- **Fix:** in `.htaccess` zusätzlich
  ```apache
  RedirectMatch 404 "(^|/)\."
  ```
  (oder `RewriteRule "(^|/)\." - [F]`). nginx/Caddy decken Punkt-Pfadsegmente bereits ab.
- **Test:** `curl -o /dev/null -w '%{http_code}' …/goto/.git/config` → 404; `…/goto/.ht_auth.json` weiterhin 403/404.

### SEC-03 – Linkcheck folgt Redirects: SSRF-Schutz umgehbar
- **Schwere:** mittel (blind – nur HTTP-Statuscodes kommen zurück; Auslöser ist der Admin, Ziele können aber auch per API-Schreibtoken gesetzt werden)
- **Status:** Code (nicht live ausgelöst, weil der Linkcheck alle Links des parallelen Testers abgefragt hätte)
- **Fundstelle:** `admin.php:843–849` (`check_links`): `CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4`; Host-Prüfung nur für die Start-URL (`admin.php:842`).
- **Beschreibung:** `host_is_public()` prüft nur den ersten Host. Ein öffentliches Ziel, das per 30x auf `http://127.0.0.1:…`, `http://169.254.169.254/…` oder ein internes Netz weiterleitet, wird verfolgt. Ergebnis (Statuscode, „toter Link") erscheint in der Admin-Liste → Port-/Dienst-Scan des internen Netzes bzw. Auslösen interner GET/HEAD-Endpunkte. Ohne `CURLOPT_PROTOCOLS`/`CURLOPT_REDIR_PROTOCOLS` hängt die Protokollmenge zudem von der libcurl-Version ab (moderne Defaults: http/https/ftp/ftps).
- **Reproduktion (in einer Wegwerf-Instanz):** Link `sec-ssrf` → `https://<eigener-server>/r` mit `Location: http://127.0.0.1:8088/goto/admin`; Diagnose → „Links prüfen"; im Access-Log des Containers erscheint `GOTO-LinkCheck/1.0` von 127.0.0.1.
- **Fix:** Redirects selbst verfolgen und jeden Hop prüfen:
  ```php
  CURLOPT_FOLLOWLOCATION => false,
  CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
  // nach curl_multi: bei 3xx CURLINFO_REDIRECT_URL holen, valid_url + host_is_public
  // prüfen, max. 4 Hops in einer zweiten Runde
  ```
  Minimal-Variante: `FOLLOWLOCATION => false` und 3xx als „erreichbar" werten (ein Redirect ist kein toter Link).
- **Test:** Unit-artiger Test mit `php -S 127.0.0.1:9999` als „öffentlichem" Redirector ist wegen des Guards schwer – stattdessen `host_is_public` per Testschalter übersteuern und prüfen, dass `check_links` bei `Location: http://127.0.0.1/` keinen zweiten Request absetzt (Access-Log zählen).

### SEC-04 – SSRF-Guard: AAAA ungeprüft, DNS-Rebinding, unvollständige Bereichsfilter
- **Schwere:** niedrig (nur eingeloggt; Titel-Fetch liefert aber den `<title>` interner Seiten zurück, ist also nicht blind)
- **Status:** teils verifiziert (Filterlücken per `php -r` im Container), Rest vermutet (benötigt eigene DNS-Zone)
- **Fundstelle:** `admin.php:780–793` (`host_is_public`), `admin.php:795–822` (`fetch_page_title`), `admin.php:842`.
- **Beschreibung:**
  1. `gethostbynamel()` liefert nur IPv4. Ein Host mit öffentlichem A- und internem AAAA-Record (`::1`, `fd00::/8`, `::ffff:127.0.0.1`) besteht die Prüfung; curl verbindet bevorzugt per IPv6 (Happy Eyeballs).
  2. TOCTOU/DNS-Rebinding: Prüfung und curl lösen getrennt auf. TTL-0-Rebinding liefert zuerst eine öffentliche, dann eine interne IP.
  3. `FILTER_FLAG_NO_PRIV_RANGE|NO_RES_RANGE` lässt in PHP 8.2 durch (verifiziert): `100.64.0.0/10` (CGNAT – bei Hostern oft intern), `192.0.0.0/24`, `198.18.0.0/15`, sowie bei IPv6 `::ffff:127.0.0.1`, `64:ff9b::7f00:1` (NAT64), `2002:7f00:1::` (6to4). Relevant, sobald (1) behoben und AAAA geprüft wird.
  - Gut: IPv6-Literale (`http://[::1]/`) und Dezimal/Oktal-IPs (`2130706433`, `017700000001`, `127.1`) werden korrekt abgewiesen; `0`/`0.0.0.0` ebenso.
- **Reproduktion (Filter):** `docker exec goto php -r 'var_dump(filter_var("100.64.1.1",FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE));'` → `string(10) "100.64.1.1"`.
- **Fix:**
  ```php
  // A + AAAA auflösen
  $recs = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
  $ips  = array_merge(array_column($recs, 'ip'), array_column($recs, 'ipv6'));
  // PHP >= 8.2: FILTER_FLAG_GLOBAL_RANGE deckt CGNAT, 198.18/15, 192.0.0/24 usw. ab
  filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE);
  // IPv4-mapped/NAT64/6to4 explizit ablehnen; dann die geprüfte IP an curl pinnen:
  CURLOPT_RESOLVE => ["$host:$port:$ip"],
  ```
  Der `file_get_contents`-Fallback lässt sich nicht pinnen → bei fehlendem curl Titel-Fetch abschalten.
- **Test:** Unit-Test für `host_is_public` mit Liste (`100.64.1.1`, `198.18.0.1`, `::ffff:127.0.0.1`, `8.8.8.8`); Rebinding mit einem eigenen TTL-0-Testhost (z. B. selbstgehostetes rbndr) prüfen, dass curl dieselbe IP nutzt (`CURLINFO_PRIMARY_IP`).

### SEC-05 – Brute-Force-Bremse nur pro IP (IPv6, Botnetze, Überlauf-Reset)
- **Schwere:** mittel (Mindestlänge 8 Zeichen + verteiltes Raten = realistischer Wörterbuchangriff; betrifft Admin-Login, TOTP-Schritt und Link-Passwörter)
- **Status:** Code (kein Brute-Force-Test laut Auftrag)
- **Fundstelle:** `admin.php:362` (`$clientKey = hash(REMOTE_ADDR)`), `index.php:243`, `lib.php:329–352` (Überlauf-Bereinigung `count($all) > 2000`).
- **Beschreibung:** Jede IPv6-Adresse ist ein eigener Zähler; wer ein /64 hat (jeder IPv6-Anschluss, viele VPS), bekommt praktisch unbegrenzte Versuche (5 pro Adresse). Durchsatz wird nur durch bcrypt + `usleep(300000)` begrenzt – parallel sind trotzdem dutzende Versuche/s möglich. Zusätzlich: Sobald `.ht_attempts.json` > 2000 Einträge hat, werden **alle nicht gesperrten Zähler gelöscht** – ein verteilter Angreifer setzt damit laufende Zähler (auch die anderer Angreifer-IPs) zurück. Für den TOTP-Schritt gilt dasselbe: mit bekanntem Passwort sind ~330 000 Versuche für einen Treffer nötig, mit IPv6-Rotation machbar.
- **Reproduktion:** (nur in Wegwerf-Instanz) 6 Fehlversuche mit `curl --interface <ipv6-a>` → gesperrt; derselbe Versuch von `<ipv6-b>` aus demselben /64 → nicht gesperrt.
- **Fix:**
  1. IPv6 auf /64 normalisieren: `inet_pton` → erste 8 Byte hashen.
  2. Zusätzlicher globaler Zähler je Ziel (`'global:admin'`, `'global:pw:'.$slug`) mit höherem Schwellwert (z. B. 50/15 min) → danach progressive Verzögerung statt harter Sperre (sonst Admin-DoS).
  3. Überlauf nicht durch Löschen der Zähler lösen, sondern älteste Einträge verdrängen (LRU nach `until`/`last`).
  4. Mindestpasswortlänge auf 12 anheben (Setup + `change_password`).
- **Test:** PHPUnit-artiger Test für den Schlüssel (`2001:db8::1` und `2001:db8::2` → gleicher Key); Lasttest in Wegwerf-Instanz mit 2100 gefälschten Keys → eigener Zähler bleibt erhalten.

### SEC-06 – Reverse-Proxy: alle Besucher teilen eine IP → Admin-Aussperrung
- **Schwere:** niedrig (abhängig vom Hoster)
- **Status:** vermutet (Live-Hoster nicht getestet)
- **Fundstelle:** `admin.php:362`, `index.php:243`.
- **Beschreibung:** GOTO nutzt bewusst nur `REMOTE_ADDR` (gut: kein `X-Forwarded-For`-Spoofing möglich). Setzt der Hoster aber einen Proxy davor, ohne `mod_remoteip`/`real_ip` zu konfigurieren, ist `REMOTE_ADDR` für alle gleich: 5 Fehlversuche eines Fremden sperren den Admin 15 Minuten aus (wiederholbar = Dauer-DoS).
- **Fix:** Optionales `'trusted_proxies' => ['10.0.0.1']` in `config.php`; nur dann die letzte nicht-vertrauenswürdige IP aus `X-Forwarded-For` nehmen. In der Diagnose `REMOTE_ADDR` (maskiert) und das Vorhandensein von `X-Forwarded-For` anzeigen.
- **Test:** Auf Uberspace/IONOS die Diagnose aufrufen und prüfen, ob `REMOTE_ADDR` der eigenen IP entspricht; Unit-Test für die Proxy-Auswertung mit gefälschtem XFF ohne Trusted-Proxy → ignoriert.

### SEC-07 – TOTP ohne Replay-Schutz
- **Schwere:** niedrig (setzt Passwort + mitgelesenen Code voraus)
- **Status:** Code
- **Fundstelle:** `admin.php:142–149`.
- **Beschreibung:** Ein gültiger Code bleibt im ±1-Fenster bis zu ~90 s mehrfach verwendbar (Shoulder-Surfing, Phishing-Proxy).
- **Fix:** Zuletzt akzeptierten Zeitschritt in `.ht_auth.json` (unter `with_locked_json`) speichern und nur Schritte `> last` akzeptieren.
- **Test:** Zwei Logins mit demselben Code innerhalb 30 s → zweiter schlägt fehl.

### SEC-08 – Passwortprüfung in eingeloggten Aktionen ohne Bremse
- **Schwere:** niedrig
- **Status:** Code
- **Fundstelle:** `admin.php:1127` (`change_password`), `admin.php:1283` (`totp_disable`).
- **Beschreibung:** Wer eine Sitzung übernommen hat (offener Browser, geklautes Cookie), kann das aktuelle Passwort unbegrenzt raten und anschließend ändern bzw. 2FA abschalten.
- **Fix:** `attempt_begin('reauth:'.$clientKey, 5, 900)` vor `password_verify`, bei Erfolg `clear_fails`; nach 5 Fehlversuchen Sitzung beenden.
- **Test:** 6× falsches „aktuelles Passwort" → Sperrmeldung.

### SEC-09 – Remember-Cookie `SameSite=Lax` + GET-Endpunkte mit Wirkung
- **Schwere:** niedrig
- **Status:** Code
- **Fundstelle:** `admin.php:255` (Lax), `admin.php:669` (`?logout`), `admin.php:872` (`?fetchtitle`), `admin.php:326` (`?lang`), Auto-Login `admin.php:696–707`.
- **Beschreibung:** Die Session ist `Strict`, aber das Remember-Cookie wird bei Top-Level-Navigation von fremden Seiten mitgeschickt, und `remember_try` meldet daraufhin an. Damit sind GET-Aktionen cross-site auslösbar: Logout-CSRF, Server-Request über `?fetchtitle=` (der Server ruft eine vom Angreifer gewählte öffentliche URL ab), Sprachwechsel. Daten fließen nicht ab (Antworten nicht lesbar), aber jede Cross-Site-Navigation rotiert auch das Remember-Token.
- **Fix:** Logout als POST mit CSRF; `fetchtitle`/`clickdata` nur mit `X-CSRF`-Header (app.js sendet ihn) oder `Sec-Fetch-Site: same-origin`; `remember_try` nur ausführen, wenn `Sec-Fetch-Site` nicht `cross-site` ist.
- **Test:** Fremde Seite mit `<a href="…/goto/admin?logout">` → Klick darf nicht abmelden.

### SEC-10 – Setup: parallele Erst-Passwörter
- **Schwere:** niedrig
- **Status:** Code
- **Fundstelle:** `admin.php:106–111` (`save_hash`), `admin.php:617–632`.
- **Beschreibung:** Prüfung „keine Auth-Datei" erfolgt außerhalb der Sperre, `save_hash` überschreibt immer. Zwei gleichzeitige Setup-POSTs (Betreiber + Angreifer, der neue Installationen abgrast) erhalten beide „gespeichert"; der letzte gewinnt, ohne dass der Betreiber es merkt.
- **Fix:** Setup-Variante `save_hash_initial()`: im Lock `if (!empty($a['hash'])) return null;` und Rückgabe auswerten (bei Konflikt Fehlermeldung statt Erfolg).
- **Test:** Zwei parallele `curl -d new_password=…` gegen frische Instanz → genau einer erhält Erfolg.

### SEC-11 – Voll-Backup-Import und Restore ungeprüft
- **Schwere:** niedrig (nur Admin, erfordert präparierte Datei – „Importiere mal unser Backup" ist aber ein plausibles Social-Engineering)
- **Status:** Code (Import laut Auftrag nicht ausgeführt)
- **Fundstelle:** `admin.php:1098–1105` (Clicks/Trash 1:1 übernommen), `admin.php:1141–1159` (Restore ohne `valid_url`/`clean_*`), `admin.php:1096` (kein Größenlimit).
- **Beschreibung:** Papierkorb-Einträge aus dem Backup werden beim Wiederherstellen ungefiltert nach `urls.json` geschrieben (`url` z. B. `javascript:…`, beliebig langer Titel, nicht-bcrypt `pass`). `index.php` blockt Nicht-http(s)-Ziele zwar (Defense-in-Depth), und der nächste `load_data()` verwirft den Eintrag – Datenmüll und Inkonsistenz bleiben. Große Dateien (bis `upload_max_filesize`) werden komplett in den Speicher dekodiert; `clicks` kann beliebig tief verschachtelt sein.
- **Fix:** Restore über `normalize_data(['links' => [$slug => $it]])` laufen lassen und bei leerem Ergebnis abbrechen; Trash beim Import ebenfalls normalisieren; Upload > 5 MB und > 10 000 Links ablehnen; `clicks` auf `{t:int,d:{date:int},s:{str:int}}` filtern.
- **Test:** Backup mit Trash-Eintrag `url: "javascript:alert(1)"` importieren → Restore meldet Fehler, `urls.json` bleibt sauber.

### SEC-12 – HSTS `includeSubDomains` und ungeprüftes `X-Forwarded-Proto`
- **Schwere:** niedrig (Betriebsrisiko)
- **Status:** verifiziert (Header), Wirkung vermutet
- **Fundstelle:** `index.php:78`, `index.php:181`, `admin.php:351`; `lib.php:68–72`.
- **Beschreibung:** GOTO liegt typischerweise in einem Unterordner (`misterhonk.de/goto/`), setzt aber HSTS für **die ganze Domain inkl. aller Subdomains** für ein Jahr. Gibt es eine Subdomain ohne gültiges Zertifikat (Intranet, Test, alte Dienste), ist sie für jeden GOTO-Besucher ein Jahr lang unerreichbar – nicht rückholbar. Außerdem wird `X-Forwarded-Proto: https` jedem Client geglaubt (verifiziert: Header setzt HSTS auf HTTP-Antwort; Browser ignorieren HSTS über HTTP, daher kein Angriff, aber `Secure`-Cookies und Base-URL lassen sich so umschalten).
- **Fix:** `includeSubDomains` nur per Option (`'hsts_subdomains' => false` als Standard); `X-Forwarded-Proto` nur bei konfigurierten Trusted Proxies (siehe SEC-06) auswerten.
- **Test:** `curl -sI https://…/goto/x | grep -i strict` → ohne `includeSubDomains`; `curl -H 'X-Forwarded-Proto: https' http://…` → kein HSTS.

### SEC-13 – Host-Header-Reflexion
- **Schwere:** info
- **Status:** verifiziert
- **Fundstelle:** `lib.php:55–58` (`base_url`), genutzt in `index.php:274/321`, `api.php:129/296`, Admin-Kurzlinks/QR.
- **Reproduktion:** `curl -A Slackbot -H 'Host: evil.example' http://localhost:8088/goto/<slug>` → `og:url="http://evil.example/goto/<slug>"`.
- **Risiko:** Nur relevant mit cachendem Proxy/CDN (Cache-Poisoning der Bot-Vorschau) oder falsch konfiguriertem vhost-Default. Escaping ist korrekt.
- **Fix:** optional `'base_url' => 'https://misterhonk.de/goto/'` in `config.php`, sonst wie bisher.
- **Test:** mit gesetzter Option ändert ein fremder Host-Header die Ausgabe nicht.

### SEC-14 – Query-Durchreichung erlaubt Parameter-Pollution am Ziel
- **Schwere:** info (bewusstes Feature)
- **Status:** verifiziert
- **Fundstelle:** `index.php:101–114` (`merge_query`), `index.php:26`.
- **Beschreibung:** `…/slug?next=https://evil.example` hängt `next=` an das Ziel. Hat die Zielseite einen Open-Redirect- oder Login-`return`-Parameter, wird der vertrauenswürdige Kurzlink zum Phishing-Sprungbrett. Außerdem übersteuert `?slug=` den Pfad (`/sec-c?slug=x` → 404), was bei gedruckten Codes mit Tracking-Zusätzen überraschen kann.
- **Fix:** Option je Link oder global „Parameter durchreichen: alle / nur `utm_*` / keine" (Standard: `utm_*` + `gq`); `slug` nur aus dem Pfad lesen, wenn das Rewrite (Variante A) aktiv ist.
- **Test:** `curl -o /dev/null -w '%{redirect_url}' '…/slug?utm_source=a&next=x'` → nur `utm_source` am Ziel.

### SEC-15 – Phishing-taugliche Ziele werden akzeptiert
- **Schwere:** info (Ziele setzen nur Admin/API-Schreibtoken; relevant bei geleaktem Token)
- **Status:** verifiziert
- **Fundstelle:** `lib.php:141–145` (`valid_url`).
- **Beschreibung:** `https://paypal.com@evil.example/` (Userinfo-Täuschung) und `https://xn--pypal-4ve.com/` (Punycode-Homograph) werden gespeichert und weitergeleitet. Unicode-IDN (`пример.рф`) lehnt `FILTER_VALIDATE_URL` ab – Admins sehen also immer Punycode (gut). Hinweis: CVE-2024-5458 (Bypass in `FILTER_VALIDATE_URL` mit Userinfo) betrifft PHP < 8.1.29/8.2.20/8.3.8.
- **Fix:** In `valid_url` Userinfo verbieten (`parse_url(...)['user'] ?? null` → false); bei `xn--`-Labels im Admin einen Warn-Chip anzeigen.
- **Test:** Anlegen von `https://a@b.example` → Fehlermeldung.

### SEC-16 – API-Token per Query-String erlaubt
- **Schwere:** info
- **Status:** Code
- **Fundstelle:** `api.php:66`, Doku `api.php:9`.
- **Beschreibung:** `?token=goto_…` landet in Access-Logs, Proxy-Logs und Shell-History.
- **Fix:** nur `Authorization`/`X-Api-Key` (Body-Feld höchstens für POST behalten), `$_GET['token']` entfernen; README anpassen.
- **Test:** `curl '…/api.php?token=…'` → 401.

### SEC-17 – Versions-Leak, fehlende Permissions-Policy
- **Schwere:** info
- **Status:** verifiziert
- **Fundstelle:** alle Antworten (`X-Powered-By: PHP/8.2.31`, `Server: Apache/2.4.67 (Debian)`); `admin.php:346–360`, `index.php:173–186`.
- **Fix:** `header_remove('X-Powered-By');` in `lib.php`; `Permissions-Policy: camera=(self), microphone=(), geolocation=(), interest-cohort=()` im Admin (Kamera für den QR-Scanner), auf öffentlichen Seiten `camera=()`. `Server`-Banner ist Hoster-Sache (`ServerTokens Prod`).
- **Test:** `curl -sI …/goto/admin | grep -i 'powered\|permissions'`.

### SEC-18 – Admin unter beliebigem PATH_INFO erreichbar
- **Schwere:** info
- **Status:** verifiziert (`/goto/admin.php/sec-c` → 200)
- **Fundstelle:** `admin.php:318–323` (Kanonisierung greift nur bei Endung `/admin.php`).
- **Beschreibung:** Relative Asset-Pfade zeigen dann auf `admin.php/assets/…` (liefert HTML; dank `nosniff` und CSP ohne Wirkung). Kein aktiver Exploit, aber unnötige Angriffsfläche (RPO).
- **Fix:** `if (!empty($_SERVER['PATH_INFO'])) { header('Location: '.$self, true, 301); exit; }`.
- **Test:** `curl -I …/goto/admin.php/x` → 301 auf `/goto/admin`.

### SEC-19 – Klick-Zählung: Schreiblast und Bot-Ausnahme
- **Schwere:** info
- **Status:** Code
- **Fundstelle:** `index.php:118–145`, `index.php:92–96`.
- **Beschreibung:** Wachstum von `clicks.json` ist sauber begrenzt (nur existierende Slugs, 90 Tage, 50 Quellen mit Verdrängung) – der in der Aufgabe befürchtete `?gq=`-Wachstumsangriff greift nicht. Jeder Aufruf schreibt aber `clicks.json` komplett unter globaler Sperre neu; eine Flut auf einen Kurzlink serialisiert alle Weiterleitungen (DoS/IO-Last auf Shared Hosting) und verfälscht Statistiken. Wer `User-Agent: Slackbot` sendet, wird nie gezählt.
- **Fix:** Akzeptieren (Statistik ist „best effort"), README-Hinweis; optional Zählung pro Prozess puffern oder bei Lock-Contention (`LOCK_NB`) den Klick verwerfen statt zu warten.
- **Test:** `ab -n 2000 -c 50 …/goto/<slug>` vor/nach `LOCK_NB` – Latenz vergleichen.

### SEC-20 – Diagnose zu schmal, Mindest-PHP veraltet
- **Schwere:** info
- **Status:** Code
- **Fundstelle:** `admin.php:1943` (`PHP_VERSION_ID >= 80000`), `admin.php:1971` (nur `urls.json` wird per fetch geprüft), README „PHP 8.0+".
- **Fix:** Diagnose-Fetch-Prüfungen zusätzlich für `.ht_auth.json`, `URLS.JSON` (SEC-01), `backups/urls-<heute>.json`, `config.php` (darf keinen Quelltext liefern), `.git/HEAD`. PHP-Prüfung mit EOL-Tabelle (siehe Teil B).
- **Test:** In der Docker-Umgebung (case-insensitive) muss die neue Prüfung vor dem Fix rot, danach grün sein.

### SEC-21 – Abhängigkeit jsQR 1.4.0 / QR-Logo
- **Schwere:** info
- **Status:** Code + Recherche
- **Beschreibung:** jsQR 1.4.0 (April 2021) ist das letzte Release, das Projekt gilt als inaktiv; Snyk führt keine bekannten Schwachstellen. Es verarbeitet nur Kamerabilder lokal im Admin; das Ergebnis wird in `app.js:404–445` per `esc()` (textContent → innerHTML) bzw. als URL-Präfill behandelt – sauber. SVG-Logos werden nur als `<img>` dekodiert und als PNG in den Export geschrieben (kein Script-Kontext).
- **Fix:** Datei unverändert lassen, SHA-256 in README/CI festhalten (`sha256sum assets/jsqr.js`), damit Manipulation auffällt. Langfristig optional `BarcodeDetector` (nativ in Chromium/Safari) bevorzugen und jsQR nur als Fallback.
- **Test:** CI-Schritt vergleicht den Hash.

---

## Teil B – Stack-Risiken (Stand November 2026)

### PHP
- **EOL:** 8.0 (Nov 2023) und **8.1 (31.12.2025) sind EOL**. **8.2 erhält nur noch bis 31.12.2026 Sicherheitsfixes** – in ~7 Wochen EOL. 8.3: Sicherheitsfixes bis 31.12.2027; 8.4 bis Ende 2028; 8.5 (Nov 2025) bis Ende 2029. Eine Quelle (Versio.io) nennt für 8.3 abweichend Nov 2026 – laut offizieller Policy (2 Jahre aktiv + 2 Jahre Security) ist Ende 2027 korrekt; maßgeblich ist php.net/supported-versions.
- **Relevante CVEs:**
  - CVE-2024-4577 (PHP-CGI-Argument-Injection, nur Windows/CGI, kritisch, aktiv ausgenutzt) – für IONOS/Uberspace (Linux, FPM) nicht relevant, aber Grund, nie Windows-CGI zu nutzen.
  - CVE-2024-5458 – Bypass in `filter_var(FILTER_VALIDATE_URL)` (Userinfo). GOTO stützt `valid_url` darauf → PHP ≥ 8.2.20 / 8.3.8 nötig.
  - CVE-2024-3096 – `password_verify` mit Passwort, das mit NUL-Byte beginnt (Link-Passwörter theoretisch betroffen, gefixt 8.2.18/8.3.6).
  - 2025: Serie im HTTP-Stream-Wrapper (CVE-2025-1736/-1861/-1734/-1217/-1219) und CVE-2025-1220 (NUL-Byte in Hostnamen) – betrifft den `file_get_contents`-Fallback des Titel-Fetch.
  - 2026 (laut Sekundärquellen, nicht gegen php.net verifiziert): Releases 8.2.34/8.3.35/8.4.26/8.5.11 mit 11 CVEs, u. a. CVE-2026-91766 (Stream-Wrapper reicht Auth/Cookie bei Redirect an fremden Origin weiter – **GOTO nicht betroffen**, da `follow_location => 0`), CVE-2026-12184 (FPM-DoS bei TLS-Fehler, gefixt 8.3.32/8.4.21/8.5.6).
- **Empfehlung für GOTO:**
  - Mindestversion in README/Diagnose auf **PHP 8.2** anheben, **empfohlen 8.4**; ab 01.01.2027 Mindestversion 8.3.
  - Diagnose-Warnung mit fester Tabelle in `lib.php`:
    ```php
    const PHP_EOL_DATES = ['8.0'=>'2023-11-26','8.1'=>'2025-12-31','8.2'=>'2026-12-31',
                           '8.3'=>'2027-12-31','8.4'=>'2028-12-31','8.5'=>'2029-12-31'];
    // gelb: < 90 Tage bis EOL, rot: EOL überschritten
    ```
    (kein Laufzeit-Netzabruf nötig – passt zu „kein externer Dienst").
  - Docker-Testimage von `php:8.2-apache` auf `php:8.4-apache` umstellen; CI-Lint-Matrix 8.2–8.5.
  - `FILTER_FLAG_GLOBAL_RANGE` (SEC-04) setzt PHP ≥ 8.2 voraus – passt zur neuen Mindestversion.

### Apache httpd 2.4
- 2024: „Confusion Attacks" (CVE-2024-38474/-38475/-38476, mod_rewrite/Handler, 2.4.60), Quelltext-Offenlegung bei `AddType`-Handlern (CVE-2024-39884/-40725, 2.4.61/2.4.62) – relevant für jede PHP-Installation.
- 2026: **2.4.67 (04.05.2026)** behebt u. a. CVE-2026-23918 (Double-Free in mod_http2, potenziell RCE), CVE-2026-24072 (mod_rewrite/ap_expr), CVE-2026-34032 (Response-Splitting), CVE-2026-33006 (Digest-Auth-Timing). Der Testcontainer läuft mit 2.4.67 ✔.
- **Empfehlung:** README: „Apache ≥ 2.4.67" (bei Distribution-Paketen: Backport-Stand prüfen). Bei Shared Hosting liegt das beim Hoster.

### nginx
- 2026: CVE-2026-32647 (mp4, RCE möglich), CVE-2026-42945 (ngx_http_rewrite_module Buffer-Overflow – GOTO-Beispielconfig nutzt `rewrite`!), CVE-2026-42926 (HTTP/2-Request-Injection im Proxy), weitere in DAV/SSI/charset. Gefixt in **1.30.1 / 1.31.0** (spätere Einträge nennen 1.30.2/1.31.1 – nicht verifiziert).
- **Empfehlung:** In `docs/nginx.example.conf` Mindestversion 1.30.1 nennen. Optional `rewrite` durch `location = /goto/admin { fastcgi_param SCRIPT_FILENAME $document_root/goto/admin.php; include fastcgi_params; fastcgi_pass …; }` ersetzen.

### Caddy
- 2025/2026 zahlreiche CVEs, teils in Caddy selbst: CVE-2026-27585 (Pfadschutz-Bypass über Glob-Zeichen im File-Matcher – trifft genau Muster wie `@gesperrt`), CVE-2026-52845 (Backslash-Bypass von pfadbasierten Regeln, Windows), CVE-2026-27589 (Admin-API `/load` cross-origin), plus Abhängigkeiten (x/crypto, quic-go).
- **Empfehlung:** Aktuelles Caddy-Release voraussetzen; Admin-API (`:2019`) nicht exponieren; Sperrregel mit `(?i)` (SEC-01) und nach Upgrade mit den SEC-01/02-curls gegen :8090 testen (Container lief beim Audit nicht).

### Shared Hosting (IONOS, Uberspace)
- **IONOS:** PHP-Version im Control-Panel wählbar; EOL-Versionen laufen nur mit **kostenpflichtigem „PHP Extended Support"** weiter (Liste bis 8.1 bestätigt, für 8.2 ab 2027 keine Zusage gefunden). Risiko: Kunden bleiben auf EOL-PHP und zahlen, statt zu aktualisieren → Diagnose-Warnung (s. o.) hilft. Upload ausschließlich per **SFTP**, nicht FTP. Daten liegen dort im Web-Root (kein Ordner oberhalb freigegeben) → `.htaccess`-Schutz ist die einzige Barriere → SEC-01/02-Fixes wichtig.
- **Uberspace:** PHP-Version per `uberspace tools version use php 8.4`; Web-Backend hinter einem Frontend-Proxy → `REMOTE_ADDR` prüfen (SEC-06). `data_dir` außerhalb von `html/` ist möglich und sollte im README als Standard für Uberspace empfohlen werden.
- Allgemein: Nachbarn auf demselben Host – Dateirechte `600/640` für `.ht_*.json`, `config.php`; `umask` beim Schreiben (`save_json`) setzt aktuell keine expliziten Rechte → optional `@chmod($file, 0640)` nach `rename`.

### jsQR 1.4.0
- Keine bekannten CVEs, aber inaktiv (siehe SEC-21).

**Quellen (Teil B):**
[php.net Supported Versions](https://www.php.net/supported-versions.php) ·
[Zend PHP EOL](https://www.zend.com/resources/php-eol) ·
[HeroDevs PHP EOL 2026](https://www.herodevs.com/blog-posts/php-end-of-life-dates-support-timeline-for-every-version-2026) ·
[Versio.io PHP EOL](https://www.versio.io/it/product-release-end-of-life-eol-php-php.html) ·
[PHP Security Release 8.2.34/8.3.35/8.4.26/8.5.11 (rocket-boys)](https://rocket-boys.co.jp/security-measures-lab/php-security-release-8-2-34-8-3-35-8-4-26-8-5-11/) ·
[HeroDevs CVE-2026-91766](https://www.herodevs.com/vulnerability-directory/cve-2026-91766) ·
[Apache httpd 2.4 Vulnerabilities](https://httpd-mobile-responsive.staged.apache.org/security/vulnerabilities_24.html) ·
[OSV OESA-2026-2401](https://osv.dev/vulnerability/OESA-2026-2401) ·
[nginx Security Advisories (Mirror)](https://snell2023.eastus.cloudapp.azure.com/en/security_advisories.html) ·
[oss-security 2026/03/26](https://www.openwall.com/lists/oss-security/2026/03/26/3) ·
[NVD CVE-2026-27585 (Caddy)](https://nvd.nist.gov/vuln/detail/cve-2026-27585) ·
[Tenable CVE-2026-52845](https://www.tenable.com/cve/CVE-2026-52845) ·
[Debian Tracker Caddy](https://security-tracker.debian.org/tracker/caddy) ·
[Snyk jsQR 1.4.0](https://security.snyk.io/package/npm/jsqr/1.4.0) ·
[IONOS PHP Extended Support](https://www.ionos.com/hosting/php-web-hosting) ·
[IONOS Digital Guide: PHP-Version aktualisieren](https://www.ionos.com/digitalguide/websites/web-development/update-php-version-and-cancel-php-extended-support.md) ·
[Futura: Fin support PHP 8.2](https://www.futura-sciences.com/conso/guides-achat/guides-tech-fin-support-php-82-guide-auditer-securiser-votre-hebergement-web-1644/)

Hinweis: Die 2026er CVE-Angaben stammen überwiegend aus Sekundärquellen (Distributions-Advisories, Aggregatoren) und widersprechen sich teils in Nummern/Versionen; vor Übernahme in die README gegen php.net, httpd.apache.org, nginx.org und die Caddy-GitHub-Advisories abgleichen.

---

## Empfohlene Reihenfolge
1. SEC-01 + SEC-02 (je 2–3 Zeilen `.htaccess`/Caddyfile, sofort).
2. SEC-03 (Linkcheck ohne Redirect-Folgen) + SEC-04 (AAAA, `GLOBAL_RANGE`, `CURLOPT_RESOLVE`).
3. SEC-05 (IPv6-/64 + globaler Zähler + kein Überlauf-Reset), dabei SEC-08 mit erledigen.
4. Teil B: Mindest-PHP 8.2/empfohlen 8.4, EOL-Warnung in der Diagnose, SEC-20.
5. Rest (SEC-07, -09, -10, -11, -12 …) im nächsten Patch-Release.
