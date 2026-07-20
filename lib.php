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
 *         "starts":"YYYY-MM-DD",    // vor diesem Tag noch nicht aktiv ("" = sofort)
 *         "created":0, "pass":"",   // pass: bcrypt-Hash, ""=ohne Passwort
 *         "preview":false,          // true = Vorschau-Seite vor der Weiterleitung
 *         "alts":[{"url":"","weight":1}]  // weitere Ziele (gewichtete Rotation)
 *      } } }
 * ------------------------------------------------------------------ */

const GOTO_VERSION = '1.2.0';

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
define('TRASH_FILE',      $dataDir . '/.ht_trash.json');
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

/* Quellen-/Kampagnen-Marker (?q=…): kleiner, dateisystem-/JSON-sicherer Bezeichner.
 * Datensparsam – der Name wird vom Nutzer selbst vergeben (kein PII). */
function clean_source(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9._\-]+/', '-', $s) ?? '';
    $s = trim($s, '-');
    return substr($s, 0, 32);
}

function clean_date(string $s): string {
    $s = trim($s);
    if ($s === '') return '';
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return ($d && $d->format('Y-m-d') === $s) ? $s : '';
}

/* ---- Rotation / mehrere Ziele ------------------------------------ */

// Weitere Ziele auf gültige [{url,weight}] normalisieren (max. 20, Gewicht 1–100)
function normalize_alts($raw): array {
    if (!is_array($raw)) return [];
    $out = [];
    foreach ($raw as $a) {
        $u = is_array($a) ? (string) ($a['url'] ?? '') : (string) $a;
        $u = trim($u);
        if (!valid_url($u)) continue;
        $w = is_array($a) ? (int) ($a['weight'] ?? 1) : 1;
        $w = max(1, min(100, $w));
        $out[] = ['url' => $u, 'weight' => $w];
        if (count($out) >= 20) break;
    }
    return $out;
}

// Freitext (eine URL je Zeile, optional abschließendes Gewicht) → [{url,weight}]
function parse_alts_text(string $text): array {
    $out = [];
    foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $weight = 1;
        if (preg_match('/^(.*\S)\s+(\d{1,3})$/', $line, $m)) { $line = $m[1]; $weight = (int) $m[2]; }
        $out[] = ['url' => $line, 'weight' => $weight];
    }
    return normalize_alts($out);
}

// Zielauswahl: gewichteter Zufall über das Primärziel (Gewicht 1) + weitere Ziele.
// Zustandslos – kein zusätzlicher Schreibzugriff im Hot-Path.
function weighted_pick(string $primary, array $alts): string {
    $pool = [];
    if (valid_url($primary)) $pool[] = ['url' => $primary, 'weight' => 1];
    foreach (normalize_alts($alts) as $a) $pool[] = $a;
    if (!$pool) return $primary;
    $total = 0; foreach ($pool as $p) $total += $p['weight'];
    $r = random_int(1, $total);
    foreach ($pool as $p) { $r -= $p['weight']; if ($r <= 0) return $p['url']; }
    return $pool[0]['url'];
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
        $starts  = is_array($item) ? clean_date((string) ($item['starts'] ?? '')) : '';
        $created = is_array($item) ? (int) ($item['created'] ?? 0) : 0;
        $pass    = is_array($item) ? (string) ($item['pass'] ?? '') : '';
        $preview = is_array($item) && !empty($item['preview']);
        $expUrl  = is_array($item) ? (string) ($item['expires_url'] ?? '') : '';
        if (!valid_url($expUrl)) $expUrl = '';
        $alts    = is_array($item) ? normalize_alts($item['alts'] ?? []) : [];
        if ($group !== '' && !in_array($group, $groups, true)) $groups[] = $group;
        $links[$slug] = ['url' => $url, 'group' => $group, 'title' => $title,
                         'expires' => $expires, 'starts' => $starts, 'created' => $created,
                         'pass' => $pass, 'preview' => $preview, 'expires_url' => $expUrl,
                         'alts' => $alts];
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

/* Tages-Backup: beim ersten Schreibvorgang eines Tages wandert der aktuelle
 * Stand nach backups/urls-JJJJ-MM-TT.json; die letzten 7 Tage bleiben erhalten.
 * (Die .htaccess-Regeln gelten auch im Unterordner – *.json ist gesperrt.) */
function backup_rotate(): void {
    $dir   = dirname(URLS_FILE) . '/backups';
    $today = $dir . '/urls-' . date('Y-m-d') . '.json';
    if (is_file($today)) return;                       // heute schon gesichert
    if (!is_dir($dir) && !@mkdir($dir, 0775)) return;  // nicht anlegbar -> still überspringen
    @copy(URLS_FILE, $today);
    $files = glob($dir . '/urls-*.json') ?: [];
    sort($files);
    while (count($files) > 7) @unlink(array_shift($files));
}

function save_data(array $d): bool {
    if (is_file(URLS_FILE)) {
        @copy(URLS_FILE, URLS_FILE . '.bak');   // letzte Schreib-Generation
        backup_rotate();                         // + Tages-Generationen (7 Tage)
    }
    return save_json(URLS_FILE, ['groups' => array_values($d['groups'] ?? []), 'links' => $d['links'] ?? []]);
}

/* ---- Klicks & Papierkorb (Admin + API) ---------------------------- */

function load_clicks(): array  { return load_json(CLICKS_FILE); }
function save_clicks(array $c): bool { return save_json(CLICKS_FILE, $c); }

// Klick-Datensatz kann int (Alt-Format) oder {t:total, d:{tag:n}} sein
function clicks_total(array $clicks, string $slug): int {
    $c = $clicks[$slug] ?? 0;
    return is_array($c) ? (int) ($c['t'] ?? 0) : (int) $c;
}
function clicks_days(array $clicks, string $slug): array {
    $c = $clicks[$slug] ?? null;
    return (is_array($c) && isset($c['d']) && is_array($c['d'])) ? $c['d'] : [];
}
// Quellen-Aufschlüsselung { quelle: n } (aus ?q=…), absteigend sortiert
function clicks_sources(array $clicks, string $slug): array {
    $c = $clicks[$slug] ?? null;
    $s = (is_array($c) && isset($c['s']) && is_array($c['s'])) ? $c['s'] : [];
    arsort($s);
    return $s;
}

function load_trash(): array  { return load_json(TRASH_FILE); }
function save_trash(array $t): bool { return save_json(TRASH_FILE, $t); }
