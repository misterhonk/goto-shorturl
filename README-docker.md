# GOTO – lokale Testumgebung

Lokales Abbild eines typischen Shared-Hostings (PHP 8.2 + Apache, `mod_rewrite`,
`.htaccess`), um GOTO vor dem Upload zu testen.

## Starten

```bash
docker compose up -d --build
```

→ http://localhost:8088/goto/admin

Beim ersten Aufruf ein Passwort festlegen (wird automatisch gespeichert) und anmelden.

## Stoppen

```bash
docker compose down
```

## Hinweis

Laufzeitdateien (`urls.json`, `clicks.json`, `.ht_*`) werden automatisch angelegt
und sind per `.gitignore` aus dem Repository ausgeschlossen.
