<?php
declare(strict_types=1);

@ini_set('display_errors', '0');   // Produktion: keine PHP-Fehler an Besucher ausgeben

/* ------------------------------------------------------------------ *
 *  GOTO  –  URL-Weiterleitungen & QR-Codes  –  Admin
 *
 *  Datenmodell (urls.json):
 *    { "groups": ["Projekt A"],
 *      "links": { "slug": {
 *         "url":"", "group":"", "title":"", "expires":"YYYY-MM-DD", "created":0
 *      } } }
 *
 *  Klicks (clicks.json):  { "slug": 42 }   – reine Zähler, DSGVO-konform,
 *  keine IPs / Zeitstempel / User-Agents.
 * ------------------------------------------------------------------ */

$cfg = require __DIR__ . '/config.php';

// Datenverzeichnis – idealerweise außerhalb des Web-Roots (siehe config.php).
$dataDir = rtrim((string) ($cfg['data_dir'] ?? __DIR__), '/');
define('URLS_FILE',     $dataDir . '/urls.json');
define('CLICKS_FILE',   $dataDir . '/clicks.json');
define('ATTEMPTS_FILE', $dataDir . '/.ht_attempts.json');
define('TOKENS_FILE',   $dataDir . '/.ht_tokens.json');
define('AUTH_FILE',     $dataDir . '/.ht_auth.json');

const REMEMBER_COOKIE = 'goto_remember';
const REMEMBER_TTL    = 2592000;   // „Angemeldet bleiben" – 30 Tage

$idleTimeout    = (int) ($cfg['idle_timeout']    ?? 1800);
$maxAttempts    = (int) ($cfg['max_attempts']    ?? 5);
$lockoutSeconds = (int) ($cfg['lockout_seconds'] ?? 900);
$self           = (string) ($_SERVER['SCRIPT_NAME'] ?? '/admin.php');

