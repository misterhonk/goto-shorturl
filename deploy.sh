#!/usr/bin/env bash
#
# GOTO – baut einen vollständigen Upload-Satz in einen Zielordner (Standard: ./dist).
# Verhindert den häufigen Fehler, einzelne Dateien (z. B. goto.css/app.js) zu vergessen.
#
#   ./deploy.sh            -> kopiert nach ./dist/
#   ./deploy.sh /pfad/zum/ftp-mount   -> kopiert dorthin
#
# Code-Dateien werden immer überschrieben; config.php und urls.example.json nur,
# wenn am Ziel noch nicht vorhanden (Erstinstallation) – damit Live-Config und
# Live-Daten bei Updates nicht überschrieben werden.

set -euo pipefail
cd "$(dirname "$0")"

DEST="${1:-dist}"

# Immer ausliefern (Anwendungscode + Schutzregeln) im Wurzelordner:
CODE=(index.php admin.php api.php lib.php lang.php .htaccess favicon.ico)

# Statische Assets – landen im Unterordner assets/ (Pfad muss erhalten bleiben):
ASSETS=(assets/goto.css assets/app.js assets/qr.js
        assets/favicon.svg assets/apple-touch-icon.png assets/og.png)

# Nur bei Erstinstallation (nicht überschreiben, falls am Ziel vorhanden):
FIRST=(config.php urls.example.json)

mkdir -p "$DEST" "$DEST/assets"

echo "→ Code nach $DEST/"
for f in "${CODE[@]}"; do
    [ -e "$f" ] || { echo "FEHLT im Repo: $f" >&2; exit 1; }
    cp -v "$f" "$DEST/"
done

echo "→ Assets nach $DEST/assets/"
for f in "${ASSETS[@]}"; do
    [ -e "$f" ] || { echo "FEHLT im Repo: $f" >&2; exit 1; }
    cp -v "$f" "$DEST/assets/"
done

echo "→ Erstinstallations-Dateien"
for f in "${FIRST[@]}"; do
    if [ -e "$DEST/$f" ]; then
        echo "  skip (existiert bereits): $DEST/$f"
    else
        cp -v "$f" "$DEST/"
    fi
done

cat <<EOF

Fertig. Inhalt von '$DEST/' per FTP in den Zielordner hochladen.

Hinweise:
  • config.php und die LIVE urls.json bei Updates NICHT überschreiben.
  • Erstinstallation: urls.example.json am Server in urls.json umbenennen.
  • Laufzeitdateien (clicks.json, .ht_*) legt der Server selbst an.
  • FallbackResource-Pfad in .htaccess an den Zielordner anpassen.
EOF
