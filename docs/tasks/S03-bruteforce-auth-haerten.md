---
id: S03
titel: Brute-Force-Schutz und Auth härten – IPv6-Präfix, globale Bremse, TOTP-Replay, Passwort-Prüfungen limitieren
prio: P1 (Security, mittel)
aufwand: M (3–4 h)
quelle: docs/analysis/research-security.md SEC-05, SEC-06, SEC-07, SEC-08
dateien: [lib.php (attempt_begin/clear_fails), admin.php (Login, TOTP, change_password, totp_disable), index.php (Link-Passwort), config.php, README.md, tests/lib.test.php]
abhängig_von: []
---

# S03 – Brute-Force- und Auth-Härtung

## Befunde
- **SEC-05 (mittel):** Die Fehlversuche zählen je exakter IP. Mit IPv6 hat ein Angreifer ein ganzes /64 (2^64 Adressen), also praktisch unbegrenzte Versuche.
  Über 2000 Einträgen wirft `lib.php:345` alle **nicht gesperrten** Zähler weg, was die Bremse zusätzlich aushebelt.
- **SEC-06 (niedrig, vermutet):** Hinter einem Reverse-Proxy oder CDN ist `REMOTE_ADDR` für alle gleich. Ein Angreifer sperrt dann jeden aus (DoS).
- **SEC-07 (niedrig):** Ein TOTP-Code ist im ±30-s-Fenster mehrfach verwendbar.
- **SEC-08 (niedrig):** `change_password` und `totp_disable` prüfen das aktuelle Passwort ohne Rate-Limit (eine gekaperte Session kann es durchprobieren).

## Umsetzung
1. **Schlüssel für Versuche**: IPv4 → /32, IPv6 → /64 (`inet_pton`, erste 8 Bytes). Helfer `client_bucket(): string` in `lib.php`, überall statt der Roh-IP nutzen.
2. **Globale Bremse** zusätzlich zur IP-Bremse: Bei mehr als N Fehlversuchen (z. B. 50) in 15 min über **alle** Quellen gilt für jeden Login eine Pflichtpause
   (`sleep(1)` bzw. Sperre 60 s mit Meldung). Admin-Login und Link-Passwörter haben getrennte Zähler.
3. **Aufräumen**: Statt „über 2000 alles Nicht-Gesperrte löschen“ abgelaufene Einträge (Fenster vorbei) löschen; bei Überlauf die **ältesten** verwerfen, aktive Zähler bleiben.
4. **Proxy**: neue Config `trusted_proxies` (Liste von IPs/CIDR, Standard leer). Nur wenn `REMOTE_ADDR` darin liegt, wird `X-Forwarded-For` (letzter nicht vertrauenswürdiger Eintrag) verwendet.
   Dieselbe Regel gilt für `X-Forwarded-Proto` in `is_https()` (siehe S04, SEC-12).
5. **TOTP-Replay**: In `.ht_auth.json` `totp_last` = zuletzt akzeptierter Zeitschritt speichern; Codes mit Zeitschritt ≤ `totp_last` ablehnen.
6. `change_password` / `totp_disable`: dieselbe `attempt_begin()`-Bremse wie beim Login (eigener Schlüssel `pwchange:<bucket>`).
7. Die bekannte Ungenauigkeit „parallele Versuche → falsche Meldung ‚zu viele Fehlversuche‘“ (Memory) dabei mitprüfen.

## Tests
- `lib.test.php`: `client_bucket` für `2001:db8::1` und `2001:db8::ffff` → gleicher Bucket; `2001:db8:0:1::1` → anderer.
- TOTP: derselbe Code zweimal hintereinander → zweiter abgelehnt (RFC-Vektor mit fester Zeit).
- Manuell (Docker): 9 falsche Link-Passwörter → Sperre; danach `.ht_attempts.json` aufräumen. **Nicht** das Admin-Login sperren, während andere testen.
