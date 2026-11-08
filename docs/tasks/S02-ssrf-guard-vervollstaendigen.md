---
id: S02
titel: SSRF-Schutz vervollständigen – Linkcheck ohne Auto-Redirect, IPv6/AAAA, DNS-Rebinding, fehlende Netze
prio: P1 (Security, mittel)
aufwand: S–M (2–3 h)
quelle: docs/analysis/research-security.md SEC-03, SEC-04
dateien: [lib.php, admin.php, tests/lib.test.php]
abhängig_von: []
---

# S02 – SSRF-Guard

## Befunde
- **SEC-03 (mittel, Code verifiziert):** Der Toter-Link-Check `admin.php:847` setzt `CURLOPT_FOLLOWLOCATION => true, MAXREDIRS 4`. Ein öffentliches Ziel kann per
  302 auf `http://127.0.0.1:…`/`169.254.169.254` umleiten, und `host_is_public` wird für den Redirect nicht erneut geprüft. Der Titel-Fetch (`admin.php:803`) macht es richtig.
- **SEC-04 (niedrig):** `host_is_public` löst nur A-Records auf, AAAA fehlt; zwischen Prüfung und Abruf ist DNS-Rebinding möglich. Nicht gesperrt sind
  100.64.0.0/10 (CGNAT), 198.18.0.0/15 und 192.0.0.0/24.

## Umsetzung
1. `lib.php`: `host_is_public()` (oder neuer Helfer `resolve_public_ips(string $host): ?array`) löst A **und** AAAA auf (`dns_get_record($h, DNS_A|DNS_AAAA)`),
   **alle** Adressen müssen öffentlich sein. Zusätzlich die Bereiche 100.64/10, 198.18/15, 192.0.0/24, 0.0.0.0/8, ::ffff:0:0/96 (mapped) und fc00::/7, fe80::/10 sperren.
   `filter_var(..., FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)` reicht nicht allein, deshalb eine explizite CIDR-Liste.
2. **Gegen Rebinding** die geprüfte IP festpinnen: `CURLOPT_RESOLVE => ["$host:$port:$ip"]` für Titel-Fetch und Linkcheck.
3. Linkcheck: `CURLOPT_FOLLOWLOCATION => false`. Redirects manuell verfolgen (max. 4): `Location` lesen, relativ auflösen, Schema http/https prüfen,
   dann erneut `resolve_public_ips` + `CURLOPT_RESOLVE`. Mit `curl_multi` einfach „Redirect = lebendig“ werten (Status 3xx → ok) und **nicht** folgen;
   das ist die kleinste sichere Lösung.
   `shortcut: 3xx gilt als lebendig, Ziel hinter dem Redirect wird nicht geprüft – erweitern, falls tote Weiterleitungsketten auffallen`.
4. `CURLOPT_PROTOCOLS`/`CURLOPT_REDIR_PROTOCOLS` auf HTTP/HTTPS beschränken.

## Tests (`tests/lib.test.php`)
- `ip_is_public`-Tabelle: 127.0.0.1, 10.x, 172.16.x, 192.168.x, 169.254.169.254, 100.64.1.1, 198.18.0.1, 0.0.0.0, ::1, fe80::1, fc00::1, ::ffff:127.0.0.1 → false;
  1.1.1.1, 2606:4700::1111 → true.
- Manuell: Ziel `https://httpbin.org/redirect-to?url=http://127.0.0.1/` (oder ein lokaler Dummy, der 302 auf 127.0.0.1 liefert) → Linkcheck macht **keinen** Request an 127.0.0.1.
