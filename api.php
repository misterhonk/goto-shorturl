<?php
declare(strict_types=1);

@ini_set('display_errors', '0');   // Produktion: keine PHP-Fehler an Aufrufer ausgeben

/* ------------------------------------------------------------------ *
 *  GOTO  –  HTTP-API zum Anlegen von Kurz-URLs
 *
 *  Authentifizierung per Token (im Admin unter „API-Zugang" erstellt):
 *      Authorization: Bearer goto_<id>_<secret>
 *    Alternativ als Header  X-Api-Key:  oder Feld  token=  (Body/Query).
 *
 *  Anlegen (POST), Felder als Formular ODER JSON-Body:
 *      url      (Pflicht, http/https)
 *      slug     (optional; leer = zufälliges Kürzel)
 *      group    (optional; wird bei Bedarf angelegt)
 *      title    (optional)
 *      expires  (optional, JJJJ-MM-TT)
 *
 *  Antwort ist immer JSON. Erfolg: 201 { ok:true, slug, short_url, … }.
 *  Datenmodell und Validierung sind identisch zu admin.php (DB-los).
 * ------------------------------------------------------------------ */

$cfg     = @include __DIR__ . '/config.php';
$dataDir = rtrim((string) ((is_array($cfg) ? ($cfg['data_dir'] ?? null) : null) ?? __DIR__), '/');

@ini_set('log_errors', '1');
@ini_set('error_log', $dataDir . '/.ht_error.log');

define('URLS_FILE',       $dataDir . '/urls.json');
define('API_TOKENS_FILE', $dataDir . '/.ht_apitokens.json');

const API_RATE_MAX = 120;   // erlaubte Anfragen pro Minute je Token

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');

function respond(int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---- Helfer (deckungsgleich mit admin.php) ------------------------ */

function load_json(string $file): array {
    if (!is_file($file)) return [];
    $data = json_decode((string) file_get_contents($file), true);
    return is_array($data) ? $data : [];
}
function save_json(string $file, array $data): bool {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) return false;
    $tmp = $file . '.tmp';
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    return @rename($tmp, $file);
}
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
        if ($group !== '' && !in_array($group, $groups, true)) $groups[] = $group;
        $links[$slug] = ['url' => $url, 'group' => $group, 'title' => $title,
                         'expires' => $expires, 'created' => $created];
    }
    return ['groups' => $groups, 'links' => $links];
}
function load_data(): array {
    $raw = load_json(URLS_FILE);
    if (!$raw && is_file(URLS_FILE) && @filesize(URLS_FILE) > 2 && is_file(URLS_FILE . '.bak')) {
        $raw = load_json(URLS_FILE . '.bak');
    }
    return normalize_data($raw);
}
function save_data(array $d): bool {
    if (is_file(URLS_FILE)) @copy(URLS_FILE, URLS_FILE . '.bak');
    return save_json(URLS_FILE, ['groups' => array_values($d['groups'] ?? []), 'links' => $d['links'] ?? []]);
}

function api_base(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443')
          || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    $dir = rtrim(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir;
}

/* ---- Eingabe einlesen (Formular oder JSON-Body) ------------------- */

$ctype = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
$in    = $_POST;
if (strpos($ctype, 'application/json') !== false) {
    $body = json_decode((string) file_get_contents('php://input'), true);
    if (is_array($body)) $in = $body;
}

/* ---- Token ermitteln --------------------------------------------- */

function bearer_token(array $in): string {
    $h = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if ($h === '' && function_exists('apache_request_headers')) {
        foreach (apache_request_headers() as $k => $v) {
            if (strcasecmp($k, 'Authorization') === 0) { $h = (string) $v; break; }
        }
    }
    if (stripos($h, 'Bearer ') === 0) return trim(substr($h, 7));
    if (isset($_SERVER['HTTP_X_API_KEY']))  return trim((string) $_SERVER['HTTP_X_API_KEY']);
    return trim((string) ($in['token'] ?? $_GET['token'] ?? ''));
}

$token = bearer_token($in);
if (!preg_match('/^goto_([0-9a-f]{12})_([0-9a-f]{48})$/', $token, $m)) {
    respond(401, ['ok' => false, 'error' => 'unauthorized', 'message' => 'Missing or malformed API token.']);
}
[$full, $id, $secret] = $m;

$tokens = load_json(API_TOKENS_FILE);
$rec    = $tokens[$id] ?? null;
if (!is_array($rec) || !hash_equals((string) ($rec['h'] ?? ''), hash('sha256', $secret))) {
    respond(401, ['ok' => false, 'error' => 'unauthorized', 'message' => 'Invalid API token.']);
}

/* ---- Rate-Limit (pro Token, je Minute) --------------------------- */

$min = (int) (time() / 60);
$rl  = (is_array($rec['rl'] ?? null)) ? $rec['rl'] : ['m' => 0, 'n' => 0];
if ((int) ($rl['m'] ?? 0) === $min) {
    if ((int) ($rl['n'] ?? 0) >= API_RATE_MAX) {
        header('Retry-After: ' . (60 - (time() % 60)));
        respond(429, ['ok' => false, 'error' => 'rate_limited', 'message' => 'Too many requests, slow down.']);
    }
    $rl['n'] = (int) ($rl['n'] ?? 0) + 1;
} else {
    $rl = ['m' => $min, 'n' => 1];
}
$rec['rl']    = $rl;
$rec['used']  = time();
$rec['calls'] = (int) ($rec['calls'] ?? 0) + 1;
$tokens[$id]  = $rec;
save_json(API_TOKENS_FILE, $tokens);

/* ---- Nur POST legt an -------------------------------------------- */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, ['ok' => false, 'error' => 'method_not_allowed', 'message' => 'Use POST to create a short URL.']);
}

/* ---- Kurz-URL anlegen -------------------------------------------- */

$url     = trim((string) ($in['url'] ?? ''));
$slug    = clean_slug((string) ($in['slug'] ?? ''));
$group   = clean_group((string) ($in['group'] ?? ''));
$title   = clean_title((string) ($in['title'] ?? ''));
$expires = clean_date((string) ($in['expires'] ?? ''));

if (!valid_url($url)) {
    respond(422, ['ok' => false, 'error' => 'invalid_url', 'message' => 'Field "url" must be a valid http(s) URL.']);
}

$data = load_data();
if ($slug === '') $slug = random_slug($data['links']);
if (in_array($slug, ['admin', 'index', 'api'], true)) {
    respond(409, ['ok' => false, 'error' => 'reserved_slug', 'slug' => $slug, 'message' => 'This slug is reserved.']);
}
if (isset($data['links'][$slug])) {
    respond(409, ['ok' => false, 'error' => 'slug_taken', 'slug' => $slug, 'message' => 'Slug already in use.']);
}
if ($group !== '' && !in_array($group, $data['groups'], true)) $data['groups'][] = $group;

$data['links'][$slug] = ['url' => $url, 'group' => $group, 'title' => $title,
                         'expires' => $expires, 'created' => time()];

if (!save_data($data)) {
    respond(500, ['ok' => false, 'error' => 'write_failed', 'message' => 'Could not save – check write permissions for urls.json.']);
}

respond(201, [
    'ok'        => true,
    'slug'      => $slug,
    'short_url' => api_base() . $slug,
    'url'       => $url,
    'group'     => $group,
    'title'     => $title,
    'expires'   => $expires,
]);
