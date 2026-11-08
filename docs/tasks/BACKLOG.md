# Backlog – bewusst noch nicht als Task ausformuliert

Ideen aus der Recherche, die erst nach Martins Entscheidung zu einem Task werden. Quelle in Klammern.

| ID | Idee | Warum (noch) nicht | Quelle |
|---|---|---|---|
| B01 | Tags quer zu Gruppen | bereits Roadmap-Punkt 8, eher nice-to-have | Memory, research-competitors |
| B02 | Passkey/WebAuthn-Login | Roadmap-Punkt 7; hilft nur, wenn T09a (Passwortmanager) erledigt ist; ohne DB etwas mehr Aufwand | research-ux Thema 6 |
| B03 | Klickzähler je Link zurücksetzen | klein und nützlich nach Testdrucken, zuerst prüfen, ob es schon geht | research-competitors Could |
| B04 | „Letzter Klick vor X Tagen“ in der Liste | aus `clicks.d` ableitbar, keine neuen Daten | research-ux |
| B05 | Markierung „gedruckt“ je Link, mit Warnung bei Löschen, Ablauf oder Umbenennen | ergänzt T05; erst entscheiden, ob es ein manuelles Flag oder automatisch (bei QR-Download) wird | research-ux |
| B06 | Leerer Startzustand als 3-Schritt-Anleitung (anlegen → QR → später umleiten) | betrifft nur neue Installationen | research-ux |
| B07 | QR-Rahmen „Scan mich“, Muster/Ecken-Stile | Wirkung durch Studien nicht belegt; Scanbarkeit braucht T06-Selbsttest | research-competitors, research-ux |
| B08 | Branding der Besucherseiten (Akzentfarbe, Logo) | geringer Nutzen; Besucherseiten nutzen bisher Indigo statt des Admin-Blaus (vereinheitlichen) | research-competitors |
| B09 | Link-in-Bio-Seite aus einer Gruppe | Scope-Gefahr | research-competitors |
| B10 | Webhooks bei Anlage oder Änderung (Opt-in) | ausgehender Request, nie im Redirect-Pfad | research-competitors |
| B11 | Eigenes OG-Bild je Link | braucht Upload-Handling (siehe T13) | research-competitors |
| B12 | `?`-Overlay mit den Tastenkürzeln | Command Palette lohnt nicht | research-ux |
| B13 | Inspektionsseite `kürzel+` (zeigt das Ziel ohne Weiterleitung, wie bei Bitly) | Vertrauen; aber T07 deckt viel ab | research-ux |
| B14 | Performance bei 500+ Links (2,8 MB HTML, `data-days` je Zeile; Assets ohne `Cache-Control`) | aus der Revision 2026-10 offen | Memory goto-revision-2026-10 |
| B15 | Zweiter Admin / einfache Rollen | Zielgruppe ist ein einzelner Admin | research-competitors |

**Nicht machen (Won't, mit Begründung in research-competitors.md 5.2):** Geo-Analytics/-Routing, Detail-Analytics je Klick,
Retargeting-Pixel, Deep Links, öffentliches Kürz-Formular, SSO, KI-Funktionen, SQLite/DB, Max-Klick-Links, externes Phishing-Screening.
