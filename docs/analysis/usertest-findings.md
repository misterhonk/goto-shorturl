# User-Test GOTO 1.3.3 – lokal, 2026-11-08

Getestet im Docker-Container (`http://localhost:8088/goto/`, php:8.2-apache), Desktop 1280×860 und
Mobil 375×812, im Browser-Pane plus `curl` für die Besucherseite. Screenshots: `screenshots/`.

## Szenarien

| # | Szenario (Persona) | Ergebnis |
|---|---|---|
| S1 | Login als Admin | ok |
| S2 | „Schnell einen Link kürzen": URL aus der Zwischenablage einfügen, Kurzlink kopieren | **holprig** (UT-01, UT-02, UT-03) |
| S3 | „QR für einen Flyer drucken": QR-Dialog, Größe für Druck wählen, herunterladen | **holprig** (UT-05, UT-06) |
| S4 | „Ziel hinter einem gedruckten QR ändern": Link bearbeiten | ok, aber Kontextverlust (UT-04) und Risiko (UT-07) |
| S5 | Besucher scannt QR / tippt Kurzlink ab | **Fehler** bei Großschreibung (UT-08) |
| S6 | Besucher: Passwort-Link, Vorschau-Seite, 404 | ok, aber wenig Vertrauen/Hilfe (UT-09, UT-10) |
| S7 | Admin am Handy | sehr lange Wege (UT-11) |
| S8 | Statistik ablesen | Testaufrufe (curl) werden mitgezählt (UT-12) |

Nicht getestet: Etikettenbogen-Druck, Reverse-QR-Scan mit Kamera, Import/Export, 2FA-Einrichtung,
API (die API deckt das Security-Dokument ab).

## Befunde

### UT-01 – URL ohne `https://` wird abgelehnt, die Eingabe ist danach weg (hoch)
`de.wikipedia.org/wiki/QR-Code` → Toast „Bitte eine gültige http(s)-URL angeben.“ Das Feld ist nach dem
Neuladen leer, die Eingabe muss neu gemacht werden. Screenshot `03-url-ohne-schema-abgelehnt.jpg`.
Prüfung serverseitig in `admin.php:973` über `valid_url()` (`lib.php:141`).
**Fix:** Vor der Prüfung `https://` voranstellen, wenn kein Schema da ist und der Text wie ein Host aussieht
(Client in `app.js` beim `blur`/`submit` **und** im Server in einem `normalize_url()`-Helfer in `lib.php`).
Bei Fehlern die Eingaben zurück ins Formular schreiben (Werte aus `$_POST` beim Rendern vorbelegen).

### UT-02 – Umlaut-Domains und Pfade mit Umlauten werden abgelehnt (hoch für DE-Zielgruppe)
`FILTER_VALIDATE_URL` liefert `false` für `https://müller.de/` und `https://de.wikipedia.org/wiki/Äpfel`
(verifiziert mit `php -r`). Nur Punycode `xn--mller-kva.de` geht.
**Fix:** In `normalize_url()` Host per `idn_to_ascii()` umwandeln (falls `intl` fehlt: Hinweis in der Diagnose),
Pfad/Query mit `rawurlencode` auf Nicht-ASCII-Bytes kodieren, **dann** `filter_var`. Test in `tests/lib.test.php`.

### UT-03 – Nach dem Anlegen ist der neue Link nicht sichtbar (mittel)
Toast „„z9geze“ angelegt. [Kopieren]“ erscheint, aber der Link steht weiter unten in seiner Gruppe; bei offenen
„Weitere Optionen“ beginnt die Liste erst bei ~1200 px (Desktop) bzw. 1769 px (Mobil). Der übliche nächste Schritt
(QR erzeugen) braucht Scrollen und Suchen. Sortierung „Neueste zuerst“ gilt nur **innerhalb** der Gruppen.
**Fix:** Nach `add` auf `#row-<slug>` springen und die Zeile kurz hervorheben; im Toast neben „Kopieren“ einen
„QR“-Knopf anbieten. „Weitere Optionen“ nach erfolgreichem Anlegen wieder zuklappen.

### UT-04 – Bearbeiten lädt die Seite neu und springt nach oben (mittel)
Klick auf den Stift → `admin?edit=z9geze`, Scroll-Position 0. Die Bearbeiten-Zeile liegt ~1500 px tiefer.
**Fix (klein):** Link auf `?edit=<slug>#row-<slug>` setzen bzw. in `app.js` beim Laden `tr.editrow` per
`scrollIntoView({block:'center'})` anfahren und das erste Feld fokussieren. Nach „Speichern“ ebenso zurück zur Zeile.

### UT-05 – QR-Dialog spricht „Pixel/Module“, nicht Druckgröße (mittel, Kern-Use-Case)
Felder „Modulgröße (px)“ und „Rand (Module)“ – wer druckt, denkt in cm/mm und DPI. Kein Hinweis auf
Mindestgröße (Faustregel: Kantenlänge ≥ Scanabstand/10, mindestens ~2 cm), keine Vorschau der Druckmaße.
Screenshot `07-qr-dialog.jpg`.
**Fix:** Zusatzfeld „Druckbreite (mm)“ + DPI (300) → berechnet die Modulgröße; Anzeige „ergibt 25 × 25 mm bei 300 dpi“.
Hinweistext zum Scanabstand. SVG mit `width/height` in mm ausgeben.

