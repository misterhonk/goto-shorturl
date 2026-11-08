# GOTO – UX-Recherche: Daten, Forschung, Empfehlungen

Stand: 2026-11-08 · Bezug: GOTO v1.3.3 (`main`, Commit 7955481)

**Legende für Empfehlungen**

- **[verifiziert]** – am Code geprüft (Datei/Zeile angegeben).
- **[vermutet]** – aus README/Screenshot abgeleitet, Code nicht im Detail geprüft.
- **Quellenqualität:** *Primär* = Standard/Forschung/Herstellerdoku; *Sekundär* = Vendor-Blog/Zusammenfassung. Viele QR-Statistiken stammen von QR-Anbietern (Interessenkonflikt) – entsprechend markiert.

---

## 1. QR-Code-Nutzung & Scanbarkeit

### Kennzahlen

| Befund | Zahl | Quelle (Qualität) |
|---|---|---|
| US-Konsumenten, die mind. 1×/Monat scannen | 70 % (n=1.000, März 2026) | Uniqode „State of QR Codes 2026“, zitiert u. a. in [qrcode-tiger.com](https://www.qrcode-tiger.com/qr-code-statistics-2022-q1) (Sekundär, Vendor) |
| Scannen täglich/mehrmals pro Woche | 47 % (n=1.548) | QR TIGER „QR Decoded 2026“, [qrcode-tiger.com](https://www.qrcode-tiger.com/qr-code-statistics-2022-q1) (Vendor) |
| Überspringen QR in Mails/Nachrichten · auf zufälligen Flyern/Postern | 53 % · 46 % | ebd. (Vendor) |
| Anstieg QR-Phishing Q1/2026 | +146 % | Microsoft, zitiert in [qrstealth](https://qrstealth-site.pages.dev/blog-qr-code-phishing-attacks-2026-how-to-stay-safe.html) (Sekundär) |
| QR-Phishing-Kampagnen YoY · Ziel Credential-Diebstahl | +331 % · 89,3 % | Cofense via [Keepnet](https://keepnetlabs.com/blog/qr-code-phishing-trends-in-depth-analysis-of-rising-quishing-statistics) (Security-Vendor) |
| Feldstudie Quishing: Hauptgrund zum Scannen war der Kontext/„Pretext“ | 52 % der Teilnehmenden | Sharevski et al., USEC 2024, [PDF](https://www.ndss-symposium.org/wp-content/uploads/usec2024-50-paper.pdf) (Primär) |
| Abbruch nach Sicht der URL-Vorschau bei legitimen/gekürzten Links · bei offensichtlich verdächtiger URL | 8 % (2/26) · 44 % (7/16) | ebd. (Primär) |

**Einordnung:** QR ist Alltag, aber die Skepsis steigt genau dort, wo GOTO eingesetzt wird (Print/Poster/Doku). Die USEC-Studie zeigt: Menschen entscheiden über den *Kontext um den Code* (Text, Absender, Gestaltung) – die URL-Vorschau der Kamera-App wirkt nur, wenn die Domain auffällig falsch aussieht. Eine **erkennbare, eigene Domain** im Code ist also das stärkste Vertrauenssignal, das GOTO liefern kann (iOS/Android zeigen vor dem Öffnen den Host an).

### Technische Druckregeln (ISO/IEC 18004 & Praxis)

- **Quiet Zone:** 4 Module Rand rundum (ISO/IEC 18004; Micro-QR 2). Praxisquellen: [uniqode](https://www.uniqode.com/blog/qr-code-best-practices/qr-code-printing-guideline), [qr-insights](https://www.qr-insights.com/blog/2026-03-03-qr-code-design-best-practices).
- **Fehlerkorrektur:** L≈7 %, M≈15 %, Q≈25 %, H≈30 % wiederherstellbar (Norm). Mit Logo: Q/H, Logo ≲ 30 % der Fläche – realistisch deutlich weniger (Logo + Finder-Pattern frei).
- **Mindestgröße:** ≈ 2 × 2 cm Nahbereich, 2,5 cm als sicheres Minimum; Faustregel **Scan-Distanz ÷ 10 = Kantenlänge** ([qr-code-generator.com](https://www.qr-code-generator.com/blog/minimum-qr-code-size/), [qr-insights Size Guide](https://www.qr-insights.com/blog/2026-02-24-qr-code-size-guide-minimum-dimensions); Sekundär). Mehr Module (lange URL) ⇒ größerer Code nötig – Kurzlinks sind hier ein echter Vorteil.
- **Kontrast:** dunkel auf hell; Empfehlungen schwanken zwischen ≥ 4:1 und WCAG-analog 4,5:1 (Sekundär, s. o.). **Invertierte Codes** (hell auf dunkel) erlaubt die Norm seit 2015 als „reflectance reversal“, viele ältere Scanner-Apps erkennen sie aber nicht – riskant für Print.
- **Druck:** Vektor (SVG/PDF) oder ≥ 300 dpi in Endgröße; **Testdruck + Scan mit mehreren Geräten** vor Auflage.
- **CTA-Rahmen („Scan me“):** Keine belastbare Studie gefunden, die die Wirkung isoliert misst. Die USEC-Daten (Pretext = Scan-Grund) stützen aber indirekt: *ein Satz, was hinter dem Code steckt, + sichtbare Kurz-URL* erhöht Vertrauen und bietet einen Fallback ohne Kamera.

### Empfehlungen für GOTO

1. **Kontrast- und Inversionswarnung im QR-Dialog.** Bei frei wählbaren Farben (`#qrFg`/`#qrBg`, admin.php:1663–1664) fehlt jede Prüfung – `grep contrast` in app.js/admin.php ohne Treffer. Luminanz-Kontrast berechnen, unter 4:1 Hinweis „schwer scanbar“, bei Vordergrund heller als Hintergrund Warnung „invertiert – ältere Scanner scheitern“. Bonus: Selbsttest per mitgeliefertem `jsqr.js` auf das gerenderte Canvas („✓ dekodierbar“). **[verifiziert]**
2. **Quiet Zone nicht unter 4 erlauben, ohne Hinweis.** Default ist 4 (admin.php:1660, gut), `min="0"` erlaubt aber 0. Bei < 4 Inline-Hinweis zeigen. **[verifiziert]**
3. **Logo erzwingt H – beibehalten.** `logoOn()` setzt ECL auf H (app.js:591–593) – entspricht Praxisempfehlung. Ergänzen: Hinweis zur maximalen Logogröße. **[verifiziert]**
4. **Druckgrößen-Rechner.** Neben „Modulgröße (px)“ eine Angabe „Mindestkantenlänge bei X m Abstand ≈ X·10 cm“ und die tatsächliche mm-Größe bei 300 dpi. Der Etikettenbogen nutzt 34–40 mm (goto.css:549/557) – über dem 2-cm-Minimum, gut. **[verifiziert]**
5. **Optionaler CTA-Rahmen beim Export**: Unterzeile mit Kurz-URL im Klartext (z. B. `misterhonk.de/goto/flyer`) direkt im SVG/PNG – Vertrauenssignal + Abtipp-Fallback. Der Etikettenbogen zeigt den Kurzlink bereits; der Einzel-Download nicht. **[vermutet]** (Einzel-SVG-Funktion `svg()` in app.js:663 nur kurz gesichtet.)
6. **Domain-Empfehlung in Doku/Onboarding:** GOTO auf einer vertrauten Marken-Domain betreiben (ist bei misterhonk.de gegeben); Pfad kurz halten, weil Länge die Modulzahl treibt.

---

## 2. Vertrauen in Kurzlinks

### Kennzahlen & Befunde

- **Branded vs. generisch:** Branded Short-Links erzielen laut Rebrandly-Analyse (> 1 Mrd. Klicks) bis zu **+34 % bis +39 % CTR** gegenüber generischen Kurzlinks ([ShortPen](https://shortpen.com/branded-short-url), [U2L](https://u2l.ai/blog/what-is-branded-link)). *Vendor-Zahl mit Interessenkonflikt; keine Peer-Review-Studie gefunden.*
- **URL-Lesekompetenz:** Albakry, Vaniea & Wolters, CHI 2020, „What is this URL's Destination?“ untersucht u. a., ob Nutzer erkennen, dass das Ziel eines Kurzlinks **nicht vorhersagbar** ist ([PDF](https://core.ac.uk/download/323051881.pdf), [ACM DOI 10.1145/3313831.3376168](https://doi.org/10.1145/3313831.3376168)). Kernbotschaft der Forschung: Nutzer verlassen sich auf den sichtbaren Domain-Teil; Kurzlinks entziehen ihnen genau diesen.
- **Vorschau wirkt begrenzt:** nur 8 % brechen bei plausibel aussehender URL-Vorschau ab (USEC 2024, s. o.). Eine Zwischenseite überzeugt also vor allem, wenn sie **das echte Ziel klar zeigt** und **nicht drängt**.

### Empfehlungen für GOTO

1. **Vorschauseite: Auto-Weiterleitung entschärfen.** Die Vorschau leitet per `<meta http-equiv="refresh" content="3;…">` nach 3 s weiter (index.php:302). Das ist (a) zu kurz, um die Ziel-Domain zu prüfen – der eigentliche Zweck –, und (b) ein WCAG-Problem: Zeitlimit ohne Abschalt-/Verlängerungsmöglichkeit (SC 2.2.1 *Timing Adjustable*, Level A; Failure [F41](https://www.w3.org/WAI/WCAG22/Techniques/failures/F41)). Vorschlag: Standard = **kein Auto-Redirect**, großer „Weiter zu *domain.tld*“-Button; optional pro Link „automatisch nach N s“ mit sichtbarem „Anhalten“-Knopf (JS, nonce-CSP-kompatibel). **[verifiziert]**
2. **Ziel-Domain hervorheben** (registrierbare Domain fett, Rest grau – Chrome-Muster) auf Vorschau- und Passwortseite. **[vermutet]** (Darstellung der Vorschauseite nicht im Detail geprüft.)
3. **Öffentliche „+“-Inspektion:** `goto/kürzel+` (Bitly-Konvention) zeigt die Vorschauseite für *jeden* Link, auch ohne Opt-in – ein billiges Vertrauenswerkzeug für skeptische Empfänger. Passwortgeschützte Links weiterhin ohne Ziel. **[vermutet]** – neue Funktion, Route in `index.php`/`.htaccess` nötig.
4. **Kürzel lesbar wählen lassen** (`flyer-2026` statt `x7Gq2a`): Custom-Kürzel sind schon möglich; im Formular als Standard-Tipp nennen, weil lesbare Pfade Vertrauen und Abtippbarkeit erhöhen. **[verifiziert]** (README „Wunsch-Kürzel“.)

---

## 3. Admin-/Dashboard-UX für Link-Management

### Erkenntnisse

- **Bestätigungsdialoge nur für Gefährliches & Unumkehrbares**, sonst Gewöhnungseffekt („automatisch Ja klicken“); Dialoge müssen **spezifisch** sein (welches Objekt, was passiert) – Jakob Nielsen, [NN/g „Confirmation Dialogs Can Prevent User Errors — If Not Overused“](https://www.nngroup.com/articles/confirmation-dialog/) (Primär).
- **Bulk-Aktionen:** „Alle auswählen“, kontextuelle Aktionsleiste, klares Feedback **mit Undo** – [NN/g Bulk Actions: 3 Design Guidelines](https://www.nngroup.com/videos/bulk-actions-design-guidelines/) (Primär).
- **Undo statt Dialog** für umkehrbare Aktionen (Toast mit „Rückgängig“) – Experten-Konsens, z. B. [LogRocket](https://blog.logrocket.com/?p=199540) (Sekundär).
- **Empty States:** Systemstatus kommunizieren, Lernhinweise geben, direkten Weg zur Kernaufgabe bieten – [NN/g, Kate Kaplan](https://www.nngroup.com/articles/empty-state-interface-design/) (Primär).
- **Mobile Tabellen:** Spalten müssen ohne Zoom lesbar sein; wenn horizontal gescrollt wird, muss das sichtbar sein (abgeschnittene Spalte/Pfeile) – [NN/g Mobile Tables](https://www.nngroup.com/articles/mobile-tables/) (Primär, 2017, Grundsätze weiter gültig).

### Ist-Zustand GOTO (am Code)

- Löschen eines Links → nativer `confirm()` (app.js:2–6, admin.php:594), obwohl gelöscht **in den Papierkorb** wandert (umkehrbar, admin.php:2032 „Wiederherstellen“). Gleiches für Bulk-Löschen (admin.php:1532). **[verifiziert]**
- Toasts existieren bereits mit `aria-live="polite"` (admin.php:422–423). **[verifiziert]**
- Empty State mit Plus-Icon vorhanden (admin.php:1567), „Keine Treffer“ bei Suche (admin.php:1612). **[verifiziert]**
- Mobile: ab ≤ 760 px wird die Tabelle zu Karten umgebaut (goto.css:486–518, u. a. `.target` umbrechend, Aktionen unten). Entspricht dem Card-Muster. **[verifiziert]**
- Tastatur-Shortcuts `/` und `n` (app.js:201–202). **[verifiziert]**
- Progressive Disclosure: „Weitere Optionen“ aufklappbar mit gemerktem Zustand (README). **[verifiziert via README]**

### Empfehlungen für GOTO

1. **Papierkorb-Löschen ohne `confirm()`, dafür Toast „„flyer“ in den Papierkorb · Rückgängig“.** Der Rückgängig-Button postet die bestehende Restore-Aktion (CSRF-Token im Toast). Dialoge bleiben nur für *endgültig* löschen, Papierkorb leeren, Import-Ersetzen, 2FA-Codes neu erzeugen, Token widerrufen. Spart einen Klick pro Löschung und folgt NN/g. **[verifiziert]**
2. **Bulk-Löschen-Toast mit Anzahl + Kürzeln** („3 Links: a, b, c“) und Bulk-Undo. NN/g fordert Spezifität bei Mehrfachaktionen. **[verifiziert]** (Bulk-Dialog ist generisch: „Markierte Links wirklich löschen?“)
3. **Empty State als Mini-Onboarding** für Self-Hosted: drei Schritte „Link anlegen → QR herunterladen → gedruckten QR später per *Scannen* umleiten“ – genau der Kern-Use-Case, der aktuell nur in README steht. Plus Link auf Diagnose (Ampel), falls Schreibrechte fehlen. **[vermutet]** (Inhalt des Empty State nur angerissen gesehen.)
4. **„QR-Status“-Spalte/Chip**: Markierung „gedruckt/im Umlauf“ pro Link (nur Flag im Datenmodell) + Warnung beim Löschen/Ablauf eines solchen Links („Dieser Code ist gedruckt – lieber umleiten?“). Hier ist ein Dialog *gerechtfertigt*, weil die Folge (toter Print-QR) teuer ist. **[vermutet]** – neues Feld in `lib.php`-Datenmodell.
5. **Command Palette (⌘K) – niedrige Priorität.** Mit `/`-Suche und `n` sind die häufigsten Wege abgedeckt; eine Palette lohnt erst bei vielen Bereichen (Diagnose, Tokens, Geräte, Papierkorb). Günstiger: `?` öffnet Shortcut-Übersicht (Entdeckbarkeit). **[verifiziert]**
6. **Inline-Editing**: Für Ziel-URL-Wechsel (Kernaufgabe bei gedruckten QRs) reicht die vorhandene Edit-Zeile; Inline-Edit lohnt nur für Titel. Nicht priorisieren. **[vermutet]**

---

## 4. Privacy-freundliche Analytics-Darstellung

### Erkenntnisse

- **Plausible** reduziert das Dashboard bewusst auf wenige, klar definierte Kennzahlen (Unique Visitors, Pageviews, Quellen, Ziele) und zählt ohne Cookies tagesweise – [Plausible Metrics Definitions](https://plausible.io/docs/metrics-definitions) (Primär).
- **Bots sind die Mehrheit:** 2024 erstmals **51 % automatisierter Traffic**, 37 % „bad bots“ – [Imperva 2025 Bad Bot Report](https://www.imperva.com/resources/resource-library/reports/2025-bad-bot-report/) (Primär, Security-Vendor). Plausible gibt an, Bots über User-Agent, ~32.000 Rechenzentrums-IP-Ranges und Verhaltensmuster zu filtern ([daily.dev-Repost des Plausible-Tests](https://app.daily.dev/posts/google-analytics-counts-bots-as-real-traffic-new-test--o72yo718n); Vendor-Selbsttest). Für Umami/Fathom keine belastbare Doku gefunden.
- **Speziell für Kurzlinks:** E-Mail-Sicherheitsgateways (Safe-Links-/URL-Rewriting-Scanner), Link-Checker und Browser-Prefetch „klicken“ Links automatisch – Kurzlink-Zähler werden dadurch systematisch überhöht (Branchenwissen; nicht durch eine hier zitierte Studie belegt).

### Ist-Zustand GOTO (am Code)

- Klicks werden nur für **Link-Preview-Crawler** ausgenommen (Regex facebookexternalhit|whatsapp|…|embedly, index.php:92–96). Allgemeine Crawler (Googlebot, bingbot), `curl`/`python-requests`, Monitoring, Safe-Links-Scanner und **HEAD-Requests** zählen mit – es gibt keine `REQUEST_METHOD`-Prüfung außer beim Passwort-POST (index.php:249), `count_click` wird bei jedem GET/HEAD aufgerufen (index.php:148). **[verifiziert]**
- Vorhanden: Kacheln gesamt/heute/7 Tage/Top-Link, Verlauf 14/30/90 Tage, Quellen-Aufschlüsselung via `gq`, CSV-Export (README). Das deckt die Plausible-Kernmetriken für Links gut ab. **[verifiziert via README]**

### Empfehlungen für GOTO

1. **Breitere Bot-Erkennung vor `count_click`** (Bot weiterleiten, aber nicht zählen): HEAD nicht zählen; `Sec-Purpose: prefetch`/`Purpose: prefetch` nicht zählen; UA-Regex um `bot|crawl|spider|slurp|curl|wget|python-requests|go-http-client|httpclient|headless|preview|scan|monitor|uptime` erweitern (Ansatz der `isbot`-Bibliothek, als einzelner Regex in `lib.php` ohne Abhängigkeit). Bleibt DSGVO-konform, weil UA nur geprüft, nicht gespeichert wird. **[verifiziert]** (Lücke am Code.)
2. **Optional „Bots“-Zähler separat** (nur eine Zahl pro Tag) – macht die Filterung *sichtbar* und erklärt Diskrepanzen zu anderen Tools („42 Aufrufe, davon 11 Bots ausgefiltert“). Transparenz als UX-Feature. **[vermutet]**
3. **„Seit Druck“-Kennzahl:** Für QR-Print ist die relevante Frage „Wird der Flyer gescannt?“. Aktivierungsdatum/„Aktiv ab“ existiert; eine Kachel „Klicks seit Aktiv-ab / seit Erstellung“ plus „letzter Klick vor X Tagen“ beantwortet sie direkt. Totes Material erkennt man an „letzter Klick vor 90 Tagen“. **[vermutet]** – nur Zähler nötig, kein Zeitstempel pro Klick (Tagesbucket genügt).
4. **Diagramm zugänglich machen:** zusätzlich zur Grafik eine Tabellen-/Textalternative (Summe, Max-Tag) bzw. `aria-label` am SVG. **[vermutet]** (Chart-Markup in app.js nicht geprüft.)

---

## 5. Barrierefreiheit (WCAG 2.2, EAA)

### Relevante Kriterien

- **2.4.11 Focus Not Obscured (Minimum), AA:** fokussiertes Element darf nicht vollständig von sticky Headern/Footern verdeckt werden; Lösung u. a. `scroll-padding`; Failure **F110** – [W3C Understanding 2.4.11](https://www.w3.org/WAI/WCAG22/Understanding/focus-not-obscured-minimum.html).
- **2.5.8 Target Size (Minimum), AA:** Zeigerziele ≥ **24 × 24 CSS-px** oder ausreichender Abstand (24-px-Kreis-Regel) – [W3C Understanding 2.5.8](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html).
- **3.3.7 Redundant Entry, A:** bereits eingegebene Infos im selben Prozess nicht erneut abfragen.
- **3.3.8 Accessible Authentication (Minimum), AA:** kein Merken/Abtippen als einziger Weg; **Einfügen und Autofill durch Passwortmanager müssen funktionieren**; TOTP-Felder dürfen nicht in Einzelziffern gesplittet sein, Einfügen muss gehen – [W3C Understanding 3.3.8](https://www.w3.org/WAI/WCAG22/Understanding/accessible-authentication-minimum.html).
- **European Accessibility Act:** gilt seit 28.06.2025 für bestimmte Produkte/Dienstleistungen gegenüber **Verbrauchern**; Kleinstunternehmen (< 10 MA, ≤ 2 Mio. €) sind bei Dienstleistungen ausgenommen – [EU-Kommission](https://commission.europa.eu/strategy-and-policy/policies/justice-and-fundamental-rights/disability/union-equality-strategy-rights-persons-disabilities-2021-2030/european-accessibility-act_en), [Richtlinie (EU) 2019/882](https://eur-lex.europa.eu/legal-content/EN/TXT/?uri=CELEX%3A32019L0882). Für ein Single-Admin-Tool ist das **Admin-UI** kaum betroffen; die **öffentlichen Seiten** (Vorschau, Passwort, 404/410) sieht aber jeder Endkunde – wer GOTO z. B. für einen Shop einsetzt, sollte diese AA-konform halten. Keine Rechtsberatung.

### Ist-Zustand & Empfehlungen

1. **Login-Formular passwortmanager-freundlich machen (3.3.8).** `<form … autocomplete="off">` und Passwortfeld **ohne** `autocomplete="current-password"`, kein Username-Feld (admin.php:1323–1325). Manche Manager speichern/füllen dann nicht zuverlässig. Fix: `autocomplete="off"` am Login entfernen, `autocomplete="current-password"` setzen, verstecktes `<input type="text" name="username" value="admin" autocomplete="username" hidden>` ergänzen (Chrome-/1Password-Empfehlung für Single-User-Logins). **[verifiziert]**
2. **2FA-Eingabe ist bereits konform:** ein einzelnes Feld, `autocomplete="one-time-code"`, Einfügen möglich (admin.php:1318, 1818). `inputmode="text"` beim Login ist richtig, weil Recovery-Codes alphanumerisch sind. Beibehalten. **[verifiziert]**
3. **`scroll-padding-top` für die sticky Topbar (2.4.11).** `.topbar{position:sticky}` (goto.css:63), aber kein `scroll-padding`/`scroll-margin` im CSS. Fix: `html{scroll-padding-top:5rem}` (Wert = Topbar-Höhe + Abstand). Toasts (falls fixiert) ebenfalls prüfen. **[verifiziert]**
4. **Zielgrößen messen (2.5.8).** `.btn--small` (Padding .42rem, 0.78rem Font) dürfte ≈ 30 px hoch sein – ok. Zeilen-Checkbox `input.rowchk` ist am Desktop **20 × 20 px** (goto.css:207; mobil 26 px) – nur über die Abstandsausnahme konform. Empfehlung: Desktop 24 px oder Klickfläche auf die ganze Zelle (`label`) erweitern. **[verifiziert]** (Größen aus CSS, nicht im Browser gemessen.)
5. **Vorschau-Redirect (2.2.1)** – siehe Abschnitt 2, Empfehlung 1. **[verifiziert]**
6. **Kontrast `--muted`:** `#6e6e73` auf `#ffffff` ≈ 5,1:1 (AA ok), auf `--bg #f5f5f7` ≈ 4,7:1 – knapp; bei 0,74-rem-Labels (`.field-label`, `.stat-label`) grenzwertig lesbar. Dunkelmodus `#98989d` auf `#1c1c1e` ≈ 5,9:1 ok. **[verifiziert]** (berechnet aus goto.css:5/25.)
7. **3.3.7 Redundant Entry:** Beim Bearbeiten werden Werte vorbefüllt; Passwortfelder bewusst leer („gesetzt – neues eingeben zum Ändern“) – das ist durch die Sicherheitsausnahme gedeckt. Kein Handlungsbedarf. **[verifiziert]**

---

## 6. Auth-UX: Passkeys, TOTP, „Angemeldet bleiben“

### Kennzahlen

- **FIDO Passkey Index (Okt. 2025):** Daten von Amazon, Google, Microsoft, PayPal, TikTok u. a.; im Mittel **93 % der Konten passkey-fähig**, **93 % Anmelde-Erfolgsquote** mit Passkeys – mehr als doppelt so hoch wie bei anderen Methoden – [FIDO Passkey Index](https://fidoalliance.org/passkey-index-2025/), [Report-PDF](https://fidoalliance.org/wp-content/uploads/2025/10/FIDO-Passkey-Index-October-2025.pdf), Zusammenfassung [Mobile ID World](https://www.mobileidworld.com/fido-alliance-reports-3-billion-passkeys-in-use-shows-93-authentication-success-rate/). *Zahlen der FIDO-Mitglieder selbst.*
- **Bekanntheit:** ~75 % (World Passkey Day 2025, n=1.389, 5 Länder) → **90 %** im „State of Passkeys 2026“; 48 % der Top-100-Websites bieten Passkeys an – [FIDO 2025 PDF](https://fidoalliance.org/wp-content/uploads/2025/04/World-Password-Day-2025-Final.pdf), [Descope zu FIDO 2026](https://www.descope.com/blog/post/2026-fido-report) (Sekundär).

### Einordnung für GOTO

- Single-Admin, Self-Hosted, oft auf Shared-Hosting per FTP: Passkeys (WebAuthn) sind serverseitig in reinem PHP ohne Abhängigkeit machbar (CBOR/COSE-Parsing, ES256-Verifikation via OpenSSL), aber spürbar Code. Der Nutzen ist real (phishing-resistent, kein Abtippen, 3.3.8-freundlich), passt zur Roadmap „7 Passkey/WebAuthn“ (MEMORY).
- TOTP + Recovery-Codes + Geräteverwaltung sind vorhanden und solide. `REMEMBER_SKIPS_2FA` default `true` (admin.php:25) – komfortabel; Remember-Token werden bei Passwort-/2FA-Änderung invalidiert (Fingerprint, admin.php:243–244, 291–295). **[verifiziert]**

### Empfehlungen

1. **Passkey als *zusätzliche* Anmeldeart** (nicht Ersatz): „Passkey hinzufügen“ in den Einstellungen; Login-Seite bietet Passkey-Button + Passwort-Fallback; Passkey ersetzt dann Passwort *und* TOTP (ist selbst 2-Faktor). Voraussetzung HTTPS – `is_https()` existiert. **[vermutet]** (Aufwand groß; Roadmap-Punkt.)
2. **Conditional UI** (`autocomplete="username webauthn"` am Username-Feld) – erst sinnvoll nach Empfehlung 5.1 (verstecktes Username-Feld). **[vermutet]**
3. **„Angemeldet bleiben“ transparent machen:** Label um Dauer ergänzen („30 Tage“ o. ä. aus `REMEMBER_TTL`) und Hinweis, ob 2FA dann übersprungen wird. Nutzer können das Risiko so einschätzen. **[verifiziert]** (Label ist nur „Angemeldet bleiben“, admin.php:1326.)

---

## Priorisierte Empfehlungsliste

Bewertung: Nutzen für den Haupt-Use-Case (Print-QR, später umleiten) × Aufwand. **V** = am Code verifiziert, **A** = Annahme.

| # | Maßnahme | Warum (Quelle) | Aufwand | Status |
|---|---|---|---|---|
| 1 | **Bot-/HEAD-/Prefetch-Filter vor `count_click`** (erweiterter UA-Regex in `lib.php`) | Zählerwerte sonst überhöht; 51 % Web-Traffic automatisiert (Imperva 2025) | S | V |
| 2 | **Login: `autocomplete="current-password"` + verstecktes `username`, `autocomplete="off"` entfernen** | WCAG 3.3.8 (Passwortmanager), Grundlage für Passkeys | XS | V |
| 3 | **Vorschauseite ohne 3-s-Zwang**: Button „Weiter zu domain“, Auto-Redirect optional mit Anhalten | Zweck = Ziel prüfen (USEC 2024); WCAG 2.2.1/F41 | S | V |
| 4 | **QR-Dialog: Kontrast-/Inversions-/Quiet-Zone-Warnung + jsQR-Selbsttest** | ISO 18004 (4 Module), Praxis ≥ 4:1, Invertierung riskant | S | V |
| 5 | **Löschen in Papierkorb: Undo-Toast statt `confirm()`**; Dialoge nur für Endgültiges | NN/g Confirmation Dialogs, Bulk Actions | S | V |
| 6 | **`html{scroll-padding-top}`** für sticky Topbar | WCAG 2.4.11 / F110 | XS | V |
| 7 | **Zeilen-Checkbox ≥ 24 px am Desktop** bzw. ganze Zelle klickbar | WCAG 2.5.8 | XS | V |
| 8 | **Kurz-URL als Klartext-Unterzeile im QR-Export** (optional CTA-Text) | Vertrauen durch erkennbare Domain + Abtipp-Fallback (USEC 2024, Branded-Link-Daten) | S | A |
| 9 | **„Gedruckt“-Flag + Warnung bei Löschen/Ablauf solcher Links** | teure Folge rechtfertigt Dialog (NN/g) | M | A |
| 10 | **Kennzahl „letzter Klick vor X Tagen“ / „seit Aktiv-ab“**, optional Bot-Zähler | Plausible-Prinzip: wenige, handlungsrelevante Metriken | M | A |
| 11 | **Empty State als 3-Schritt-Onboarding** (Anlegen → QR → später umleiten via Scan) | NN/g Empty States (Lernhinweise, direkte Pfade) | XS | A |
| 12 | **„Angemeldet bleiben (N Tage)“** im Label | Transparenz bei Sicherheitsabwägung | XS | V |
| 13 | **Druckgrößen-Hinweis** (Distanz ÷ 10, mm bei 300 dpi) | Praxisregeln Mindestgröße | S | A |
| 14 | **`goto/kürzel+` Inspektionsseite** | Kurzlink-Ziele sind sonst nicht vorhersagbar (Albakry et al., CHI 2020) | M | A |
| 15 | **Passkeys (WebAuthn) zusätzlich zu Passwort+TOTP** | FIDO: 93 % Erfolgsquote, phishing-resistent | L | A |
| 16 | `?`-Shortcut-Übersicht statt Command Palette | Entdeckbarkeit vorhandener Shortcuts | XS | V |

**Bewusst nicht empfohlen:** SQLite/DB (Projektprinzip), externe Analytics- oder Scan-Dienste (CSP `'self'`), Command Palette (Nutzen < Aufwand bei Single-Admin), Inline-Editing aller Felder.

## Grenzen dieser Recherche

- QR-Nutzungs- und Branded-Link-Zahlen stammen überwiegend von Anbietern mit Eigeninteresse; die Primärtexte von ISO/IEC 18004 (kostenpflichtig) und GS1 wurden nicht eingesehen – Norm-Werte (Quiet Zone 4, ECC-Prozente) sind allgemein dokumentiert.
- Keine Studie gefunden, die CTA-Rahmen („Scan me“) isoliert misst.
- Albakry et al. (CHI 2020): Fragestellungen bestätigt, Detailergebnisse nicht ausgewertet.
- Zielgrößen und Kontraste aus CSS berechnet, nicht im Browser gemessen; kein Screenreader-Test.
- EAA-Einordnung ist keine Rechtsberatung.
