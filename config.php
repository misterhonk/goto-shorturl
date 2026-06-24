<?php

/**
 * Konfiguration von GOTO.
 *
 * Das Passwort wird beim ersten Aufruf von admin.php festgelegt und automatisch
 * (als bcrypt-Hash) im Datenverzeichnis gespeichert (.ht_auth.json) – hier muss
 * dann nichts eingetragen werden.
 */

return [
    // Optional: Hash hier ODER per Umgebungsvariable GOTO_PASSWORD_HASH fest
    // „festnageln" (hat Vorrang und deaktiviert das Ändern im UI). Leer lassen
    // = Passwort wird über den Setup-Bildschirm gesetzt.
    'password_hash'   => getenv('GOTO_PASSWORD_HASH') ?: '',

    'idle_timeout'    => 1800,   // Auto-Logout nach Inaktivität (Sekunden)
    'max_attempts'    => 5,      // Login-Fehlversuche bis zur Sperre
    'lockout_seconds' => 900,    // Dauer der Sperre nach zu vielen Versuchen

    // Favicons der Zielseiten in der Liste anzeigen. true lädt sie von
    // icons.duckduckgo.com (ein externer Abruf je Domain). false = aus.
    'favicons'        => true,

    // Standardsprache der Oberfläche: 'de' oder 'en'. Lässt sich im Admin
    // jederzeit umschalten (wird pro Browser als Cookie gespeichert).
    'lang'            => 'de',

    // Speicherort der Datendateien (urls.json, clicks.json, .ht_auth.json …).
    // Standard: dieser Ordner. SICHERER ist ein Ordner OBERHALB des Web-Roots,
    // dann sind die Daten selbst ohne Apache/.htaccess nicht abrufbar, z.B.:
    //   'data_dir' => __DIR__ . '/../goto-data',
    // (Ordner muss existieren und für den Webserver beschreibbar sein.)
    'data_dir'        => __DIR__,
];
