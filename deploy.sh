#!/usr/bin/env bash
#
# GOTO – baut einen vollständigen Upload-Satz und liefert ihn aus.
# Verhindert den häufigen Fehler, einzelne Dateien (z. B. goto.css/app.js) zu vergessen.
#
#   ./deploy.sh                          -> kopiert nach ./dist/
#   ./deploy.sh /pfad/zum/ftp-mount      -> kopiert dorthin
#   ./deploy.sh user@host:/pfad [URL]    -> per SSH direkt auf den Server, z. B.
#       ./deploy.sh mrhonk@faye.uberspace.de:/var/www/virtual/mrhonk/html/goto https://misterhonk.de/goto/
#
# Code-Dateien werden immer überschrieben; config.php und urls.example.json nur,
# wenn am Ziel noch nicht vorhanden (Erstinstallation) – damit Live-Config und
# Live-Daten bei Updates nicht überschrieben werden.
#
# SSH-Ziel: Dateien kommen zuerst als versteckte .<name>.deploy-Dateien in den
# Zielordner (von .htaccess/nginx/Caddy gesperrt), werden dort mit dem PHP des
# Servers gelintet und erst dann umbenannt – Besucher sehen nie eine halbe
# Version. Vorher sichert das Skript den ganzen Ordner (inkl. Daten) nach
# ~/goto-backups/ auf dem Server (je Zielordner bleiben die letzten 5). Mit URL prüft es
# danach von außen, ob das Admin antwortet und urls.json gesperrt ist.

set -euo pipefail
cd "$(dirname "$0")"

DEST="${1:-dist}"
URL="${2:-}"

# Immer ausliefern (Anwendungscode + Schutzregeln) im Wurzelordner:
CODE=(index.php admin.php api.php lib.php lang.php .htaccess favicon.ico)

# Statische Assets – landen im Unterordner assets/ (Pfad muss erhalten bleiben):
ASSETS=(assets/goto.css assets/app.js assets/qr.js assets/jsqr.js
        assets/favicon.svg assets/apple-touch-icon.png assets/og.png)

# Nur bei Erstinstallation (nicht überschreiben, falls am Ziel vorhanden):
FIRST=(config.php urls.example.json)

for f in "${CODE[@]}" "${ASSETS[@]}" "${FIRST[@]}"; do
    [ -e "$f" ] || { echo "FEHLT im Repo: $f" >&2; exit 1; }
done

if [[ "$DEST" != *:/* ]]; then
    # ---- Lokales Ziel (dist/ oder eingehängter Ordner) -----------------
    mkdir -p "$DEST" "$DEST/assets"
    echo "→ Code nach $DEST/"
    for f in "${CODE[@]}";   do cp -v "$f" "$DEST/"; done
    echo "→ Assets nach $DEST/assets/"
    for f in "${ASSETS[@]}"; do cp -v "$f" "$DEST/assets/"; done
    echo "→ Erstinstallations-Dateien"
    for f in "${FIRST[@]}"; do
        if [ -e "$DEST/$f" ]; then echo "  skip (existiert bereits): $DEST/$f"; else cp -v "$f" "$DEST/"; fi
    done
    cat <<EOF

Fertig. Inhalt von '$DEST/' per FTP in den Zielordner hochladen.

Hinweise:
  • config.php und die LIVE urls.json bei Updates NICHT überschreiben.
  • Erstinstallation: urls.example.json am Server in urls.json umbenennen.
  • Laufzeitdateien (clicks.json, .ht_*) legt der Server selbst an.
  • FallbackResource-Pfad in .htaccess an den Zielordner anpassen.
EOF
    exit 0
fi

# ---- SSH-Ziel -----------------------------------------------------------
HOST="${DEST%%:*}"
DIR="${DEST#*:}"
VERSION="$(grep -o "GOTO_VERSION = '[^']*'" lib.php | cut -d"'" -f2)"

STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT
mkdir -p "$STAGE/assets"
for f in "${CODE[@]}";   do cp "$f" "$STAGE/.$f.deploy"; done
for f in "${ASSETS[@]}"; do cp "$f" "$STAGE/assets/.$(basename "$f").deploy"; done
for f in "${FIRST[@]}"; do
    if ! ssh "$HOST" "test -e '$DIR/$f'"; then cp "$f" "$STAGE/.$f.deploy"; echo "→ Erstinstallation: $f wird angelegt"; fi
done

echo "→ GOTO $VERSION nach $HOST:$DIR (vorerst als versteckte .deploy-Dateien)"
ssh "$HOST" "mkdir -p '$DIR/assets'"
rsync -rlt "$STAGE/" "$HOST:$DIR/"

echo "→ Lint, Sicherung und Austausch auf dem Server"
ssh "$HOST" bash -s -- "$DIR" <<'REMOTE'
set -euo pipefail
cd "$1"
shopt -s nullglob dotglob
TMP=(.*.deploy assets/.*.deploy)
abort() { rm -f "${TMP[@]}"; echo "ABGEBROCHEN: $1 – am Server wurde nichts geändert." >&2; exit 1; }
for f in "${TMP[@]}"; do
    n="$(basename "$f" .deploy)"
    case "$f" in *.php.deploy) php -l "$f" >/dev/null 2>&1 || abort "PHP-Syntaxfehler in $(dirname "$f")/${n#.}";; esac
done
OLD="$(grep -o "GOTO_VERSION = '[^']*'" lib.php 2>/dev/null | cut -d"'" -f2 || true)"
NAME="$(basename "$PWD")"   # Sicherungen je Zielordner getrennt rotieren
mkdir -p ~/goto-backups
B=~/goto-backups/${NAME}_${OLD:-neu}_$(date +%Y%m%d-%H%M%S).tar.gz
tar -czf "$B" --exclude='.*.deploy' -C "$(dirname "$PWD")" "$NAME" || abort "Sicherung fehlgeschlagen"
ls -1t ~/goto-backups/"${NAME}"_*.tar.gz | tail -n +6 | xargs -r rm -f
for f in "${TMP[@]}"; do
    b="$(basename "$f" .deploy)"
    mv -f "$f" "$(dirname "$f")/${b#.}"
done
echo "  vorher: ${OLD:-keine Installation}  ->  jetzt: $(grep -o "GOTO_VERSION = '[^']*'" lib.php | cut -d"'" -f2)"
echo "  Sicherung: $B"
echo "  Zurück: tar -xzf $B -C $(dirname "$PWD")"
REMOTE

if [ -n "$URL" ]; then
    URL="${URL%/}/"
    echo "→ Prüfung von außen: $URL"
    ok=1
    admin="$(curl -fsS -m 15 "${URL}admin" || true)"
    if [[ "$admin" == *"<title>GOTO"* ]]; then echo "  ✓ Admin antwortet"; else echo "  ✗ Admin antwortet nicht wie erwartet"; ok=0; fi
    code="$(curl -s -o /dev/null -m 15 -w '%{http_code}' "${URL}urls.json" || true)"
    if [ "$code" != "200" ]; then echo "  ✓ urls.json gesperrt (HTTP $code)"; else echo "  ✗ urls.json ist öffentlich lesbar!"; ok=0; fi
    [ "$ok" = 1 ] || { echo "Prüfung FEHLGESCHLAGEN – Sicherung siehe oben." >&2; exit 1; }
fi
echo "Fertig."
