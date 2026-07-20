<?php
declare(strict_types=1);

/* ------------------------------------------------------------------ *
 *  GOTO  –  HTTP-API (CRUD) für Kurz-URLs
 *
 *  Authentifizierung per Token (im Admin unter „API-Zugang" erstellt):
 *      Authorization: Bearer goto_<id>_<secret>
 *    Alternativ als Header  X-Api-Key:  oder Feld  token=  (Body/Query).
 *
 *  Methoden (Felder als Formular, JSON-Body oder Query):
 *      GET              Liste aller Links (mit Klick-Summen)
 *      GET    ?slug=…   Details eines Links inkl. Tages-Klickwerten
 *      POST             Anlegen: url (Pflicht), slug, group, title,
 *                       expires (JJJJ-MM-TT), starts (JJJJ-MM-TT), password, preview,
 *                       alts (Rotation: Array [{url,weight}] oder Text, eine URL je Zeile)
 *      PATCH            Ändern: slug (Kennung) + beliebige der Felder
 *                       url, title, group, expires, starts, password (""=weg),
 *                       preview, alts, newslug (umbenennen)
 *      DELETE ?slug=…   In den Papierkorb (Wiederherstellen im Admin)
 *
 *  Antwort ist immer JSON ({ ok:true, … } bzw. { ok:false, error }).
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

/* ---- Eingabe einlesen (Formular, JSON-Body oder Query) ------------ */

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$ctype  = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
$in     = $_POST;
if (strpos($ctype, 'application/json') !== false) {
    $body = json_decode((string) file_get_contents('php://input'), true);
    if (is_array($body)) $in = $body;
} elseif ($in === [] && in_array($method, ['PATCH', 'DELETE'], true)) {
    // PHP füllt $_POST nur bei POST – Formular-Body bei PATCH/DELETE selbst parsen
    parse_str((string) file_get_contents('php://input'), $in);
}
$in += $_GET;   // Query-Parameter als Fallback (Body hat Vorrang)

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

/* ---- Scope: Nur-Lese-Token dürfen keine schreibenden Methoden ----- */