/* ---------- Helfer ------------------------------------------------- */

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function load_json(string $file): array {
    if (!is_file($file)) return [];
    $data = json_decode((string) file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function save_json(string $file, array $data): bool {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return @file_put_contents($file, $json, LOCK_EX) !== false;
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

function is_expired(string $expires): bool {
    return $expires !== '' && $expires < date('Y-m-d');
}

function host_of(string $url): string {
    return (string) parse_url($url, PHP_URL_HOST);
}

function random_slug(array $links): string {
    $alphabet = 'abcdefghijkmnpqrstuvwxyz23456789';   // ohne l,o,0,1
    do {
        $s = '';
        for ($i = 0; $i < 6; $i++) $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    } while (isset($links[$s]));
    return $s;
}

/* ---- Datenmodell -------------------------------------------------- */

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

function load_data(): array   { return normalize_data(load_json(URLS_FILE)); }
function save_data(array $d): bool {
    return save_json(URLS_FILE, ['groups' => array_values($d['groups'] ?? []), 'links' => $d['links'] ?? []]);
}
function load_clicks(): array  { return load_json(CLICKS_FILE); }
function save_clicks(array $c): bool { return save_json(CLICKS_FILE, $c); }

// Passwort-Hash aus der Auth-Datei (vom Setup automatisch geschrieben)
function stored_hash(): string { return (string) (load_json(AUTH_FILE)['hash'] ?? ''); }
function save_hash(string $hash): bool { return save_json(AUTH_FILE, ['hash' => $hash]); }

/* ---- Flash / Redirect / CSRF -------------------------------------- */

function flash(string $msg, string $type = 'error'): void {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}
function redirect(string $to): void { header('Location: ' . $to); exit; }

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_ok(): bool {
    return !empty($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''));
}

/* ---- Remember-Me (Selector:Validator, nur Hash gespeichert) ------- */

function remember_purge(array $t): array {
    $now = time();
    foreach ($t as $s => $r) if (($r['exp'] ?? 0) < $now) unset($t[$s]);
    return $t;
}
function remember_cookie(string $value, int $expires, string $cookieDir, bool $https): void {
    setcookie(REMEMBER_COOKIE, $value, [
        'expires'  => $expires, 'path' => $cookieDir,
        'httponly' => true, 'secure' => $https, 'samesite' => 'Lax',
    ]);
}
function remember_set(string $cookieDir, bool $https): void {
    $sel = bin2hex(random_bytes(9));
    $val = bin2hex(random_bytes(32));
    $t = remember_purge(load_json(TOKENS_FILE));
    $t[$sel] = ['h' => hash('sha256', $val), 'exp' => time() + REMEMBER_TTL];
    save_json(TOKENS_FILE, $t);
    remember_cookie($sel . ':' . $val, time() + REMEMBER_TTL, $cookieDir, $https);
}
function remember_forget(string $cookieDir, bool $https): void {
    $raw = (string) ($_COOKIE[REMEMBER_COOKIE] ?? '');
    if ($raw !== '' && strpos($raw, ':') !== false) {
        [$sel] = explode(':', $raw, 2);
        $t = load_json(TOKENS_FILE);
        unset($t[$sel]);
        save_json(TOKENS_FILE, $t);
    }
    remember_cookie('', time() - 86400, $cookieDir, $https);
}
function remember_try(string $cookieDir, bool $https): bool {
    $raw = (string) ($_COOKIE[REMEMBER_COOKIE] ?? '');
    if ($raw === '' || strpos($raw, ':') === false) return false;
    [$sel, $val] = explode(':', $raw, 2);
    $t   = load_json(TOKENS_FILE);
    $rec = $t[$sel] ?? null;
    if (!$rec || ($rec['exp'] ?? 0) < time()
        || !hash_equals((string) ($rec['h'] ?? ''), hash('sha256', $val))) {
        return false;
    }
    // Token bei jeder Nutzung rotieren
    $nv = bin2hex(random_bytes(32));
    $t = remember_purge($t);
    $t[$sel] = ['h' => hash('sha256', $nv), 'exp' => time() + REMEMBER_TTL];
    save_json(TOKENS_FILE, $t);
    remember_cookie($sel . ':' . $nv, time() + REMEMBER_TTL, $cookieDir, $https);
    return true;
}

/* ---- Brute-Force-Bremse (pro IP, dateibasiert) -------------------- */

function lock_remaining(string $key): int {
    $rec = load_json(ATTEMPTS_FILE)[$key] ?? null;
    return $rec ? max(0, (int) ($rec['until'] ?? 0) - time()) : 0;
}
function register_fail(string $key, int $max, int $lockSecs): void {
    $now = time();
    $all = load_json(ATTEMPTS_FILE);
    foreach ($all as $k => $r) {
        if (($r['until'] ?? 0) < $now && ($r['count'] ?? 0) === 0) unset($all[$k]);
    }
    $rec = $all[$key] ?? ['count' => 0, 'until' => 0];
    $rec['count']++;
    if ($rec['count'] >= $max) $rec = ['count' => 0, 'until' => $now + $lockSecs];
    $all[$key] = $rec;
    save_json(ATTEMPTS_FILE, $all);
}
function clear_fails(string $key): void {
    $all = load_json(ATTEMPTS_FILE);
    unset($all[$key]);
    save_json(ATTEMPTS_FILE, $all);
}

/* ---------- Session (gehärtet) ------------------------------------- */

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443')
      || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
$cookieDir = rtrim(dirname($self), '/') . '/';
$self      = $cookieDir . 'admin';   // schöne URL (…/admin), Ordnername egal

// Kanonische URL: direkter Aufruf von admin.php -> /admin (ohne .php)
$reqPath = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
if (preg_match('#/admin\.php$#', $reqPath)) {
    header('Location: ' . $self, true, 301);
    exit;
}

session_set_cookie_params([
    'lifetime' => 0, 'path' => $cookieDir, 'httponly' => true,
    'secure' => $https, 'samesite' => 'Strict',
]);
session_name('goto_sid');
session_start();

/* ---------- Security-Header (vor jeglicher Ausgabe) ---------------- */

$nonce = base64_encode(random_bytes(16));
header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
if ($https) header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header(
    "Content-Security-Policy: default-src 'none'; " .
    "img-src 'self' https://icons.duckduckgo.com data:; " .
    "style-src 'nonce-$nonce'; script-src 'self' 'nonce-$nonce'; " .
    "form-action 'self'; base-uri 'none'; frame-ancestors 'none'"
);

$clientKey = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli'));
$flashMsg  = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$base = ($https ? 'https' : 'http') . '://'
      . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $cookieDir;

/* ---------- Layout ------------------------------------------------- */

function head(string $title, string $nonce): void { ?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($title) ?></title>
<script nonce="<?= $nonce ?>">(function(){try{var t=localStorage.getItem('goto-theme');if(t&&t!=='system')document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
<style nonce="<?= $nonce ?>">
:root{
  --bg:#f4f5f7; --card:#fff; --fg:#1f2328; --muted:#6b7280;
  --line:#ebedf0; --line-strong:#dcdfe4;
  --blue:#2563eb; --blue-dark:#1d4ed8; --red:#dc2626; --red-dark:#b91c1c;
  --ok-bg:#ecfdf5; --ok-fg:#047857; --ok-line:#a7f3d0;
  --err-bg:#fef2f2; --err-fg:#b91c1c; --err-line:#fecaca;
  --info-bg:#eff6ff; --info-fg:#1e40af; --info-line:#bfdbfe;
  --g1:#6366f1; --g2:#2563eb;
  --radius:14px; --radius-sm:9px; --ring:rgba(37,99,235,.18);
  --shadow:0 1px 2px rgba(16,24,40,.04), 0 6px 20px rgba(16,24,40,.06);
}
/* Dunkel-Variablen */
@media (prefers-color-scheme:dark){
  :root:not([data-theme="light"]){
    --bg:#0e1014; --card:#171a21; --fg:#e7e9ec; --muted:#99a1ad;
    --line:#242935; --line-strong:#343b49;
    --blue:#3b82f6; --blue-dark:#2563eb; --red:#ef4444; --red-dark:#dc2626;
    --ok-bg:#0c2c22; --ok-fg:#4ade80; --ok-line:#14543c;
    --err-bg:#2a1416; --err-fg:#fca5a5; --err-line:#5b1d1d;
    --info-bg:#0f1f3a; --info-fg:#93c5fd; --info-line:#1e3a64;
    --g1:#a5b4fc; --g2:#60a5fa; --ring:rgba(59,130,246,.25);
    --shadow:0 1px 2px rgba(0,0,0,.3), 0 6px 20px rgba(0,0,0,.35);
  }
}
:root[data-theme="dark"]{
  --bg:#0e1014; --card:#171a21; --fg:#e7e9ec; --muted:#99a1ad;
  --line:#242935; --line-strong:#343b49;
  --blue:#3b82f6; --blue-dark:#2563eb; --red:#ef4444; --red-dark:#dc2626;
  --ok-bg:#0c2c22; --ok-fg:#4ade80; --ok-line:#14543c;
  --err-bg:#2a1416; --err-fg:#fca5a5; --err-line:#5b1d1d;
  --info-bg:#0f1f3a; --info-fg:#93c5fd; --info-line:#1e3a64;
  --g1:#a5b4fc; --g2:#60a5fa; --ring:rgba(59,130,246,.25);
  --shadow:0 1px 2px rgba(0,0,0,.3), 0 6px 20px rgba(0,0,0,.35);
}
*{box-sizing:border-box}
html{background:var(--bg)}
body{font:15.5px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
     color:var(--fg);background:var(--bg);max-width:1140px;margin:0 auto;
     padding:2.5rem 1.5rem 4rem;-webkit-font-smoothing:antialiased}
a{color:var(--blue)}
h1{font-size:1.45rem;margin:0;letter-spacing:-.015em}
.muted{color:var(--muted);font-size:.85rem}
.sub{margin:.15rem 0 0;color:var(--muted);font-size:.85rem}

.topbar{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1.75rem}
.brand{display:flex;align-items:center;gap:.85rem}
.brand-mark{display:inline-flex;align-items:center;justify-content:center;width:44px;height:44px;
     border-radius:13px;background:linear-gradient(135deg,var(--g1),var(--g2));color:#fff;
     flex:0 0 auto;box-shadow:0 4px 14px rgba(37,99,235,.35)}
.brand-mark svg{width:24px;height:24px}
.brand h1{font-weight:800;font-size:1.6rem;letter-spacing:.04em;line-height:1;
     background:linear-gradient(135deg,var(--g1),var(--g2));-webkit-background-clip:text;
     background-clip:text;color:transparent}
.ico{width:16px;height:16px;flex:0 0 auto;display:inline-block;vertical-align:middle}

.panel,.group{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);
     box-shadow:var(--shadow);padding:1.15rem 1.2rem;margin-bottom:1.1rem}

.bar{display:flex;gap:.6rem;flex-wrap:wrap;align-items:center}
.bar+.bar{margin-top:.65rem}
.bar .grow{flex:1;min-width:8rem}
.bar .grow2{flex:2;min-width:11rem}
.bar input[type=date]{width:11rem;flex:0 0 auto}
.bar--end{align-items:flex-end}
.panel>form+form{margin-top:.85rem;padding-top:.85rem;border-top:1px solid var(--line)}
.field{display:flex;flex-direction:column;gap:.3rem;min-width:0}
.field>input,.field>select{width:100%}
.field-label{font-size:.74rem;font-weight:600;letter-spacing:.02em;color:var(--muted);padding-left:.15rem}
.field--group{flex:0 0 12rem}
.field--date{flex:0 0 13rem}
.datefield{display:flex;gap:.4rem;align-items:stretch}
.datefield input[type=date]{flex:1;width:auto}
.datefield-clear{flex:0 0 auto;padding:.45rem .55rem}

/* Bearbeiten-Ansicht: gestapelte Felder, Ziel-URL volle Breite */
.editgrid{display:flex;flex-direction:column;gap:.7rem;padding:.4rem 0}
.editgrid .field>input{width:100%}
.erow{display:flex;gap:.7rem;flex-wrap:wrap;align-items:flex-end}
.erow .field{flex:1;min-width:11rem}
.erow .field--date{flex:0 0 13rem}
.erow--actions{gap:.4rem}

input[type=text],input[type=password],input[type=date],input[type=number],select{
     width:100%;padding:.6rem .7rem;border:1px solid var(--line-strong);border-radius:var(--radius-sm);
     font:inherit;background:var(--card);color:var(--fg);transition:border-color .15s,box-shadow .15s}
input::placeholder{color:var(--muted);opacity:.8}
input:focus,select:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px var(--ring)}
select{max-width:12rem;cursor:pointer}
input[type=file]{font:inherit;font-size:.85rem;color:var(--muted)}

.btn{font:inherit;font-weight:500;font-size:.85rem;padding:.55rem .9rem;border:1px solid transparent;
     border-radius:var(--radius-sm);cursor:pointer;text-decoration:none;display:inline-flex;
     align-items:center;justify-content:center;gap:.35rem;line-height:1;white-space:nowrap;
     transition:background .15s,border-color .15s,color .15s,transform .04s}
.btn:active{transform:translateY(1px)}
.btn--primary{background:var(--blue);color:#fff}
.btn--primary:hover{background:var(--blue-dark)}
.btn--danger{background:var(--red);color:#fff}
.btn--danger:hover{background:var(--red-dark)}
.btn--ghost{background:transparent;color:var(--fg);border-color:var(--line-strong)}
.btn--ghost:hover{background:var(--bg);border-color:var(--muted)}
.btn--small{padding:.42rem .68rem;font-size:.78rem}
.btn.ok{color:var(--ok-fg);border-color:var(--ok-line);background:var(--ok-bg)}

.flash{padding:.75rem .95rem;border-radius:var(--radius-sm);margin-bottom:1.25rem;font-size:.9rem;
     border:1px solid transparent}
.flash.success{background:var(--ok-bg);color:var(--ok-fg);border-color:var(--ok-line)}
.flash.error{background:var(--err-bg);color:var(--err-fg);border-color:var(--err-line)}
.flash.info{background:var(--info-bg);color:var(--info-fg);border-color:var(--info-line)}

.bulkbar{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;padding:.7rem 1.2rem}
.bulkbar .bulk-spacer{flex:1 1 auto;min-width:1rem}
.bulkbar select{max-width:11rem}

.group-head{display:flex;align-items:center;justify-content:space-between;gap:.5rem;margin-bottom:.35rem}
.group-head h2{font-size:1rem;margin:0;display:flex;align-items:center;gap:.55rem}
.count{display:inline-flex;align-items:center;justify-content:center;min-width:1.45rem;height:1.45rem;
     padding:0 .45rem;font-size:.72rem;font-weight:600;color:var(--muted);background:var(--bg);
     border:1px solid var(--line);border-radius:999px}
.group-actions{display:flex;gap:.35rem;align-items:center}

table{width:100%;border-collapse:collapse;margin-top:.35rem}
td{padding:.55rem .5rem;border-top:1px solid var(--line);vertical-align:middle}
tr:first-child td{border-top:0}
table tr:hover td{background:color-mix(in srgb,var(--bg) 60%,transparent)}
.cbcell{width:1%;text-align:center}
.rowchk{width:auto;cursor:pointer}
.linkcell{display:flex;align-items:center;gap:.55rem}
.fav{width:16px;height:16px;border-radius:3px;flex:0 0 auto;background:var(--bg)}
.fav.hide{visibility:hidden}
.linkmeta{display:flex;flex-direction:column;min-width:0}
.linkmeta .slug{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.83rem;color:var(--blue);text-decoration:none}
.linkmeta .slug:hover{text-decoration:underline}
.ltitle{font-size:.78rem;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:16rem}
.target{max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--muted);font-size:.85rem}
.chip{display:inline-flex;align-items:center;gap:.25rem;font-size:.72rem;padding:.08rem .4rem;border-radius:999px;
     border:1px solid var(--line);margin-left:.4rem;white-space:nowrap;vertical-align:middle}
.chip .ico{width:12px;height:12px}
.chip--date{color:var(--muted);background:var(--bg)}
.chip--exp{color:var(--err-fg);background:var(--err-bg);border-color:var(--err-line)}
.clickcell{width:1%}
.clicks{display:inline-flex;align-items:center;gap:.3rem;color:var(--muted);font-size:.82rem;font-variant-numeric:tabular-nums}
.clicks .ico{width:14px;height:14px}
.actions{white-space:nowrap;text-align:right}
.actions .btn{margin-left:.2rem;padding:.42rem .5rem}
.movecell{width:1%;white-space:nowrap}
.movecell select{min-width:9.5rem;max-width:12rem;padding:.5rem .6rem;font-size:.82rem}
.editbar{gap:.5rem}
.inline{display:inline}
.empty{margin:.5rem 0 0}

.card{max-width:360px;background:var(--card);border:1px solid var(--line);border-radius:var(--radius);
     box-shadow:var(--shadow);padding:1.5rem}
.card label{display:block;font-size:.85rem;margin-bottom:.4rem;color:var(--muted)}
.card .btn{margin-top:1rem;width:100%}
code{background:var(--bg);border:1px solid var(--line);padding:.12rem .4rem;border-radius:6px;
     font-size:.82em;font-family:ui-monospace,Menlo,Consolas,monospace}
.hashbox{width:100%;padding:.75rem;border:1px solid var(--line-strong);border-radius:var(--radius-sm);
     font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.8rem;background:var(--bg);color:var(--fg)}

.tools{margin-top:1.5rem}
.tools summary{cursor:pointer;color:var(--muted);font-size:.9rem;font-weight:500;padding:.3rem 0;
     list-style:none;display:inline-flex;align-items:center;gap:.4rem}
.tools summary::-webkit-details-marker{display:none}
.tools summary::before{content:"›";display:inline-block;transition:transform .15s;font-size:1.1em}
.tools[open] summary::before{transform:rotate(90deg)}
.tools[open]{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);
     box-shadow:var(--shadow);padding:1.1rem 1.2rem}
.tools .bar{margin-top:.85rem}
.chk{display:inline-flex;align-items:center;gap:.4rem;font-size:.85rem;color:var(--muted);white-space:nowrap}
.chk input{width:auto}
.card .chk--remember{display:flex;align-items:center;gap:.5rem;margin:.9rem 0 0;color:var(--muted)}
.card .chk--remember input{width:auto}

/* Inline-Validierung */
input.valid{border-color:#16a34a}
input.invalid{border-color:#dc2626}
input.valid:focus{box-shadow:0 0 0 3px rgba(22,163,74,.18)}
input.invalid:focus{box-shadow:0 0 0 3px rgba(220,38,38,.18)}

/* Live-Suche */
.search{position:relative;margin-bottom:1.1rem}
.search .ico{position:absolute;left:.85rem;top:50%;transform:translateY(-50%);width:17px;height:17px;
     color:var(--muted);pointer-events:none}
.search input{width:100%;padding:.65rem .8rem .65rem 2.4rem;border:1px solid var(--line-strong);
     border-radius:var(--radius-sm);font:inherit;background:var(--card);color:var(--fg);box-shadow:var(--shadow)}
.search input:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px var(--ring)}

/* Drag & Drop */
tr[draggable=true]{cursor:grab}
tr.dragging{opacity:.4}
section.group.dropok{outline:2px dashed var(--blue);outline-offset:3px}

/* Toasts */
.toasts{position:fixed;top:1rem;right:1rem;z-index:60;display:flex;flex-direction:column;gap:.6rem;
     max-width:min(360px,90vw)}
.toast{padding:.7rem .95rem;border-radius:var(--radius-sm);font-size:.88rem;border:1px solid transparent;
     box-shadow:0 8px 24px rgba(16,24,40,.16);cursor:pointer;opacity:0;transform:translateX(24px);
     transition:opacity .3s,transform .3s}
.toast--in{opacity:1;transform:none}
.toast--out{opacity:0;transform:translateX(24px)}
.toast--success{background:var(--ok-bg);color:var(--ok-fg);border-color:var(--ok-line)}
.toast--error{background:var(--err-bg);color:var(--err-fg);border-color:var(--err-line)}
.toast--info{background:var(--info-bg);color:var(--info-fg);border-color:var(--info-line)}

dialog.qrdlg{border:none;border-radius:var(--radius);padding:0;width:min(560px,94vw);
     background:var(--card);color:var(--fg);box-shadow:0 24px 70px rgba(0,0,0,.4)}
dialog.qrdlg::backdrop{background:rgba(10,12,16,.55)}
.qrdlg-head{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.2rem;border-bottom:1px solid var(--line)}
.qrdlg-head strong{font-size:1rem}
.qrdlg-body{display:flex;gap:1.2rem;padding:1.2rem;flex-wrap:wrap}
.qrprev{flex:0 0 200px;width:200px;height:200px;display:flex;align-items:center;justify-content:center;
     background:#fff;border:1px solid var(--line);border-radius:var(--radius-sm);overflow:hidden}
.qrprev canvas{image-rendering:pixelated;max-width:100%;height:auto}
.qropts{flex:1;min-width:200px;display:flex;flex-direction:column;gap:.7rem}
.qropts label{display:flex;flex-direction:column;gap:.3rem;font-size:.8rem;color:var(--muted)}
.qrrow{display:flex;gap:.6rem}
.qrrow label{flex:1}
.qropts input[type=color]{height:38px;padding:.2rem;cursor:pointer;border:1px solid var(--line-strong);
     border-radius:var(--radius-sm);background:var(--card);width:100%}
.qropts select,.qropts input{max-width:none}
.qrurl{word-break:break-all;margin:.2rem 0 0}
.qrdl{display:flex;gap:.5rem;margin-top:.2rem}

/* Kopfzeile rechts: Theme-Umschalter + Abmelden */
.topbar-actions{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;justify-content:flex-end}
#theme{width:auto;min-width:7rem;max-width:none;padding:.42rem .6rem;font-size:.82rem}

/* ====== Responsive ====== */
@media (max-width:760px){
  body{padding:1.5rem 1rem 3rem;font-size:15px}
  .panel,.group,.tools[open]{padding:1rem}
  .brand h1{font-size:1.35rem}
  .brand-mark{width:38px;height:38px}
  /* Anlege-Felder volle Breite */
  .bar .grow,.bar .grow2,.field--group,.field--date{flex:1 1 100%}
  .field--group select,.field--date,.datefield{max-width:none}
  /* Tabellen werden zu Karten */
  table,tbody{display:block}
  tr{display:block;background:var(--bg);border:1px solid var(--line);border-radius:var(--radius-sm);
     padding:.7rem .8rem;margin-bottom:.6rem}
  table tr:hover td{background:none}
  td{display:block;border:0;padding:.2rem 0}
  .cbcell{display:inline-block;width:auto;margin-right:.5rem;vertical-align:middle}
  .clickcell,.movecell{display:inline-block;width:auto;vertical-align:middle}
  .clickcell{margin-right:1rem}
  .movecell select{min-width:0;width:auto;max-width:none}
  .target{white-space:normal;max-width:none;word-break:break-all}
  .actions{text-align:left;margin-top:.55rem}
  .actions .btn{margin:0 .4rem 0 0}
  .ltitle{max-width:none}
  .editgrid .erow .field,.editgrid .erow .field--date{flex:1 1 100%}
  .group-head{flex-wrap:wrap}
}
</style>
</head>
<body>
<?php }

function foot(string $nonce): void {
    $dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/') . '/';
    ?>
<script src="<?= e($dir) ?>qr.js" nonce="<?= $nonce ?>"></script>
<script nonce="<?= $nonce ?>">
(function(){
  document.querySelectorAll('form[data-confirm]').forEach(function(f){
    f.addEventListener('submit',function(e){ if(!confirm(f.getAttribute('data-confirm'))) e.preventDefault(); });
  });
  document.querySelectorAll('button[data-confirm]').forEach(function(b){
    b.addEventListener('click',function(e){ if(!confirm(b.getAttribute('data-confirm'))) e.preventDefault(); });
  });
  document.querySelectorAll('[data-copy]').forEach(function(b){
    b.addEventListener('click',function(){
      navigator.clipboard.writeText(b.getAttribute('data-copy')).then(function(){
        b.classList.add('ok'); setTimeout(function(){ b.classList.remove('ok'); },1200);
      });
    });
  });
  document.querySelectorAll('select[data-autosubmit]').forEach(function(s){
    s.addEventListener('change',function(){ s.form.submit(); });
  });
  document.querySelectorAll('img.fav').forEach(function(im){
    im.addEventListener('error',function(){ im.classList.add('hide'); });
  });
  // Theme-Umschalter (System / Hell / Dunkel)
  var themeSel=document.getElementById('theme');
  if(themeSel){
    var cur='system'; try{ cur=localStorage.getItem('goto-theme')||'system'; }catch(e){}
    themeSel.value=cur;
    themeSel.addEventListener('change',function(){
      var v=themeSel.value;
      try{ localStorage.setItem('goto-theme',v); }catch(e){}
      if(v==='system') document.documentElement.removeAttribute('data-theme');
      else document.documentElement.setAttribute('data-theme',v);
    });
  }
  // Ablaufdatum zurücksetzen
  document.querySelectorAll('[data-clear-date]').forEach(function(b){
    b.addEventListener('click',function(){
      var f=b.closest('.datefield'), inp=f&&f.querySelector('input[type=date]');
      if(inp){ inp.value=''; inp.focus(); }
    });
  });

  // Bulk-Auswahl
  var boxes=Array.prototype.slice.call(document.querySelectorAll('.rowchk'));
  var selall=document.getElementById('selall'), selcount=document.getElementById('selcount');
  function upd(){ var n=boxes.filter(function(b){return b.checked;}).length;
    if(selcount) selcount.textContent=n+' markiert';
    if(selall) selall.checked=(n>0&&n===boxes.length); }
  boxes.forEach(function(b){ b.addEventListener('change',upd); });
  if(selall) selall.addEventListener('change',function(){ boxes.forEach(function(b){ b.checked=selall.checked; }); upd(); });
  var bulk=document.getElementById('bulk');
  if(bulk) bulk.addEventListener('submit',function(e){
    if(!boxes.some(function(b){return b.checked;})){ e.preventDefault(); alert('Bitte zuerst Links markieren.'); }
  });

  // Inline-URL-Validierung
  function isUrl(v){ try{ var u=new URL(v); return u.protocol==='http:'||u.protocol==='https:'; }catch(_){ return false; } }
  document.querySelectorAll('input[name=url]').forEach(function(inp){
    function chk(){ var v=inp.value.trim();
      inp.classList.toggle('valid', v!=='' && isUrl(v));
      inp.classList.toggle('invalid', v!=='' && !isUrl(v)); }
    inp.addEventListener('input',chk); chk();
  });

  // Live-Suche
  var search=document.getElementById('search');
  if(search){
    var rows=Array.prototype.slice.call(document.querySelectorAll('tr[data-search]'));
    var secs=Array.prototype.slice.call(document.querySelectorAll('section.group[data-group]'));
    var nores=document.getElementById('noresults');
    search.addEventListener('input',function(){
      var q=search.value.trim().toLowerCase(), any=false;
      rows.forEach(function(r){ var m=(q===''||r.getAttribute('data-search').indexOf(q)>=0);
        r.hidden=!m; if(m) any=true; });
      secs.forEach(function(s){ var vis=s.querySelectorAll('tr[data-search]:not([hidden])').length;
        s.hidden=(q!==''&&vis===0); });
      if(nores) nores.hidden=!(q!==''&&!any);
    });
  }

  // Drag & Drop zwischen Gruppen
  var dnd=document.getElementById('dndform');
  if(dnd){
    var dragSlug=null;
    document.querySelectorAll('tr[data-slug]').forEach(function(tr){
      tr.addEventListener('dragstart',function(e){
        if(e.target.closest('input,select,button,a,.movecell')){ e.preventDefault(); return; }
        dragSlug=tr.getAttribute('data-slug'); tr.classList.add('dragging');
        e.dataTransfer.effectAllowed='move';
        try{ e.dataTransfer.setData('text/plain',dragSlug); }catch(_){}
      });
      tr.addEventListener('dragend',function(){ tr.classList.remove('dragging'); dragSlug=null;
        document.querySelectorAll('.dropok').forEach(function(s){ s.classList.remove('dropok'); }); });
    });
    document.querySelectorAll('section.group[data-group]').forEach(function(sec){
      sec.addEventListener('dragover',function(e){ if(dragSlug!==null){ e.preventDefault(); sec.classList.add('dropok'); } });
      sec.addEventListener('dragleave',function(e){ if(!sec.contains(e.relatedTarget)) sec.classList.remove('dropok'); });
      sec.addEventListener('drop',function(e){ e.preventDefault(); if(dragSlug===null) return;
        dnd.querySelector('[name=slug]').value=dragSlug;
        dnd.querySelector('[name=group]').value=sec.getAttribute('data-group');
        dnd.submit();
      });
    });
  }

  // Toasts
  var tc=document.getElementById('toasts');
  if(tc){
    Array.prototype.slice.call(tc.children).forEach(function(t,i){
      requestAnimationFrame(function(){ t.classList.add('toast--in'); });
      var to=setTimeout(function(){ hideToast(t); }, 4200+i*250);
      t.addEventListener('click',function(){ clearTimeout(to); hideToast(t); });
    });
  }
  function hideToast(t){ t.classList.add('toast--out'); setTimeout(function(){ if(t.parentNode) t.remove(); },350); }

  // QR-Dialog
  var dlg=document.getElementById('qrdlg');
  if(dlg && window.QRCodeGen){
    var prev=document.getElementById('qrPrev'),
        elEcl=document.getElementById('qrEcl'), elScale=document.getElementById('qrScale'),
        elMargin=document.getElementById('qrMargin'), elFg=document.getElementById('qrFg'),
        elBg=document.getElementById('qrBg'), elUrl=document.getElementById('qrUrl'),
        elTitle=document.getElementById('qrTitle');
    var cur={url:'',slug:'qr'};
    function num(el,d){ var v=parseInt(el.value,10); return isNaN(v)?d:v; }
    function draw(qr,scale,margin){
      var n=qr.size, dim=(n+margin*2)*scale;
      var c=document.createElement('canvas'); c.width=dim; c.height=dim;
      var x2=c.getContext('2d');
      x2.fillStyle=elBg.value; x2.fillRect(0,0,dim,dim);
      x2.fillStyle=elFg.value;
      for(var y=0;y<n;y++) for(var x=0;x<n;x++) if(qr.modules[y][x])
        x2.fillRect((x+margin)*scale,(y+margin)*scale,scale,scale);
      return c;
    }
    function svg(qr,scale,margin){
      var n=qr.size, dim=(n+margin*2)*scale, r='';
      for(var y=0;y<n;y++){ var x=0; while(x<n){ if(qr.modules[y][x]){ var w=1;
        while(x+w<n&&qr.modules[y][x+w]) w++;
        r+='<rect x="'+((x+margin)*scale)+'" y="'+((y+margin)*scale)+'" width="'+(w*scale)+'" height="'+scale+'"/>'; x+=w;
      } else x++; } }
      return '<svg xmlns="http://www.w3.org/2000/svg" width="'+dim+'" height="'+dim+'" viewBox="0 0 '+dim+' '+dim+'" shape-rendering="crispEdges">'
        +'<rect width="'+dim+'" height="'+dim+'" fill="'+elBg.value+'"/><g fill="'+elFg.value+'">'+r+'</g></svg>';
    }
    function dl(name,blob){ var u=URL.createObjectURL(blob),a=document.createElement('a');
      a.href=u; a.download=name; document.body.appendChild(a); a.click();
      setTimeout(function(){ URL.revokeObjectURL(u); a.remove(); },150); }
    function render(){
      if(!cur.url) return;
      try{ var qr=QRCodeGen.encode(cur.url, elEcl.value), m=Math.max(0,num(elMargin,4));
        var ps=Math.max(1,Math.floor(200/(qr.size+m*2)));
        prev.innerHTML=''; prev.appendChild(draw(qr,ps,m));
      }catch(err){ prev.textContent=err.message; }
    }
    document.querySelectorAll('[data-qr]').forEach(function(b){
      b.addEventListener('click',function(){
        cur.url=b.getAttribute('data-qr'); cur.slug=b.getAttribute('data-slug')||'qr';
        elTitle.textContent='QR-Code: '+cur.slug; elUrl.textContent=cur.url;
        render(); if(dlg.showModal) dlg.showModal(); else dlg.setAttribute('open','');
      });
    });
    [elEcl,elScale,elMargin,elFg,elBg].forEach(function(el){ el.addEventListener('input',render); });
    document.getElementById('qrClose').addEventListener('click',function(){ dlg.close(); });
    dlg.addEventListener('click',function(e){ if(e.target===dlg) dlg.close(); });
    document.getElementById('qrPng').addEventListener('click',function(){
      try{ var qr=QRCodeGen.encode(cur.url,elEcl.value);
        draw(qr,Math.max(2,num(elScale,8)),Math.max(0,num(elMargin,4)))
          .toBlob(function(bl){ dl('qr-'+cur.slug+'.png',bl); },'image/png');
      }catch(err){ alert(err.message); }
    });
    document.getElementById('qrSvg').addEventListener('click',function(){
      try{ var qr=QRCodeGen.encode(cur.url,elEcl.value);
        dl('qr-'+cur.slug+'.svg', new Blob([svg(qr,Math.max(2,num(elScale,8)),Math.max(0,num(elMargin,4)))],{type:'image/svg+xml'}));
      }catch(err){ alert(err.message); }
    });
  }
})();
</script>
</body>
</html>
<?php }

function render_toasts(array $toasts): void {
    echo '<div id="toasts" class="toasts" aria-live="polite">';
    foreach ($toasts as $t) {
        echo '<div class="toast toast--' . e($t['type']) . '">' . e($t['msg']) . '</div>';
    }
    echo '</div>';
}

function group_options(array $groups, string $selected): string {
    $out = '<option value=""' . ($selected === '' ? ' selected' : '') . '>– ohne Gruppe –</option>';
    foreach ($groups as $g) {
        $out .= '<option value="' . e($g) . '"' . ($g === $selected ? ' selected' : '') . '>' . e($g) . '</option>';
    }
    return $out;
}

function logo_mark(): string {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.6" '
         . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . '<path d="M5 12h12M12 6l6 6-6 6"/></svg>';
}

function icon(string $name): string {
    $paths = [
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'folder'   => '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/><path d="M12 10v6M9 13h6"/>',
        'copy'     => '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
        'edit'     => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'trash'    => '<path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        'logout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5M12 15V3"/>',
        'upload'   => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5M12 3v12"/>',
        'check'    => '<path d="M20 6L9 17l-5-5"/>',
        'x'        => '<path d="M18 6L6 18M6 6l12 12"/>',
        'lock'     => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'qr'       => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3M21 14v.01M14 21h3M21 17v4"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'bars'     => '<path d="M12 20V10M18 20V4M6 20v-4"/>',
        'search'   => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
    ];
    return '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
         . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . ($paths[$name] ?? '') . '</svg>';
}

function render_table(array $rows, string $base, string $self, string $csrf, ?string $editing, array $groups, array $clicks): void {
    if (!$rows) { echo '<p class="muted empty">Keine Links in dieser Gruppe.</p>'; return; }
    echo '<table>';
    foreach ($rows as $slug => $l):
        $short = $base . $slug;
        $url   = $l['url'];
        $host  = host_of($url);
        $hay   = strtolower($slug . ' ' . $l['title'] . ' ' . $url . ' ' . $l['group']);
        ?>
        <?php if ($editing === $slug): ?>
        <tr>
          <td class="cbcell"></td>
          <td colspan="5">
            <div class="editgrid">
              <div class="erow">
                <label class="field">
                  <span class="field-label">Gewünschte Short-URL</span>
                  <input form="editform" type="text" name="newslug" value="<?= e($slug) ?>" pattern="[a-z0-9\-]+" required>
                </label>
                <label class="field field--date">
                  <span class="field-label">Ablaufdatum (optional)</span>
                  <span class="datefield">
                    <input form="editform" type="date" name="expires" value="<?= e($l['expires']) ?>">
                    <button type="button" class="btn btn--ghost btn--small datefield-clear" data-clear-date title="Ablauf entfernen"><?= icon('x') ?></button>
                  </span>
                </label>
              </div>
              <label class="field">
                <span class="field-label">Ziel-URL</span>
                <input form="editform" type="text" name="url" value="<?= e($url) ?>" required placeholder="https://…">
              </label>
              <label class="field">
                <span class="field-label">Titel / Notiz</span>
                <input form="editform" type="text" name="title" value="<?= e($l['title']) ?>" placeholder="optional">
              </label>
              <div class="erow erow--actions">
                <button form="editform" class="btn btn--primary btn--small"><?= icon('check') ?>Speichern</button>
                <a class="btn btn--ghost btn--small" href="<?= e($self) ?>"><?= icon('x') ?>Abbrechen</a>
              </div>
            </div>
          </td>
        </tr>
        <?php else: ?>
        <tr draggable="true" data-slug="<?= e($slug) ?>" data-search="<?= e($hay) ?>">
          <td class="cbcell"><input class="rowchk" type="checkbox" name="slugs[]" value="<?= e($slug) ?>" form="bulk"></td>
          <td>
            <div class="linkcell">
              <?php if ($host !== ''): ?><img class="fav" src="https://icons.duckduckgo.com/ip3/<?= e($host) ?>.ico" alt="" width="16" height="16"><?php endif; ?>
              <div class="linkmeta">
                <a class="slug" href="<?= e($short) ?>" target="_blank" rel="noopener"><?= e($slug) ?></a>
                <?php if ($l['title'] !== ''): ?><span class="ltitle"><?= e($l['title']) ?></span><?php endif; ?>
              </div>
            </div>
          </td>
          <td class="target" title="<?= e($url) ?>"><?= e($url) ?><?php if ($l['expires'] !== ''): ?><span class="chip <?= is_expired($l['expires']) ? 'chip--exp' : 'chip--date' ?>"><?= icon('calendar') ?><?= is_expired($l['expires']) ? 'abgelaufen' : e($l['expires']) ?></span><?php endif; ?></td>
          <td class="clickcell"><span class="clicks" title="Aufrufe (anonym gezählt)"><?= icon('bars') ?><?= (int) ($clicks[$slug] ?? 0) ?></span></td>
          <td class="movecell">
            <form method="post" class="inline">
              <input type="hidden" name="action" value="move">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="slug" value="<?= e($slug) ?>">
              <select name="group" data-autosubmit><?= group_options($groups, $l['group']) ?></select>
            </form>
          </td>
          <td class="actions">
            <button type="button" class="btn btn--ghost btn--small" data-qr="<?= e($short) ?>" data-slug="<?= e($slug) ?>" title="QR-Code" aria-label="QR-Code"><?= icon('qr') ?></button>
            <button type="button" class="btn btn--ghost btn--small" data-copy="<?= e($short) ?>" title="Kurzlink kopieren" aria-label="Kopieren"><?= icon('copy') ?></button>
            <a class="btn btn--ghost btn--small" href="<?= e($self) ?>?edit=<?= urlencode($slug) ?>" title="Bearbeiten" aria-label="Bearbeiten"><?= icon('edit') ?></a>
            <form class="inline" method="post" data-confirm="„<?= e($slug) ?>“ wirklich löschen?">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="slug" value="<?= e($slug) ?>">
              <button class="btn btn--danger btn--small" title="Löschen" aria-label="Löschen"><?= icon('trash') ?></button>
            </form>
          </td>
        </tr>
        <?php endif; ?>
    <?php endforeach;
    echo '</table>';
}

/* ---------- Passwort-Quelle: ENV/config.php hat Vorrang, sonst Auth-Datei */

$cfgHash      = (string) ($cfg['password_hash'] ?? '');
$passwordHash = $cfgHash !== '' ? $cfgHash : stored_hash();
$hashEditable = ($cfgHash === '');   // Ändern im UI nur, wenn nicht in config/ENV festgelegt

/* ================================================================== *
 *  1) Setup-Modus  (kein Passwort gesetzt)
 * ================================================================== */

if ($passwordHash === '') {
    $manualHash = null;
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['new_password'])) {
        $pw = (string) $_POST['new_password'];
        if (strlen($pw) < 8) {
            flash('Bitte mindestens 8 Zeichen wählen.');
            redirect($self);
        }
        $hash = password_hash($pw, PASSWORD_DEFAULT);
        if (save_hash($hash)) {
            flash('Passwort gespeichert – du kannst dich jetzt anmelden.', 'success');
            redirect($self);
        }
        $manualHash = $hash;   // Datenordner nicht beschreibbar -> manueller Weg
    }
    head('GOTO – Einrichtung', $nonce);
    echo '<div class="topbar"><div class="brand"><span class="brand-mark">' . logo_mark()
       . '</span><div><h1>GOTO</h1><p class="sub">Einrichtung</p></div></div></div>';
    render_toasts($flashMsg ? [$flashMsg] : []);
    if ($manualHash) { ?>
        <p class="muted">Das automatische Speichern hat nicht geklappt (Schreibrechte im
        Datenverzeichnis fehlen). Trag diese Zeile in <code>config.php</code> ein:</p>
        <textarea class="hashbox" rows="3" readonly>'password_hash' => '<?= e($manualHash) ?>',</textarea>
    <?php } else { ?>
        <p class="muted">Lege ein Passwort fest – es wird sicher (bcrypt) gespeichert.
        Danach kannst du dich direkt anmelden.</p>
        <form class="card" method="post" autocomplete="off">
            <label>Passwort festlegen (mind. 8 Zeichen)</label>
            <input type="password" name="new_password" minlength="8" autofocus required>
            <button class="btn btn--primary"><?= icon('lock') ?>Passwort speichern</button>
        </form>
    <?php }
    foot($nonce);
    exit;
}

/* ================================================================== *
 *  2) Logout
 * ================================================================== */

if (isset($_GET['logout'])) {
    remember_forget($cookieDir, $https);
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    redirect($self);
}

/* ================================================================== *
 *  3) Auth-Zustand + Idle-Timeout
 * ================================================================== */

$loggedIn  = !empty($_SESSION['auth']);
$loginInfo = null;

if ($loggedIn && time() - (int) ($_SESSION['seen'] ?? 0) > $idleTimeout) {
    $loggedIn = false;
    unset($_SESSION['auth']);
    session_regenerate_id(true);
    $loginInfo = 'Sitzung abgelaufen – bitte erneut anmelden.';
}
if ($loggedIn) $_SESSION['seen'] = time();

// Auto-Login per „Angemeldet bleiben"-Cookie
if (!$loggedIn && remember_try($cookieDir, $https)) {
    session_regenerate_id(true);
    $_SESSION['auth'] = true;
    $_SESSION['seen'] = time();
    csrf_token();
    $loggedIn = true;
}

/* ================================================================== *
 *  4) Login
 * ================================================================== */

$loginError = null;

if (!$loggedIn && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['password'])) {
    $wait = lock_remaining($clientKey);
    if ($wait > 0) {
        $loginError = 'Zu viele Fehlversuche. Bitte ' . (int) ceil($wait / 60) . ' Min. warten.';
    } elseif (password_verify((string) $_POST['password'], $passwordHash)) {
        session_regenerate_id(true);
        $_SESSION['auth'] = true;
        $_SESSION['seen'] = time();
        csrf_token();
        clear_fails($clientKey);
        if (!empty($_POST['remember'])) remember_set($cookieDir, $https);
        redirect($self);
    } else {
        register_fail($clientKey, $maxAttempts, $lockoutSeconds);
        usleep(300000);
        $loginError = 'Falsches Passwort.';
    }
}

/* ================================================================== *
 *  5) Export  (GET, nur eingeloggt)
 * ================================================================== */

if ($loggedIn && isset($_GET['export'])) {
    $json = json_encode(load_data(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="goto-' . date('Y-m-d') . '.json"');
    echo $json;
    exit;
}

/* ================================================================== *
 *  6) Aktionen (POST)
 * ================================================================== */

if ($loggedIn && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    if (!csrf_ok()) { http_response_code(400); exit('Ungültiger CSRF-Token.'); }

    $data   = load_data();
    $action = (string) $_POST['action'];
    $slug   = clean_slug((string) ($_POST['slug'] ?? ''));
    $url    = trim((string) ($_POST['url'] ?? ''));
    $group  = clean_group((string) ($_POST['group'] ?? ''));
    $title  = clean_title((string) ($_POST['title'] ?? ''));
    $expires = clean_date((string) ($_POST['expires'] ?? ''));
    $groupValid = ($group === '' || in_array($group, $data['groups'], true)) ? $group : '';

    if ($action === 'add') {
        if (!valid_url($url)) {
            flash('Bitte eine gültige http(s)-URL angeben.');
        } else {
            if ($slug === '') $slug = random_slug($data['links']);
            if (in_array($slug, ['admin', 'index'], true)) {
                flash('„' . $slug . '“ ist reserviert – bitte ein anderes Kürzel wählen.');
            } elseif (isset($data['links'][$slug])) {
                flash('Kürzel „' . $slug . '“ existiert bereits.');
            } else {
                $data['links'][$slug] = ['url' => $url, 'group' => $groupValid,
                    'title' => $title, 'expires' => $expires, 'created' => time()];
                save_data($data)
                    ? flash('„' . $slug . '“ angelegt.', 'success')
                    : flash('Konnte nicht speichern – Schreibrechte für urls.json prüfen.');
            }
        }
    } elseif ($action === 'update') {
        $newslug = clean_slug((string) ($_POST['newslug'] ?? ''));
        if (!isset($data['links'][$slug]))                    flash('Unbekanntes Kürzel.');
        elseif (!valid_url($url))                             flash('Bitte eine gültige Ziel-URL angeben.');
        elseif ($newslug === '')                              flash('Bitte eine Short-URL angeben.');
        elseif (in_array($newslug, ['admin', 'index'], true)) flash('„' . $newslug . '“ ist reserviert.');
        elseif ($newslug !== $slug && isset($data['links'][$newslug])) flash('„' . $newslug . '“ ist bereits vergeben.');
        else {
            $entry = $data['links'][$slug];
            $entry['url'] = $url; $entry['title'] = $title; $entry['expires'] = $expires;
            if ($newslug === $slug) {
                $data['links'][$slug] = $entry;
            } else {
                // Schlüssel umbenennen, Reihenfolge in der Liste erhalten
                $rebuilt = [];
                foreach ($data['links'] as $k => $v) $rebuilt[$k === $slug ? $newslug : $k] = $k === $slug ? $entry : $v;
                $data['links'] = $rebuilt;
                // Klickzähler mit umziehen
                $cl = load_clicks();
                if (isset($cl[$slug])) { $cl[$newslug] = $cl[$slug]; unset($cl[$slug]); save_clicks($cl); }
            }
            save_data($data) ? flash('„' . $newslug . '“ aktualisiert.', 'success')
                             : flash('Konnte nicht speichern.');
        }
    } elseif ($action === 'move') {
        if (isset($data['links'][$slug])) {
            $data['links'][$slug]['group'] = $groupValid;
            save_data($data);
            flash('„' . $slug . '“ verschoben.', 'success');
        }
    } elseif ($action === 'delete') {
        if (isset($data['links'][$slug])) {
            unset($data['links'][$slug]);
            save_data($data);
            $cl = load_clicks(); unset($cl[$slug]); save_clicks($cl);
            flash('„' . $slug . '“ gelöscht.', 'success');
        }
    } elseif ($action === 'bulk') {
        $op    = (string) ($_POST['op'] ?? '');
        $slugs = array_map('clean_slug', (array) ($_POST['slugs'] ?? []));
        $slugs = array_values(array_filter($slugs, fn($s) => $s !== '' && isset($data['links'][$s])));
        if (!$slugs) {
            flash('Keine Links markiert.');
        } elseif ($op === 'move') {
            foreach ($slugs as $s) $data['links'][$s]['group'] = $groupValid;
            save_data($data);
            flash(count($slugs) . ' Link(s) verschoben.', 'success');
        } elseif ($op === 'delete') {
            foreach ($slugs as $s) unset($data['links'][$s]);
            save_data($data);
            $cl = load_clicks(); foreach ($slugs as $s) unset($cl[$s]); save_clicks($cl);
            flash(count($slugs) . ' Link(s) gelöscht.', 'success');
        } elseif ($op === 'reset') {
            $cl = load_clicks(); foreach ($slugs as $s) unset($cl[$s]); save_clicks($cl);
            flash('Zähler von ' . count($slugs) . ' Link(s) zurückgesetzt.', 'success');
        }
    } elseif ($action === 'group_add') {
        $g = clean_group((string) ($_POST['group_name'] ?? ''));
        if ($g === '')                                flash('Bitte einen Gruppennamen angeben.');
        elseif (in_array($g, $data['groups'], true))  flash('Gruppe „' . $g . '“ existiert bereits.');
        else { $data['groups'][] = $g; save_data($data); flash('Gruppe „' . $g . '“ angelegt.', 'success'); }
    } elseif ($action === 'group_rename') {
        $old = clean_group((string) ($_POST['old'] ?? ''));
        $new = clean_group((string) ($_POST['new'] ?? ''));
        $i   = array_search($old, $data['groups'], true);
        if ($i === false)                                          flash('Unbekannte Gruppe.');
        elseif ($new === '')                                       flash('Bitte einen Namen angeben.');
        elseif ($new !== $old && in_array($new, $data['groups'], true)) flash('Gruppe „' . $new . '“ existiert bereits.');
        else {
            $data['groups'][$i] = $new;
            foreach ($data['links'] as &$l) if ($l['group'] === $old) $l['group'] = $new;
            unset($l);
            save_data($data);
            flash('Gruppe umbenannt.', 'success');
        }
    } elseif ($action === 'group_delete') {
        $g = clean_group((string) ($_POST['group'] ?? ''));
        $i = array_search($g, $data['groups'], true);
        if ($i !== false) {
            array_splice($data['groups'], $i, 1);
            foreach ($data['links'] as &$l) if ($l['group'] === $g) $l['group'] = '';
            unset($l);
            save_data($data);
            flash('Gruppe „' . $g . '“ gelöscht.', 'success');
        }
    } elseif ($action === 'import') {
        $tmp = $_FILES['file']['tmp_name'] ?? '';
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            flash('Keine Datei empfangen.');
        } else {
            $parsed = json_decode((string) file_get_contents($tmp), true);
            if (!is_array($parsed)) {
                flash('Die Datei ist kein gültiges JSON.');
            } else {
                $imported = normalize_data($parsed);
                if (!empty($_POST['merge'])) {
                    foreach ($imported['links'] as $s => $l) $data['links'][$s] = $l;
                    foreach ($imported['groups'] as $g) if (!in_array($g, $data['groups'], true)) $data['groups'][] = $g;
                } else {
                    $data = $imported;
                }
                save_data($data)
                    ? flash(count($imported['links']) . ' Link(s) importiert.', 'success')
                    : flash('Konnte nicht speichern.');
            }
        }
    } elseif ($action === 'change_password') {
        if (!$hashEditable) {
            flash('Das Passwort ist in config.php/ENV festgelegt und hier nicht änderbar.');
        } else {
            $curp = (string) ($_POST['current'] ?? '');
            $newp = (string) ($_POST['new'] ?? '');
            if (!password_verify($curp, $passwordHash)) flash('Aktuelles Passwort ist falsch.');
            elseif (strlen($newp) < 8)                  flash('Neues Passwort: mindestens 8 Zeichen.');
            else {
                save_hash(password_hash($newp, PASSWORD_DEFAULT))
                    ? flash('Passwort geändert.', 'success')
                    : flash('Konnte nicht speichern – Schreibrechte prüfen.');
            }
        }
    }
    redirect($self);
}

/* ================================================================== *
 *  7) Login-Ansicht
 * ================================================================== */

if (!$loggedIn) {
    head('GOTO – Anmelden', $nonce);
    echo '<div class="topbar"><div class="brand"><span class="brand-mark">' . logo_mark()
       . '</span><div><h1>GOTO</h1><p class="sub">URL-Weiterleitungen &amp; QR-Codes</p></div></div></div>';
    $lt = $flashMsg ? [$flashMsg] : [];
    if ($loginError) $lt[] = ['msg' => $loginError, 'type' => 'error'];
    if ($loginInfo)  $lt[] = ['msg' => $loginInfo,  'type' => 'info'];
    render_toasts($lt);
    ?>
    <form class="card" method="post" autocomplete="off">
        <label>Passwort</label>
        <input type="password" name="password" autofocus required>
        <label class="chk chk--remember"><input type="checkbox" name="remember" value="1"> Angemeldet bleiben</label>
        <button class="btn btn--primary"><?= icon('lock') ?>Anmelden</button>
    </form>
    <?php
    foot($nonce);
    exit;
}

/* ================================================================== *
 *  8) Verwaltungs-Ansicht
 * ================================================================== */

$data      = load_data();
$links     = $data['links'];
$groups    = $data['groups'];
$clicks    = load_clicks();
$csrf      = csrf_token();
$editing   = isset($_GET['edit'])      ? clean_slug((string) $_GET['edit'])       : null;
$editgroup = isset($_GET['editgroup']) ? clean_group((string) $_GET['editgroup']) : null;

$sections  = array_fill_keys($groups, []);
$ungrouped = [];
foreach ($links as $slug => $l) {
    if ($l['group'] !== '' && isset($sections[$l['group']])) $sections[$l['group']][$slug] = $l;
    else $ungrouped[$slug] = $l;
}

head('GOTO', $nonce);
?>
<div class="topbar">
  <div class="brand">
    <span class="brand-mark"><?= logo_mark() ?></span>
    <div>
      <h1>GOTO</h1>
      <p class="sub">URL-Weiterleitungen &amp; QR-Codes</p>
    </div>
  </div>
  <div class="topbar-actions">
    <select id="theme" aria-label="Darstellung" title="Darstellung">
      <option value="system">System</option>
      <option value="light">Hell</option>
      <option value="dark">Dunkel</option>
    </select>
    <a class="btn btn--ghost btn--small" href="<?= e($self) ?>?logout"><?= icon('logout') ?>Abmelden</a>
  </div>
</div>

<?php render_toasts($flashMsg ? [$flashMsg] : []); ?>

<div class="panel">
  <form method="post" autocomplete="off">
    <input type="hidden" name="action" value="add">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <div class="bar">
      <label class="field grow">
        <span class="field-label">Ziel-URL</span>
        <input type="text" name="url" placeholder="https://ziel-adresse.de/…" required>
      </label>
    </div>
    <div class="bar">
      <label class="field grow2">
        <span class="field-label">Gewünschte Short-URL</span>
        <input type="text" name="slug" placeholder="leer lassen für zufälligen Wert" pattern="[a-z0-9\-]*">
      </label>
      <label class="field field--group">
        <span class="field-label">Gruppe</span>
        <select name="group"><?= group_options($groups, '') ?></select>
      </label>
      <label class="field field--date">
        <span class="field-label">Ablaufdatum (optional)</span>
        <span class="datefield">
          <input type="date" name="expires" title="Nach diesem Tag ist der Link gesperrt">
          <button type="button" class="btn btn--ghost btn--small datefield-clear" data-clear-date title="Ablauf entfernen"><?= icon('x') ?></button>
        </span>
      </label>
    </div>
    <div class="bar bar--end">
      <label class="field grow">
        <span class="field-label">Titel / Notiz</span>
        <input type="text" name="title" placeholder="optional – z. B. „Intro-Video“">
      </label>
      <button class="btn btn--primary"><?= icon('plus') ?>Hinzufügen</button>
    </div>
  </form>

  <form class="bar bar--end" method="post" autocomplete="off">
    <input type="hidden" name="action" value="group_add">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <label class="field grow">
      <span class="field-label">Neue Gruppe / Projekt</span>
      <input type="text" name="group_name" placeholder="z. B. Bachelorarbeit" maxlength="40" required>
    </label>
    <button class="btn btn--ghost"><?= icon('folder') ?>Gruppe anlegen</button>
  </form>
</div>

<?php if ($editing !== null && isset($links[$editing])): ?>
  <form id="editform" method="post">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="slug" value="<?= e($editing) ?>">
  </form>
<?php endif; ?>

<form id="bulk" class="panel bulkbar" method="post">
  <input type="hidden" name="action" value="bulk">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
  <label class="chk"><input type="checkbox" id="selall"> <span id="selcount">0 markiert</span></label>
  <span class="bulk-spacer"></span>
  <select name="group" aria-label="Zielgruppe"><?= group_options($groups, '') ?></select>
  <button class="btn btn--ghost btn--small" name="op" value="move"><?= icon('folder') ?>Verschieben</button>
  <button class="btn btn--ghost btn--small" name="op" value="reset"><?= icon('bars') ?>Zähler&nbsp;0</button>
  <button class="btn btn--danger btn--small" name="op" value="delete" data-confirm="Markierte Links wirklich löschen?"><?= icon('trash') ?>Löschen</button>
</form>

<?php if ($links): ?>
  <div class="search">
    <?= icon('search') ?>
    <input type="text" id="search" placeholder="Suchen … Kürzel, Titel, URL oder Gruppe" autocomplete="off">
  </div>
<?php endif; ?>

<form id="dndform" method="post" hidden>
  <input type="hidden" name="action" value="move">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
  <input type="hidden" name="slug" value="">
  <input type="hidden" name="group" value="">
</form>

<?php if (!$links): ?>
  <p class="muted empty">Noch keine Links angelegt.</p>
<?php endif; ?>

<?php foreach ($groups as $g): ?>
  <section class="group" data-group="<?= e($g) ?>">
    <div class="group-head">
      <?php if ($editgroup === $g): ?>
        <form class="bar" method="post" autocomplete="off">
          <input type="hidden" name="action" value="group_rename">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="old" value="<?= e($g) ?>">
          <input type="text" name="new" value="<?= e($g) ?>" maxlength="40" autofocus required>
          <button class="btn btn--primary btn--small"><?= icon('check') ?>Speichern</button>
          <a class="btn btn--ghost btn--small" href="<?= e($self) ?>"><?= icon('x') ?>Abbrechen</a>
        </form>
      <?php else: ?>
        <h2><?= e($g) ?><span class="count"><?= count($sections[$g]) ?></span></h2>
        <span class="group-actions">
          <a class="btn btn--ghost btn--small" href="<?= e($self) ?>?editgroup=<?= urlencode($g) ?>"><?= icon('edit') ?>Umbenennen</a>
          <form class="inline" method="post" data-confirm="Gruppe „<?= e($g) ?>“ löschen? Links wandern zu „ohne Gruppe".">
            <input type="hidden" name="action" value="group_delete">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="group" value="<?= e($g) ?>">
            <button class="btn btn--ghost btn--small"><?= icon('trash') ?>Löschen</button>
          </form>
        </span>
      <?php endif; ?>
    </div>
    <?php render_table($sections[$g], $base, $self, $csrf, $editing, $groups, $clicks); ?>
  </section>
<?php endforeach; ?>

<?php if ($ungrouped): ?>
  <section class="group" data-group="">
    <div class="group-head">
      <h2>Ohne Gruppe<span class="count"><?= count($ungrouped) ?></span></h2>
    </div>
    <?php render_table($ungrouped, $base, $self, $csrf, $editing, $groups, $clicks); ?>
  </section>
<?php endif; ?>

<?php if ($links): ?><p id="noresults" class="muted empty" hidden>Keine Treffer für die Suche.</p><?php endif; ?>

<dialog id="qrdlg" class="qrdlg">
  <div class="qrdlg-head">
    <strong id="qrTitle">QR-Code</strong>
    <button type="button" class="btn btn--ghost btn--small" id="qrClose"><?= icon('x') ?>Schließen</button>
  </div>
  <div class="qrdlg-body">
    <div class="qrprev" id="qrPrev"></div>
    <div class="qropts">
      <label>Fehlerkorrektur
        <select id="qrEcl">
          <option value="L">L – niedrig (7 %)</option>
          <option value="M" selected>M – mittel (15 %)</option>
          <option value="Q">Q – hoch (25 %)</option>
          <option value="H">H – maximal (30 %)</option>
        </select>
      </label>
      <div class="qrrow">
        <label>Modulgröße (px)<input type="number" id="qrScale" min="2" max="40" value="8"></label>
        <label>Rand (Module)<input type="number" id="qrMargin" min="0" max="16" value="4"></label>
      </div>
      <div class="qrrow">
        <label>Vordergrund<input type="color" id="qrFg" value="#000000"></label>
        <label>Hintergrund<input type="color" id="qrBg" value="#ffffff"></label>
      </div>
      <p class="muted qrurl" id="qrUrl"></p>
      <div class="qrdl">
        <button type="button" class="btn btn--primary btn--small" id="qrPng"><?= icon('download') ?>PNG</button>
        <button type="button" class="btn btn--primary btn--small" id="qrSvg"><?= icon('download') ?>SVG</button>
      </div>
    </div>
  </div>
</dialog>

<details class="tools">
  <summary>Export / Import</summary>
  <p><a class="btn btn--ghost btn--small" href="<?= e($self) ?>?export=1"><?= icon('download') ?>Export – JSON herunterladen</a></p>
  <form class="bar" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="action" value="import">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="file" name="file" accept="application/json,.json" required>
    <label class="chk"><input type="checkbox" name="merge" value="1"> mit Bestand zusammenführen</label>
    <button class="btn btn--ghost"><?= icon('upload') ?>Importieren</button>
  </form>
  <p class="muted">Ohne Häkchen werden alle bestehenden Einträge <strong>ersetzt</strong>. Klick-Zähler werden nicht exportiert.</p>
</details>

<?php if ($hashEditable): ?>
<details class="tools">
  <summary>Passwort ändern</summary>
  <form class="bar bar--end" method="post" autocomplete="off">
    <input type="hidden" name="action" value="change_password">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <label class="field grow">
      <span class="field-label">Aktuelles Passwort</span>
      <input type="password" name="current" autocomplete="current-password" required>
    </label>
    <label class="field grow">
      <span class="field-label">Neues Passwort (mind. 8 Zeichen)</span>
      <input type="password" name="new" minlength="8" autocomplete="new-password" required>
    </label>
    <button class="btn btn--primary"><?= icon('lock') ?>Speichern</button>
  </form>
</details>
<?php endif; ?>

<?php
foot($nonce);
