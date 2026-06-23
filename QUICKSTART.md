# GOTO — Quick Start

In 5 Minuten startklar. Ausführliche Doku: siehe [README.md](README.md).

---

## 🚀 Produktiv einrichten

1. **Hochladen** — Ordner `goto/` in den Web-Root (z. B. `deine-domain.de/goto/`).

2. **Schreibrechte** geben, damit die App Daten speichern kann:
   ```bash
   chmod 664 urls.json
   chmod 775 .
   ```

3. **Passwort setzen** — `deine-domain.de/goto/admin` öffnen → Wunschpasswort
   eingeben → wird automatisch gespeichert. (Änderbar später im Admin unter
   „Passwort ändern".)

4. **Fertig.** Anmelden und ersten Link anlegen.

> Voraussetzung: PHP 8.0+ mit `mod_rewrite` (Apache).

---

## 🔗 Nutzung

| Aktion | Wo |
|---|---|
| Verwaltung | `deine-domain.de/goto/admin` |
| Öffentlicher Kurzlink | `deine-domain.de/goto/kürzel` |

**Link anlegen:** Ziel-URL eintragen → Kürzel optional (leer = zufällig) →
*Hinzufügen*. QR-Code, Gruppe, Ablaufdatum, Titel jederzeit verfügbar.

---

## 🧪 Lokal testen (Docker)

Im übergeordneten Ordner:

```bash
docker compose up -d --build
```

→ http://localhost:8088/goto/admin

Beim ersten Aufruf Passwort festlegen (wird automatisch gespeichert) und anmelden.
Stoppen: `docker compose down`.

---

## 🔒 Empfehlung für mehr Sicherheit

Datendateien aus dem Web-Root holen — in `config.php`:

```php
'data_dir' => __DIR__ . '/../goto-data',
```

Ordner anlegen, beschreibbar machen, vorhandene `urls.json` dorthin verschieben.

---

## 🆘 Schnelle Hilfe

| Problem | Lösung |
|---|---|
| „Konnte nicht speichern" | Schreibrechte auf `urls.json` / Datenordner prüfen |
| `500` nach Upload | in `.htaccess` die Zeile `Options -Indexes` entfernen |
| Kurzlink → 404 | Kürzel falsch oder `mod_rewrite` fehlt |
| Kurzlink → 410 | Link ist abgelaufen → Ablaufdatum im Admin anpassen |