### UT-06 – Keine Kontrast-/Scanbarkeitswarnung (mittel)
Vorder-/Hintergrundfarbe frei wählbar, keine Prüfung (kein Treffer für „contrast/luminance“ in `app.js`). Helle
Vordergrund- oder invertierte Farben machen Codes für viele Scanner unlesbar.
**Fix:** Kontrastverhältnis berechnen (WCAG-Formel), unter ~4:1 bzw. bei hellerem Vordergrund als Hintergrund
Warnhinweis im Dialog. Optional: Selbsttest per `jsqr.js` auf das gerenderte Canvas („✓ scanbar“) – Decoder ist schon im Projekt.

### UT-07 – Kürzel umbenennen bricht gedruckte QR-Codes ohne Warnung (hoch, Datenverlust-ähnlich)
In der Bearbeiten-Zeile ist das Kürzel ein normales Feld. Beim Umbenennen (`admin.php:991–1017`) wird der alte Slug
entfernt, Klicks wandern mit – der alte Kurzlink liefert danach 404. Genau das zerstört gedruckte Codes.
**Fix:** Bei Änderung des Kürzels eine deutliche Warnung (Inline-Hinweis + Bestätigung) **und** Option
„alten Kurzlink weiterleiten“ (alter Slug bleibt als Alias, z. B. Feld `alias_of` oder Liste `aliases` im Eintrag).

### UT-08 – Großgeschriebene Kurzlinks liefern 404 (hoch)
`/goto/Z9GEZE` → 404, `/goto/z9geze` → 302. Slugs werden beim Anlegen kleingeschrieben (`clean_slug`), beim Aufruf
aber nicht. Abgetippte Links von Plakaten/Flyern („goto/Sommerfest“) und manche Scanner-Apps scheitern.
**Fix:** In `index.php` das angefragte Kürzel durch denselben `clean_slug`/`strtolower` schicken (Einzeiler).
Test: `curl -I /goto/Z9GEZE` → 302.

### UT-09 – Vorschau-Seite zeigt nur die Domain (niedrig–mittel)
„Weiterleitung zu de.wikipedia.org“ – der Pfad fehlt, ein „Abbrechen“ gibt es nicht. Für Vertrauen (Anti-Phishing)
hilft die vollständige, gekürzte Ziel-URL. Screenshot `10-besucher-vorschau.jpg`.
**Fix:** Volle URL (umgebrochen, max. ~120 Zeichen) unter der Domain; Domain fett. Countdown pausierbar.

### UT-10 – 404-Seite ist eine Sackgasse (niedrig)
„Kurz-URL nicht gefunden – Diese Kurz-URL existiert nicht (mehr).“ Keine Hilfe. Screenshot `11-besucher-404.jpg`.
**Fix:** Optionaler Konfig-Wert `fallback_url` („zur Startseite“) und Hinweis „Groß-/Kleinschreibung und Tippfehler prüfen“.
Optional ähnlichste Kürzel **nicht** anzeigen (würde Links enumerierbar machen).

### UT-11 – Mobil: Liste erst nach 2+ Bildschirmhöhen, 268 px je Link (mittel)
Stat-Kacheln (5 Stück, 2-spaltig) + offenes Anlege-Formular schieben die Liste auf 1769 px. Je Link-Karte 268 px →
~3 Links pro Bildschirm. Gruppen-„Umbenennen/Löschen“ stehen mobil dauerhaft sichtbar. Screenshots `12-*`, `13-*`.
**Fix:** Mobil Kacheln als eine horizontale Scroll-Zeile (oder nur 2 Kacheln), „Weitere Optionen“ mobil immer zu,
Gruppen-Aktionen in ein „…“-Menü, Gruppen-Select in der Karte nur im Bearbeiten-Modus.

### UT-12 – Testaufrufe zählen als Klicks (mittel)
Mehrere `curl`-Aufrufe erhöhten „Heute“ auf 8. Nur Social-Preview-Bots werden ausgenommen. Siehe auch Wettbewerbsrecherche
(Dub #2309: Link-Scanner, HEAD-Requests).
**Fix:** `HEAD` nie zählen; UA-Liste für typische Nicht-Menschen (curl, wget, python-requests, Go-http-client,
`bot|crawler|spider|preview|scan`) ausnehmen; optional Prefetch-Header (`Sec-Purpose: prefetch`, `Purpose: prefetch`).

### UT-13 – Toast verdeckt Theme- und Abmelde-Knopf (niedrig, bekannt)
Toast mit Kopier-Knopf bleibt 9 s oben rechts über der Topbar. Screenshot `04-link-angelegt-toast.jpg`.
**Fix:** Toasts unter die Topbar (top: Topbar-Höhe + 8 px) oder unten rechts; WCAG 2.4.11 (Focus Not Obscured).

## Positiv aufgefallen
- Login, Dark Mode und Typografie wirken hochwertig. Labels sind fast überall korrekt verknüpft (nur Suche,
  QR-Logo-Datei, Import-Datei und eine Textarea ohne Label).
- Query-Durchreichung funktioniert, `gq`-Marker wird korrekt entfernt.
- Passwort-Link: richtige Fehlermeldung, kein Ziel-Leak; Referrer-Policy `no-referrer`.
- OG-Seite für Bots liefert sinnvolle Vorschau ohne Klickzählung.
