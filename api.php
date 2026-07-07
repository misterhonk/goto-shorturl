<?php
declare(strict_types=1);

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
 *  Datenmodell, Validierung und Bootstrap kommen aus lib.php.
 * ------------------------------------------------------------------ */

require __DIR__ . '/lib.php';   // Bootstrap ($cfg, $dataDir, Konstanten) + Helfer

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

/* ---- API-spezifische Helfer -------------------------------------- */

function api_base(): string {
    $dir = rtrim(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';
    return (is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir;
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
$linkpw  = (string) ($in['password'] ?? '');   // optional: Link-Passwort (wird gehasht)

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
                         'expires' => $expires, 'created' => time(),
                         'pass' => ($linkpw !== '') ? password_hash($linkpw, PASSWORD_DEFAULT) : '',
                         'preview' => !empty($in['preview'])];

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
    'protected' => ($linkpw !== ''),
    'preview'   => !empty($in['preview']),
]);
