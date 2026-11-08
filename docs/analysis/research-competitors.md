# Marktrecherche: URL-Shortener & QR-Generatoren (Stand Nov 2026)

Ziel: GOTO gegen Open-Source- und kommerzielle Alternativen einordnen, Community-Wünsche
sammeln und daraus eine priorisierte Gap-Liste ableiten.

**Methodik:** GitHub-API (Stars, letzter Push, Issues nach Reactions sortiert, READMEs,
Changelogs) am 2026-11-08, ergänzt um Websuche (Vergleichsseiten, Reddit-Mirror, Bewertungs-
portale). Reddit/HN direkt waren per Suche kaum erreichbar; wo nur Aggregatoren vorlagen, ist
das vermerkt. Kommerzielle Feature-Angaben stammen überwiegend aus Hersteller-/Konkurrenz-
blogs und sind als Tendenz zu lesen, nicht als verifizierte Datenblätter.

---

## 1. Vergleichstabelle Open Source

| Projekt | Stack / Speicher | Hosting-Anforderung | Stars · letzter Push | Analytics (Privacy) | QR | API | Multi-User |
|---|---|---|---|---|---|---|---|
| **GOTO** | PHP, **JSON-Dateien** | Shared Hosting, FTP, kein DB | – (eigenes Repo) | anonyme Tageszähler + Quelle (`gq`), keine IP/UA | **lokal, Logo, PNG/SVG, Etikettenbogen, Reverse-Scan** | REST, Token (rw/ro) | nein (Single-Admin, 2FA) |
| [YOURLS](https://github.com/YOURLS/YOURLS) | PHP, MySQL/MariaDB | Shared Hosting mit MySQL | 12,3k · 2026-11 (1.10.6, Aug 2026) | Klicks mit Referrer, Land, Zeit (IP gespeichert) | nur per Plugin | JSON/XML, Signatur-Token | nein (Plugins) |
| [Shlink](https://github.com/shlinkio/shlink) | PHP (RoadRunner/Swoole), SQLite/MySQL/PG/MSSQL | Docker/CLI, eigener Webserver-Prozess empfohlen | 5,4k · 2026-11 (5.1.x) | Visits mit GeoLite2, Bot-Flag, optional Matomo; IP anonymisiert | **seit 5.0 serverseitig entfernt**, nur noch im Web-Client | REST, API-Keys mit Rollen | über API-Keys/Dashboard |
| [Kutt](https://github.com/thedevs-network/kutt) | Node.js, SQLite (Default v3) / PG / MySQL, opt. Redis | Node ≥ 20 oder Docker | 11,2k · 2026-10 | private Stats (Browser, OS, Land, Referrer) | nein | REST | ja (Registrierung, Admin, OIDC) |
| [Dub](https://github.com/dubinc/dub.link) | Next.js, PlanetScale/MySQL, Upstash Redis, Tinybird | Vercel + mehrere SaaS | 25,1k · 2026-10 | volle Attribution (Geo, Device, Referrer, Conversions) | ja (Designer) | umfangreich, Webhooks, SDKs | Workspaces, SSO |
| [Sink](https://github.com/miantiao-me/Sink) | Nuxt 4, Cloudflare Workers, D1 + KV, Analytics Engine | Cloudflare-Account | 7,3k · 2026-10 | Echtzeit, Globus, Geo/Device | ja | ja, Site-Token | nein (Wunsch-Issue offen) |
| [Polr](https://github.com/cydrobolt/polr) | PHP/Lumen, MySQL | Shared Hosting mit MySQL | 5,1k · **2024-05 (faktisch tot)** | Referrer, Land | nein | ja | ja |
| [Lstu](https://framagit.org/fiat-tux/hat-softwares/lstu) | Perl/Mojolicious, SQLite/PG | VPS mit Perl | 24 (Framagit) · 2025-09 | Zähler | ja | ja | nein |
| [Chhoto URL](https://github.com/SinTan1729/chhoto-url) | Rust/Actix, SQLite | Docker | 1,0k · 2026-11 | **nur Zähler** (wie GOTO) | ja (einfach) | JSON-RPC-artig, API-Key | nein (bewusst) |
| [Reduced.to](https://github.com/origranot/reduced.to) | NestJS/Qwik, PG, Redis | Docker | 0,7k · **archiviert 2025** | Geo, Device | ja | ja | ja |
| [Slash](https://github.com/yourselfhosted/slash) | Go, SQLite/PG | Docker | 3,2k · 2026-08 | Traffic & Quellen | nein | gRPC/REST | ja (Team, Collections) |
| [Zipline](https://github.com/diced/zipline) | Next.js, PostgreSQL | Docker | 3,5k · 2026-11 | Views | nein | ja | ja (Invites, Quotas, Passkeys) |
| [Linkding](https://github.com/sissbruecker/linkding) | Python/Django, SQLite | Docker | 11,4k | – | – | – | Bookmark-Manager, **kein Shortener** – nicht relevant |
| [kimili/No-DB-URL-Shortener](https://github.com/kimili/No-DB-URL-Shortener) | PHP, Flatfiles | Shared Hosting | 49 · 2024-02 | keine | nein | nein | nein |
| [solariz/tinyPHPshortener](https://github.com/solariz/tinyPHPshortener) | PHP, Flatfile, Basic Auth | Shared Hosting | ~0 · 2025-06 | keine | nein | nein | nein |
| [mikecao/shorty](https://github.com/mikecao/shorty), [mathiasbynens/php-url-shortener](https://github.com/mathiasbynens/php-url-shortener) | PHP, MySQL/SQLite | Shared Hosting | 0,3k · 2020 | minimal | nein | minimal | nein |

**Befund DB-lose PHP-Shortener:** Es gibt nur Mini-Skripte (< 50 Stars, seit Jahren ohne
Pflege, kein Admin-UI, keine Sicherheit). GOTO hat in der Nische „file-only + vollwertiges
Admin“ **keinen ernsthaften Mitbewerber**.

Neue Kleinprojekte 2026 (Laravel/Workers, z. B. [LinkHop](https://github.com/navi-crwn/LinkHop),
[redrx](https://github.com/arumes31/redrx), [QRocodilla](https://github.com/QRocodilla/qrocodilla))
zeigen, was als „Feature-Paket“ gilt: QR-Studio, Device/Country-Routing, Link-in-Bio,
Phishing-Screening, MCP-Server für KI-Assistenten. Alle brauchen DB und meist Docker.

---

## 2. Kurzprofile

### YOURLS
- **Stärken:** Der Klassiker seit 2009, läuft auf Shared Hosting, 200+ Plugins, Bookmarklet,
  sehr stabil; weiterhin aktiv (1.10.x 2026).
- **Schwächen:** MySQL-Pflicht, altbackenes Admin-UI, keine eingebaute Geo-/QR-/Passwort-
  Funktion, kein CSP ([#2134](https://github.com/YOURLS/YOURLS/issues/2134) offen),
  User-Management seit Jahren Wunsch ([#1255](https://github.com/YOURLS/YOURLS/issues/1255)).
  Öffentliche Instanzen ziehen Spam an – die Doku warnt selbst
  ([docs.yourls.org/…/abuse](https://docs.yourls.org/guide/troubleshooting/abuse)).
- **Relevanz für GOTO:** Hauptkonkurrent bei Shared Hosting. Migrationspfad YOURLS → GOTO
  wäre ein Akquise-Hebel.

### Shlink
- **Stärken:** API-first, Multi-Domain, Tags, Redirect-Regeln (Device, Sprache, Query-Param,
  **Geolocation**), Bot-Erkennung in Visits, GeoLite2, Matomo-Anbindung, Import aus
  YOURLS/Bitly/Kutt, Link-Health-Check-Diskussion ([#334](https://github.com/shlinkio/shlink/issues/334)).
- **Schwächen:** Betrieb erfordert Docker/CLI bzw. eigenen App-Server; „klassischer
  Webserver“ ist mühsam ([#806](https://github.com/shlinkio/shlink/issues/806)). Admin-UI ist
  ein separates Projekt. **QR-Generierung wurde in 5.0 serverseitig entfernt**
  ([#2408](https://github.com/shlinkio/shlink/issues/2408), [#2514](https://github.com/shlinkio/shlink/issues/2514)) –
  Begründung u. a.: fremde Parteien konnten über die öffentliche QR-URL Codes mit fremdem
  Branding erzeugen. Bestätigt GOTOs Ansatz „QR rein client-seitig im Admin“.

### Kutt
- **Stärken:** Modernes UI, v3 (2025) mit SQLite-Default und „zero config“, OIDC, Passwort,
  Ablauf, Custom Domains, Themes.
- **Schwächen:** Node-Prozess nötig (nicht Shared-Hosting-tauglich), keine QR-Codes,
  historisch Google-Abhängigkeiten (reCAPTCHA/GA) – meistgewünschter Fix
  ([#373](https://github.com/thedevs-network/kutt/issues/373)), Docker-Images hinken CVEs
  hinterher ([#1059](https://github.com/thedevs-network/kutt/issues/1059)).

### Dub
- **Stärken:** Funktional Marktführer im OSS-Bereich: Attribution/Conversions, Deep Links,
  Device/Geo-Targeting, QR-Designer, Tags, Ordner, Webhooks, Workspaces, Partner-Programme.
- **Schwächen:** Faktisch eine SaaS mit offenem Code: braucht Vercel, PlanetScale, Upstash,
  Tinybird, Resend, Stripe. Das meistgevotete Issue ist Self-Hosting per Docker
  ([#25](https://github.com/dubinc/dub.link/issues/25), 103 Reactions; [#378](https://github.com/dubinc/dub.link/issues/378),
  [#598](https://github.com/dubinc/dub.link/issues/598) „externe Dienste eliminieren“).
  Lizenz teils proprietär (EE).

### Sink
- **Stärken:** Serverlos auf Cloudflare (kostenlos), Echtzeit-Analytics, Device/Country-
  Routing, Passwort, Ablauf, OG-Anpassung, QR, JSON-Import/CSV-Export, KI-Slugs. Mit
  [Slite](https://github.com/miantiao-me/Slite) gibt es eine Node/Docker-Variante.
- **Schwächen:** Cloudflare-Lock-in (US-Anbieter, DSGVO-Diskussion), Single-Token-Auth
  (Multi-Token-Wunsch [#104](https://github.com/miantiao-me/Sink/issues/104) offen).

### Chhoto URL
- **Stärken:** Philosophisch GOTOs engster Verwandter: minimal, nur Hit-Zähler, keine
  Cookies, „Bloat that will not be implemented“ (Tracking, User-Management). Ablauf, Notizen,
  QR, OIDC, Public Mode mit Zwangsablauf.
- **Schwächen:** Docker/Rust-Binary nötig, sehr einfache Auth (kein 2FA), kein Gruppen-/
  Kampagnen-/Druck-Feature.

### Slash
- Go-Links („s/name“) für Teams mit Browser-Extension und Collections. Anderer Use-Case
  (interne Kurzbefehle). Wünsche: kein `/s/`-Präfix ([#5](https://github.com/yourselfhosted/slash/issues/5),
  19 Reactions), **PWA + Share-Target** ([#31](https://github.com/yourselfhosted/slash/issues/31)),
  Safari-Extension.

### Zipline
- ShareX-/Datei-Upload-Server mit Shortener als Beifunktion (Postgres, Passkeys, Webhooks).
  Nur am Rand Konkurrenz.

### Polr, Reduced.to, Lstu
- Polr seit 2024 ohne Pushes (216 offene Issues), Reduced.to 2025 archiviert, Lstu (Perl)
  kaum aktiv. Zeigen: **Pflegebarkeit/Langlebigkeit ist selbst ein Feature** – Nutzer sitzen
  mit toten Projekten fest.

### DB-lose PHP-Skripte
- kimili/No-DB-URL-Shortener, robsd/php-*-url-shortener, solariz/tinyPHPshortener: Ein-Datei-
  Skripte ohne Admin, Statistik, QR, Sicherheitsheader. Keine Konkurrenz, aber Beleg für
  Nachfrage nach „ohne Datenbank“.

---

## 3. Kommerzielle Anbieter – „Table Stakes“ 2025/26

Quellen (Tendenz, überwiegend Herstellerblogs): [LinkDrip-Vergleich](https://www.linkdrip.com/blog/bitly-vs-rebrandly-vs-short-io-vs-linkdrip-which-link-platform-actually-fits-modern-teams-in-2026),
[Cutt.ly Top 12](https://cutt.ly/resources/blog/top-12-best-url-shorteners-2026),
[Toolradar Short.io vs Rebrandly](https://toolradar.com/compare/short-io-vs-rebrandly),
[QR TIGER (Capterra)](https://www.capterra.com/p/10041460/QR-TIGER/).

| Feature | Bitly | Rebrandly | Short.io | QR TIGER / QR-Generator-Pro | Gilt als Standard? | GOTO |
|---|---|---|---|---|---|---|
| Branded/Custom Domain | ✓ | ✓ (Kern) | ✓ | ✓ | **ja** | implizit (eigene Domain), kein Multi-Domain |
| Dynamische QR-Codes | ✓ | ✓ | ✓ | ✓ (Kern) | **ja** | ✓ (jeder Link = dynamischer QR) |
| QR-Design (Farben, Logo) | ✓ | ✓ | ✓ | ✓ (Frames, Muster) | **ja** | ✓ Farben/Logo; keine Muster/Frames |
| Bulk-Erstellung (CSV) | ✓ | ✓ | ✓ | ✓ (bis 3.000/Batch) | **ja** | ✓ CSV-Import |
| UTM-Builder | ✓ | ✓ | ✓ | – | ja | teilweise (Quelle `gq`, Param-Durchreichung) |
| Klick-Analytics Geo/Device/Referrer | ✓ | ✓ | ✓ | ✓ | **ja** | bewusst nein (nur Zähler) |
| Geo-/Device-Targeting | Premium | ✓ | ✓ | ✓ | ja (Mittelklasse) | nein (nur Rotation) |
| Deep Links (App) | ✓ | ✓ | ✓ | – | ja (Marketing) | nein |
| Link-in-Bio-Seite | ✓ | teils | ✓ | ✓ | ja (B2C) | nein |
| Retargeting-Pixel | – | ✓ | ✓ | ✓ | Nische | nein (widerspricht DSGVO-Ansatz) |
| Team-Workspaces/Rollen | ✓ | ✓ | ✓ | ✓ | **ja** | nein |
| Webhooks / Zapier | ✓ | ✓ | ✓ | ✓ | ja | nein |
| Browser-Extension / Bookmarklet | ✓ | ✓ | ✓ | – | ja | nein |
| Passwort / Ablauf / Scan-Limit | teils | ✓ | ✓ | ✓ | ja | ✓ (ohne Scan-Limit, bewusst) |
| API | ✓ | ✓ | ✓ | ✓ | **ja** | ✓ |
| Phishing-/Malware-Schutz | ✓ | ✓ | ✓ | – | ja (öffentl. Dienste) | n/a (kein öffentliches Formular) |

**Großes Ärgernis im kommerziellen Markt:** Dynamische QR-Codes werden nach Testphase oder
ausgelaufenem Abo deaktiviert – gedruckte Flyer/Poster sind dann tot
([Trustpilot qr-code-generator.com](https://www.trustpilot.com/review/www.qr-code-generator.com?page=9),
[North Penn Now, Apr 2026](https://northpennnow.com/news/2026/apr/02/your-qr-code-stops-working-the-moment-you-stop-paying/),
[„Subscription Trap“, Mär 2026](https://stocks.observer-reporter.com/observerreporter/article/businesnewswire-2026-3-28-the-subscription-trap-how-qr-code-platforms-weaponize-your-printed-materials),
[Uniqode-Erklärung](https://www.uniqode.com/blog/qr-code-basics/qr-codes-expiry)). Das ist
GOTOs stärkstes Verkaufsargument: **dynamische QR-Codes auf eigener Domain, ohne Abo, ohne
Ablauf durch Dritte.**

---

## 4. Community-Wünsche & Beschwerden

| Thema | Beleg | Bedeutung für GOTO |
|---|---|---|
| **Setup-Schmerz / Self-Hosting ohne SaaS** | Dub [#25](https://github.com/dubinc/dub.link/issues/25) (103 👍), [#378](https://github.com/dubinc/dub.link/issues/378), [#598](https://github.com/dubinc/dub.link/issues/598), [#2870](https://github.com/dubinc/dub.link/issues/2870); Shlink „klassischer Webserver“ [#806](https://github.com/shlinkio/shlink/issues/806); Kutt-Kritik „Node + PG + Redis“ ([selfhosting.sh](https://selfhosting.sh/best/url-shorteners/)) | GOTOs FTP-Upload ist Alleinstellung → prominent vermarkten |
| **Keine externen/Google-Dienste** | Kutt [#373](https://github.com/thedevs-network/kutt/issues/373) (15 👍), [#235](https://github.com/thedevs-network/kutt/issues/235) | GOTO erfüllt das bereits (kein Laufzeit-Dienst) |
| **Bot-/Fake-Klicks verfälschen Zahlen** | Dub [#2309](https://github.com/dubinc/dub.link/issues/2309) (X sendet 1×GET + 3×HEAD je geteiltem Link), Shlink [#1613](https://github.com/shlinkio/shlink/issues/1613) Bots aus Zählern ausschließen, Sink [#313](https://github.com/miantiao-me/Sink/issues/313) | **GOTO zählt HEAD-Requests und generische Crawler/Link-Scanner (Googlebot, Outlook SafeLinks, curl) mit** – nur Social-Preview-Bots sind ausgenommen |
| **SSO/OIDC** | Kutt [#809](https://github.com/thedevs-network/kutt/issues/809) (12 👍), [#367](https://github.com/thedevs-network/kutt/issues/367); Shlink [#1983](https://github.com/shlinkio/shlink/issues/1983); Chhoto [#145](https://github.com/SinTan1729/chhoto-url/issues/145) | Für Single-Admin auf Shared Hosting geringe Relevanz |
| **Passwortgeschützte Links** | Sink [#37](https://github.com/miantiao-me/Sink/issues/37) (meistgewünscht) | GOTO hat es ✓ |
| **Multi-User / mehrere Tokens** | YOURLS [#1255](https://github.com/YOURLS/YOURLS/issues/1255), Sink [#104](https://github.com/miantiao-me/Sink/issues/104) | GOTO hat mehrere Tokens ✓; Multi-User bewusst offen |
| **Import/Migration von anderen Plattformen** | Shlink [#835](https://github.com/shlinkio/shlink/issues/835), [#1242](https://github.com/shlinkio/shlink/issues/1242) (YOURLS-Import) | GOTO: nur eigenes JSON/CSV – YOURLS-Import fehlt |
| **Link-Health-Check** | Shlink [#334](https://github.com/shlinkio/shlink/issues/334) | GOTO hat Toter-Link-Prüfung ✓ |
| **Device-/Plattform-Routing, Deep Links** | Dub [#27](https://github.com/dubinc/dub.link/issues/27) (22 👍), Sink [#184](https://github.com/miantiao-me/Sink/issues/184), [#306](https://github.com/miantiao-me/Sink/issues/306) | Passt (UA-basiert, kein Dienst nötig) – Could |
| **Tags/Ordner** | Dub [#295](https://github.com/dubinc/dub.link/issues/295), Sink [#194](https://github.com/miantiao-me/Sink/issues/194) | GOTO hat Gruppen; Tags bereits als Roadmap-Punkt 8 vorgemerkt |
| **Editierbare OG-Vorschau** | Shlink [#848](https://github.com/shlinkio/shlink/issues/848), Sink-Feature „Social Preview“ | GOTO nutzt Titel; Beschreibung/Bild je Link fehlt |
| **QR-Standard-Design** | Shlink [#1149](https://github.com/shlinkio/shlink/issues/1149) | GOTO hat es ✓ (Voreinstellungen) |
| **Klickzähler zurücksetzen** | Shlink [#1148](https://github.com/shlinkio/shlink/issues/1148) | prüfen, ob GOTO das anbietet |
| **PWA / Share-Target / Mobile-Admin** | Slash [#31](https://github.com/yourselfhosted/slash/issues/31), Chhoto bewirbt „mobile friendly UI“ | GOTO ist responsiv; PWA-Share-Target fehlt |
| **CSP / Sicherheitshärtung** | YOURLS [#2134](https://github.com/YOURLS/YOURLS/issues/2134) (offen seit Jahren) | GOTO hat strenge nonce-CSP ✓ |
| **Spam bei öffentlichen Instanzen** | [YOURLS-Doku Abuse](https://docs.yourls.org/guide/troubleshooting/abuse), [LowEndSpirit: ~50 % Spam trotz Captcha](https://lowendspirit.com/discussion/comment/40060) | GOTO hat kein öffentliches Formular → Problem existiert nicht (bewusst so lassen) |
| **Langlebigkeit/Pflege, CVE-Rebuilds** | Shlink [#2678](https://github.com/shlinkio/shlink/issues/2678) (46 👍, PHP-CVE → Docker-Rebuild), Kutt [#1059](https://github.com/thedevs-network/kutt/issues/1059); Polr/Reduced.to tot | GOTO: kein Image, Hoster patcht PHP → Vorteil |
| **Themes/Branding** | YOURLS-Poll [#2915](https://github.com/YOURLS/YOURLS/issues/2915), Kutt [#347](https://github.com/thedevs-network/kutt/issues/347), Chhoto [#139](https://github.com/SinTan1729/chhoto-url/issues/139) | eigene Akzentfarbe/Logo für Besucherseiten – Could |

Reddit r/selfhosted (Mirror): [Flink-Vorstellung Feb 2025](https://redlib.hbubli.cc/r/selfhosted/comments/1inkovh/selfhostable_url_shortener_with_qr_codes_embeds) –
Nachfrage nach „QR + Metadaten + Metriken“, Gegenstimme: eigener Shortener lohnt nur mit
kurzer eigener Domain. Aggregierte HN-Stimmen: YOURLS als „läuft seit Jahren“-Lösung, Shlink
für API-Nutzer ([Toolradar](https://toolradar.com/compare/yourls-vs-shlink),
[selfhosting.sh](https://selfhosting.sh/best/url-shorteners/)). Direkte HN-Threads wurden nicht
gefunden.

---

## 5. Gap-Analyse

### 5.1 Alleinstellungsmerkmale von GOTO

1. **Einziger vollwertiger Shortener ohne Datenbank** – FTP-Upload auf Shared Hosting.
   YOURLS/Polr brauchen MySQL, alle anderen Docker/Node/Cloud.
2. **Print-QR-Workflow komplett:** Etikettenbogen, druckscharfe SVGs, Logo, Design-Presets,
   „Aktiv ab“ zum Vorab-Drucken, Rotation/Ersatz-URL hinter gedrucktem Code. So
   druckorientiert ist kein OSS-Projekt.
3. **Reverse-QR-Scan** (gedruckten Code fotografieren → Eintrag bearbeiten) – bei keinem
   Mitbewerber gefunden.
4. **QR rein client-seitig** – Shlink hat genau deshalb seine Server-QRs entfernt; GOTO war
   da schon.
5. **DSGVO by design:** nur Zähler, keine IP/UA/Referrer, kein Laufzeit-Dienst, strenge
   nonce-CSP, Referrer-Policy no-referrer. Nur Chhoto ist ähnlich sparsam (aber ohne
   Kampagnen-Zählung).
6. **Quellen-Tracking ohne Personenbezug** (`gq`) – Kampagnenauswertung ohne Cookies/IP.
7. **Sicherheitsniveau für Single-Admin:** TOTP + Recovery-Codes, Geräteverwaltung,
   Audit-Log, read-only-Tokens – mehr als YOURLS/Chhoto/Sink.
8. **Kein Abo-Risiko für gedruckte QR-Codes** – direkte Antwort auf die meistgenannte
   Beschwerde im kommerziellen Markt.

### 5.2 Priorisierte Lücken (MoSCoW)

Bewertung: **Nutzen** für Einzelperson/kleines Team, Shared Hosting, Print-QR, DSGVO ·
**Passung** zu file-only / kein externer Dienst.

#### Must
| Lücke | Wer hat es | Nutzen | Passung | Begründung |
|---|---|---|---|---|
| **Bot-/HEAD-Filter beim Zählen** (HEAD nicht zählen; generische Crawler, Link-Scanner wie Outlook SafeLinks/Proofpoint, `curl`/`python-requests`, Prefetch-Header ignorieren) | Shlink (Bot-Flag), Dub, Bitly | hoch – Zählerqualität ist GOTOs einzige Statistik | voll (reine UA/Methoden-Prüfung, kein Dienst) | Belegt durch Dub #2309, Shlink #1613. Derzeit zählt `index.php` jeden Nicht-Preview-Request inkl. HEAD. |
| **YOURLS-Import** (SQL-Dump/CSV/API-Export → GOTO-JSON) | Shlink (Import aus YOURLS, Bitly, Kutt) | hoch – YOURLS ist die Zielgruppe mit gleichem Hosting | voll (einmaliger Import im Admin) | Wechselhürde senken; Shlink #835/#1242 zeigen Nachfrage. Optional Shlink/Kutt-CSV. |

#### Should
| Lücke | Wer hat es | Nutzen | Passung | Begründung |
|---|---|---|---|---|
| **Device-/OS-Routing** (iOS → App Store, Android → Play Store, sonst Web) | Shlink, Sink, Dub, Short.io | mittel-hoch für Print-QR (App-Downloads, Menüs) | voll (UA-Regel, wie Rotation im Datenmodell) | Häufiger Wunsch (Dub #27, Sink #184). Geo-Routing dagegen bräuchte GeoIP-DB → Won't. |
| **UTM-Builder im Link-/QR-Dialog** | Bitly, Rebrandly, Short.io | mittel | voll (nur Client-UI) | Baut auf vorhandener Param-Durchreichung + `gq` auf; Standard im Markt. |
| **OG-Vorschau je Link editierbar** (Beschreibung, optional eigenes Bild) | Sink, Dub, Shlink-Wunsch #848 | mittel | voll (Felder im JSON, Bild lokal) | GOTO hat schon Bot-OG-Seite; nur Felder fehlen. |
| **Bookmarklet / PWA mit Share-Target** (Link vom Handy aus „Teilen“ kürzen) | YOURLS (Bookmarklet), Kutt/Slash (Extension), Slash-Wunsch #31 | mittel – Mobile-Admin | voll (Manifest + JS, kein Dienst) | Billig, verbessert Alltagsnutzung deutlich. |
| **Tags** zusätzlich zu Gruppen | Dub, Shlink, Slash | mittel | voll | Bereits als Roadmap-Punkt 8 vorgemerkt; Dub #295, Sink #194. |

#### Could
| Lücke | Wer hat es | Nutzen | Passung | Begründung |
|---|---|---|---|---|
| **Webhooks** (bei Anlage/Änderung) | Dub, Zipline, Short.io | gering-mittel | eingeschränkt (ausgehender Request = Laufzeit-Kontakt, nur opt-in im Admin, nie im Redirect-Pfad) | Für Automationen (n8n). |
| **Multi-Domain** (mehrere Domains auf eine Instanz) | Shlink, Kutt, Dub | gering (Shared Hosting: je Domain eigener Ordner) | mittel | Workaround: mehrere Installationen. |
| **QR-Muster/Frames/„Scan me“-Rahmen** | QR TIGER, Dub | mittel für Print | voll (Client) | Optischer Mehrwert, aber Scan-Robustheit testen. |
| **Branding der Besucherseiten** (Akzentfarbe/Logo) | Kutt, YOURLS-Wunsch | gering-mittel | voll | Kutt #347, Chhoto #139. |
| **Klickzähler je Link zurücksetzen** (falls nicht vorhanden) | Shlink-Wunsch #1148 | gering | voll | Nach Testdrucken nützlich. |
| **Link-in-Bio-Seite** (eine Gruppe als öffentliche Linkliste) | Bitly, Short.io, Dub | gering-mittel | voll (statische Seite aus Gruppe) | Kommerzieller Standard, für Vereine/Künstler nett; Scope-Gefahr. |
| **Zweiter Admin / einfache Rollen** | Kutt, Dub, Zipline | gering (Zielgruppe Single-Admin) | mittel (Auth-Datei erweitern) | Erst bei konkreter Nachfrage. |
| **Passkey/WebAuthn** | Zipline | gering-mittel | voll | Bereits Roadmap-Punkt 7. |

#### Won't (bewusst nicht)
| Lücke | Begründung |
|---|---|
| Geo-Analytics / Geo-Targeting | Erfordert GeoIP-DB (MaxMind-Lizenz/Download) und IP-Verarbeitung – widerspricht DSGVO-Ansatz und „kein externer Dienst“. |
| Detail-Analytics (Referrer, UA, Zeitstempel je Klick) | Kern des Privacy-Versprechens; „nur Zähler“ ist Feature (vgl. Chhoto). |
| Retargeting-Pixel, Conversion-Tracking | Dritt-Tracking, Cookie-Banner-Pflicht. |
| App-Deep-Links/Deferred Deep Links | Braucht App-Integration/SDKs; Device-Routing deckt 90 % ab. |
| Öffentliches Kürz-Formular / Anonym-Links | Spam-Magnet (YOURLS-Doku, LowEndSpirit); GOTO ist Admin-Tool. |
| OIDC/SSO | Single-Admin auf Shared Hosting; TOTP genügt. Ggf. später bei Multi-User. |
| KI-Slugs, MCP-Server | Externer Dienst bzw. Hype; API reicht für Integrationen. |
| SQLite/DB, Max-Klick-/Selbstzerstör-Links | Laut Projektvorgabe ausgeschlossen. |
| Phishing-/Malware-Screening | Nur bei öffentlicher Eingabe nötig; externe Listen = Laufzeitdienst. |

---

## 6. Fazit

GOTO besetzt eine Nische, die sonst niemand bedient: **datenbanklos, Shared Hosting,
druckorientierte dynamische QR-Codes, Privacy by design.** Die Konkurrenz konkurriert über
Analytics-Tiefe und Team-Funktionen, die GOTO bewusst nicht will. Die wertvollsten Lücken
sind klein und philosophiekonform: **saubere Bot-/HEAD-Filterung, YOURLS-Import,
Device-Routing, UTM-Builder, OG-Felder, Bookmarklet/PWA.** Für die Außendarstellung sollte
„Ihre QR-Codes sterben nicht, wenn ein Abo ausläuft“ und „FTP-Upload statt Docker“ im
Vordergrund stehen.
