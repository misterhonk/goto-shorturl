<?php
declare(strict_types=1);

/* ------------------------------------------------------------------ *
 *  GOTO – gemeinsame Basis für index.php, admin.php und api.php.
 *
 *  Enthält Bootstrap (Konfiguration, Datenpfad, Fehler-Logging,
 *  gemeinsame Pfad-Konstanten) sowie die reinen Helfer für Daten-,
 *  URL- und Slug-Verarbeitung. Bewusst OHNE Session, HTTP-Header oder
 *  Ausgabe – das bleibt Sache des jeweiligen Einstiegspunkts.
 *
 *  Datenmodell (urls.json):
 *    { "groups": ["Projekt A"],
 *      "links": { "slug": {
 *         "url":"", "group":"", "title":"", "expires":"YYYY-MM-DD",
 *         "created":0, "pass":"",   // pass: bcrypt-Hash, ""=ohne Passwort
 *         "preview":false           // true = Vorschau-Seite vor der Weiterleitung
 *      } } }
 * ------------------------------------------------------------------ */

@ini_set('display_errors', '0');   // Produktion: keine PHP-Fehler an Besucher ausgeben

$cfg = @include __DIR__ . '/config.php';
if (!is_array($cfg)) $cfg = [];

// Datenverzeichnis – idealerweise außerhalb des Web-Roots (siehe config.php).
$dataDir = rtrim((string) ($cfg['data_dir'] ?? __DIR__), '/');

// Fehler nicht anzeigen, aber in eine geschützte Datei loggen (Ferndiagnose)
@ini_set('log_errors', '1');
@ini_set('error_log', $dataDir . '/.ht_error.log');

// Von allen Einstiegspunkten genutzte Dateien.
define('URLS_FILE',       $dataDir . '/urls.json');
define('CLICKS_FILE',     $dataDir . '/clicks.json');
define('API_TOKENS_FILE', $dataDir . '/.ht_apitokens.json');

/* ---- HTTPS-Erkennung (inkl. Reverse-Proxy) ----------------------- */

function is_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
}

/* ---- JSON lesen / atomar schreiben ------------------------------- */

function load_json(string $file): array {
    if (!is_file($file)) return [];
    $data = json_decode((string) file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function save_json(string $file, array $data): bool {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) return false;                    // ungültige Daten -> nicht schreiben
    $tmp = $file . '.tmp';
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    return @rename($tmp, $file);                          // rename ist atomar (gleiches Dateisystem)
}

/* ---- Validierung / Bereinigung ----------------------------------- */

function valid_url(string $url): bool {
    if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true);
}

function clean_slug(string $s): string {
    return preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($s)));
}

function clean_text(string $s, int $max): string {
    $s = preg_replace('/[\x00-\x1F\x7F]+/', '', $s) ?? $s;
    $s = trim(preg_replace('/\s+/', ' ', $s) ?? $s);
    return function_exists('mb_substr') ? mb_substr($s, 0, $max) : substr($s, 0, $max);
}
function clean_group(string $s): string { return clean_text($s, 40); }
function clean_title(string $s): string { return clean_text($s, 80); }

function clean_date(string $s): string {
    $s = trim($s);
    if ($s === '') return '';
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return ($d && $d->format('Y-m-d') === $s) ? $s : '';
}

function random_slug(array $links): string {
    $alphabet = 'abcdefghijkmnpqrstuvwxyz23456789';   // ohne l,o,0,1
    do {
        $s = '';
        for ($i = 0; $i < 6; $i++) $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    } while (isset($links[$s]));
    return $s;
}

/* ---- Datenmodell (urls.json) ------------------------------------- */

function normalize_data($raw): array {
    $groups = [];
    $links  = [];
    if (!is_array($raw)) return ['groups' => [], 'links' => []];

    $newFormat = isset($raw['links']) && is_array($raw['links']);
    $src       = $newFormat ? $raw['links'] : $raw;

    if ($newFormat) {
        foreach (($raw['groups'] ?? []) as $g) {
            $g = clean_group((string) $g);
            if ($g !== '' && !in_array($g, $groups, true)) $groups[] = $g;
        }
    }
    foreach ($src as $slug => $item) {
        $slug = clean_slug((string) $slug);
        $url  = is_array($item) ? (string) ($item['url'] ?? '') : (string) $item;
        if ($slug === '' || !valid_url($url)) continue;
        $group   = is_array($item) ? clean_group((string) ($item['group'] ?? '')) : '';
        $title   = is_array($item) ? clean_title((string) ($item['title'] ?? '')) : '';
        $expires = is_array($item) ? clean_date((string) ($item['expires'] ?? '')) : '';
        $created = is_array($item) ? (int) ($item['created'] ?? 0) : 0;
        $pass    = is_array($item) ? (string) ($item['pass'] ?? '') : '';
        $preview = is_array($item) && !empty($item['preview']);
        if ($group !== '' && !in_array($group, $groups, true)) $groups[] = $group;
        $links[$slug] = ['url' => $url, 'group' => $group, 'title' => $title,
                         'expires' => $expires, 'created' => $created,
                         'pass' => $pass, 'preview' => $preview];
    }
    return ['groups' => $groups, 'links' => $links];
}

function load_data(): array {
    $raw = load_json(URLS_FILE);
    // Bei beschädigter (nicht-leerer, aber undekodierbarer) Datei auf Sicherung zurückgreifen
    if (!$raw && is_file(URLS_FILE) && @filesize(URLS_FILE) > 2 && is_file(URLS_FILE . '.bak')) {
        $raw = load_json(URLS_FILE . '.bak');
    }
    return normalize_data($raw);
}

function save_data(array $d): bool {
    if (is_file(URLS_FILE)) @copy(URLS_FILE, URLS_FILE . '.bak');   // eine Sicherungs-Generation
    return save_json(URLS_FILE, ['groups' => array_values($d['groups'] ?? []), 'links' => $d['links'] ?? []]);
}
