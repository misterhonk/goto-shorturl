---
id: S04
titel: Härtung Kleinkram – Cookies, Setup-Race, Import-Validierung, HSTS-Scope, Host-Header, Token im Query, Ziel-URL-Täuschung
prio: P2 (Security, niedrig/info)
aufwand: M (3–4 h, gut in Einzelcommits teilbar)
quelle: docs/analysis/research-security.md SEC-09 bis SEC-16, SEC-19
dateien: [lib.php, admin.php, api.php, index.php, config.php, README.md, tests/lib.test.php]
abhängig_von: [T02 (normalize_url) für Teil d]
---

# S04 – Härtung Kleinkram

Jeder Punkt ist ein eigener kleiner Commit. Vor dem Umsetzen den Befund in research-security.md lesen (Fundstelle und Reproduktion stehen dort).

| Teil | Befund | Fix |
|---|---|---|
| a | **SEC-09** Remember-Cookie `SameSite=Lax`; GET-Endpunkte (Logout, `?fetchtitle=`, `?clickdata=`, Exporte) sind von fremden Seiten auslösbar | Session- und Remember-Cookie `SameSite=Strict` (Admin wird nie von außen verlinkt aufgerufen, außer T14-Share: dort nach Login-Redirect ok). Logout nur noch per POST mit CSRF. `fetchtitle` prüft den Header `Sec-Fetch-Site: same-origin`, falls vorhanden |
| b | **SEC-10** Zwei gleichzeitige Setup-Requests überschreiben sich | Setup schreibt `.ht_auth.json` mit `fopen($f,'x')` (exklusiv anlegen); scheitert das → „bereits eingerichtet“ |
| c | **SEC-11** Voll-Backup-Import und Papierkorb-Restore übernehmen Einträge ungeprüft | Beide Wege durch `normalize_data()` und `valid_url()` schicken; Upload-Limit (z. B. 5 MB) und maximale Linkzahl (z. B. 20 000) |
| d | **SEC-15** Ziele wie `https://paypal.com@evil.example` und Punycode-Homographen werden angenommen | `valid_url` lehnt Userinfo (`user:pass@`) ab. Bei `xn--`-Hosts zeigt das Admin die Unicode-Form **und** einen Hinweis „internationalisierte Domain – auf Verwechslung prüfen“ |
| e | **SEC-12** HSTS `includeSubDomains` aus einem Unterordner gilt für die ganze Domain; `X-Forwarded-Proto` wird jedem geglaubt | HSTS ohne `includeSubDomains` (Config `hsts_subdomains` = false); `X-Forwarded-Proto` nur von `trusted_proxies` (siehe S03) |
| f | **SEC-13** Host-Header fließt in `og:url` und die angezeigten Kurzlinks | Config `base_url` (optional). Ist sie gesetzt, wird nur sie verwendet; sonst Host gegen `[A-Za-z0-9.-]+(:\d+)?` prüfen |
| g | **SEC-14** `?slug=` übersteuert den Pfad; beliebige Query-Parameter gehen ans Ziel | `?slug=` nur verwenden, wenn der Pfad leer ist (Rewrite-Fallback). Die Durchreichung ist gewolltes Feature und wird im README als Hinweis dokumentiert. Optional pro Link `passthrough=false` |
| h | **SEC-16** API-Token auch per `?token=` (landet in Logs/Referrer) | `?token=` deprecaten: Header `Deprecation`, im README streichen, in der nächsten Major-Version entfernen |
| i | **SEC-19** Jeder Klick schreibt `clicks.json` komplett neu, bei einer Flut wird es langsam | Messen (ab vs. 1000 Links); falls relevant: Klicks erst gepuffert in `clicks-YYYYMMDD.log` (append, `LOCK_EX`) schreiben und beim Admin-Aufruf/stündlich verdichten. `shortcut:`-Kommentar, falls aufgeschoben |

## Akzeptanzkriterien
- Je Teil ein Test in `tests/lib.test.php` (wo die Logik in `lib.php` liegt) oder ein `curl`-Nachweis in der Commit-Nachricht („Geprueft:“-Absatz).
- Die CI ist grün.