if (($rec['scope'] ?? 'write') === 'read' && $method !== 'GET') {
    respond(403, ['ok' => false, 'error' => 'read_only', 'message' => 'This token is read-only; only GET is allowed.']);
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

/* ---- Routing: GET liest, POST legt an, PATCH ändert, DELETE löscht */

// Öffentliche Sicht auf einen Eintrag (ohne Passwort-Hash)
function link_out(string $slug, array $l, array $clicks, bool $withDays = false): array {
    $o = [
        'slug'      => $slug,
        'short_url' => api_base() . $slug,
        'url'       => (string) $l['url'],
        'group'     => (string) $l['group'],
        'title'     => (string) $l['title'],
        'expires'   => (string) $l['expires'],
        'starts'    => (string) ($l['starts'] ?? ''),
        'created'   => (int) $l['created'],
        'protected'   => ($l['pass'] ?? '') !== '',
        'preview'     => !empty($l['preview']),
        'expires_url' => (string) ($l['expires_url'] ?? ''),
        'alts'        => normalize_alts($l['alts'] ?? []),
        'clicks'      => clicks_total($clicks, $slug),
    ];
    if ($withDays) {
        $days = clicks_days($clicks, $slug);
        $o['clicks_today'] = (int) ($days[date('Y-m-d')] ?? 0);
        $o['clicks_days']  = (object) $days;
    }
    return $o;
}

$data = load_data();

if ($method === 'GET') {
    $clicks = load_clicks();
    $slug   = clean_slug((string) ($in['slug'] ?? ''));
    if ($slug !== '') {
        if (!isset($data['links'][$slug])) {
            respond(404, ['ok' => false, 'error' => 'not_found', 'slug' => $slug]);
        }
        respond(200, ['ok' => true, 'link' => link_out($slug, $data['links'][$slug], $clicks, true)]);
    }
    $out = [];
    foreach ($data['links'] as $s => $l) $out[] = link_out($s, $l, $clicks);
    respond(200, ['ok' => true, 'count' => count($out), 'groups' => $data['groups'], 'links' => $out]);
}

if ($method === 'DELETE') {
    $slug = clean_slug((string) ($in['slug'] ?? ''));
    if ($slug === '' || !isset($data['links'][$slug])) {
        respond(404, ['ok' => false, 'error' => 'not_found', 'slug' => $slug]);
    }
    // Wie im Admin: in den Papierkorb, Klick-Zähler wandert mit
    $cl = load_clicks();
    $tr = load_trash();
    $tr[$slug]            = $data['links'][$slug];
    $tr[$slug]['deleted'] = time();
    $tr[$slug]['clicks']  = $cl[$slug] ?? 0;
    unset($data['links'][$slug], $cl[$slug]);
    if (!save_data($data)) {
        respond(500, ['ok' => false, 'error' => 'write_failed', 'message' => 'Could not save – check write permissions.']);
    }
    save_clicks($cl);
    save_trash($tr);
    respond(200, ['ok' => true, 'deleted' => $slug, 'trash' => true]);
}

if ($method === 'PATCH') {
    $slug = clean_slug((string) ($in['slug'] ?? ''));
    if ($slug === '' || !isset($data['links'][$slug])) {
        respond(404, ['ok' => false, 'error' => 'not_found', 'slug' => $slug]);
    }
    $l = $data['links'][$slug];
    if (array_key_exists('url', $in)) {
        $u = trim((string) $in['url']);
        if (!valid_url($u)) {
            respond(422, ['ok' => false, 'error' => 'invalid_url', 'message' => 'Field "url" must be a valid http(s) URL.']);
        }
        $l['url'] = $u;
    }
    if (array_key_exists('title', $in))   $l['title'] = clean_title((string) $in['title']);
    if (array_key_exists('expires', $in)) $l['expires'] = clean_date((string) $in['expires']);
    if (array_key_exists('starts', $in))  $l['starts'] = clean_date((string) $in['starts']);
    if (array_key_exists('preview', $in)) $l['preview'] = filter_var($in['preview'], FILTER_VALIDATE_BOOL);
    if (array_key_exists('expires_url', $in)) {
        $eu = trim((string) $in['expires_url']);
        $l['expires_url'] = ($eu === '' || valid_url($eu)) ? $eu : ($l['expires_url'] ?? '');
    }
    if (array_key_exists('alts', $in)) {
        $l['alts'] = is_string($in['alts']) ? parse_alts_text($in['alts']) : normalize_alts($in['alts']);
    }
    if (array_key_exists('password', $in)) {
        $pw = (string) $in['password'];
        $l['pass'] = $pw === '' ? '' : password_hash($pw, PASSWORD_DEFAULT);   // "" entfernt das Passwort
    }
    if (array_key_exists('group', $in)) {
        $g = clean_group((string) $in['group']);
        if ($g !== '' && !in_array($g, $data['groups'], true)) $data['groups'][] = $g;
        $l['group'] = $g;
    }
    $new = $slug;
    if (array_key_exists('newslug', $in)) {
        $new = clean_slug((string) $in['newslug']);
        if ($new === '') {
            respond(422, ['ok' => false, 'error' => 'invalid_slug', 'message' => 'Field "newslug" must contain a-z, 0-9 or "-".']);
        }
        if (in_array($new, ['admin', 'index', 'api'], true)) {
            respond(409, ['ok' => false, 'error' => 'reserved_slug', 'slug' => $new, 'message' => 'This slug is reserved.']);
        }
        if ($new !== $slug && isset($data['links'][$new])) {
            respond(409, ['ok' => false, 'error' => 'slug_taken', 'slug' => $new, 'message' => 'Slug already in use.']);
        }
    }
    if ($new === $slug) {
        $data['links'][$slug] = $l;
    } else {
        // Schlüssel umbenennen (Reihenfolge erhalten), Klick-Zähler mitnehmen
        $rebuilt = [];
        foreach ($data['links'] as $k => $v) $rebuilt[$k === $slug ? $new : $k] = $k === $slug ? $l : $v;
        $data['links'] = $rebuilt;
        $cl = load_clicks();
        if (isset($cl[$slug])) { $cl[$new] = $cl[$slug]; unset($cl[$slug]); save_clicks($cl); }
    }
    if (!save_data($data)) {
        respond(500, ['ok' => false, 'error' => 'write_failed', 'message' => 'Could not save – check write permissions.']);
    }
    respond(200, ['ok' => true, 'link' => link_out($new, $data['links'][$new], load_clicks())]);
}

if ($method !== 'POST') {
    header('Allow: GET, POST, PATCH, DELETE');
    respond(405, ['ok' => false, 'error' => 'method_not_allowed', 'message' => 'Use GET, POST, PATCH or DELETE.']);
}

/* ---- POST: Kurz-URL anlegen ---------------------------------------- */

$url     = trim((string) ($in['url'] ?? ''));
$slug    = clean_slug((string) ($in['slug'] ?? ''));
$group   = clean_group((string) ($in['group'] ?? ''));
$title   = clean_title((string) ($in['title'] ?? ''));
$expires = clean_date((string) ($in['expires'] ?? ''));
$starts  = clean_date((string) ($in['starts'] ?? ''));
$linkpw  = (string) ($in['password'] ?? '');   // optional: Link-Passwort (wird gehasht)

if (!valid_url($url)) {
    respond(422, ['ok' => false, 'error' => 'invalid_url', 'message' => 'Field "url" must be a valid http(s) URL.']);
}

if ($slug === '') $slug = random_slug($data['links']);
if (in_array($slug, ['admin', 'index', 'api'], true)) {
    respond(409, ['ok' => false, 'error' => 'reserved_slug', 'slug' => $slug, 'message' => 'This slug is reserved.']);
}
if (isset($data['links'][$slug])) {
    respond(409, ['ok' => false, 'error' => 'slug_taken', 'slug' => $slug, 'message' => 'Slug already in use.']);
}
if ($group !== '' && !in_array($group, $data['groups'], true)) $data['groups'][] = $group;

$expUrlIn = trim((string) ($in['expires_url'] ?? ''));
if (!valid_url($expUrlIn)) $expUrlIn = '';
$altsIn = $in['alts'] ?? null;
$altsIn = is_string($altsIn) ? parse_alts_text($altsIn) : normalize_alts(is_array($altsIn) ? $altsIn : []);
$data['links'][$slug] = ['url' => $url, 'group' => $group, 'title' => $title,
                         'expires' => $expires, 'starts' => $starts, 'created' => time(),
                         'pass' => ($linkpw !== '') ? password_hash($linkpw, PASSWORD_DEFAULT) : '',
                         'preview' => filter_var($in['preview'] ?? false, FILTER_VALIDATE_BOOL),
                         'expires_url' => $expUrlIn, 'alts' => $altsIn];

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
    'expires'     => $expires,
    'starts'      => $starts,
    'alts'        => $altsIn,
    'protected'   => ($linkpw !== ''),
    'preview'     => filter_var($in['preview'] ?? false, FILTER_VALIDATE_BOOL),
    'expires_url' => $expUrlIn,
]);
