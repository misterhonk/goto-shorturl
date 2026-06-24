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

// Fehler nicht anzeigen, aber in eine geschützte Datei loggen (Ferndiagnose)
@ini_set('log_errors', '1');
@ini_set('error_log', $dataDir . '/.ht_error.log');

define('URLS_FILE',     $dataDir . '/urls.json');
define('CLICKS_FILE',   $dataDir . '/clicks.json');
define('ATTEMPTS_FILE', $dataDir . '/.ht_attempts.json');
define('TOKENS_FILE',   $dataDir . '/.ht_tokens.json');
define('AUTH_FILE',     $dataDir . '/.ht_auth.json');
define('TRASH_FILE',    $dataDir . '/.ht_trash.json');
define('SHOW_FAVICONS', (bool) ($cfg['favicons'] ?? true));

// Sprache: Cookie > config.php > Deutsch. Übersetzung über lang.php (Fallback: Deutsch).
$LANG_EN = (array) (@include __DIR__ . '/lang.php');
$lang = 'de';
if (in_array((string) ($_COOKIE['goto_lang'] ?? ''), ['de', 'en'], true))      $lang = (string) $_COOKIE['goto_lang'];
elseif (in_array((string) ($cfg['lang'] ?? ''), ['de', 'en'], true))           $lang = (string) $cfg['lang'];

function t(string $de, ...$args): string {
    global $lang, $LANG_EN;
    $s = ($lang === 'en' && isset($LANG_EN[$de])) ? $LANG_EN[$de] : $de;
    return $args ? vsprintf($s, $args) : $s;
}

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
    if ($json === false) return false;                    // ungültige Daten -> nicht schreiben
    $tmp = $file . '.tmp';
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    return @rename($tmp, $file);                          // rename ist atomar (gleiches Dateisystem)
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

function load_trash(): array  { return load_json(TRASH_FILE); }
function save_trash(array $t): bool { return save_json(TRASH_FILE, $t); }

// CSV importieren. Kopfzeile mit Spalten url,slug,group,title,expires (Reihenfolge egal).
// Ohne Kopfzeile: erste Spalte = url, zweite = slug usw. Trenner , oder ; wird erkannt.
function csv_to_data(string $content): array {
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;     // BOM entfernen
    $lines   = preg_split('/\r\n|\r|\n/', trim($content)) ?: [];
    if (!$lines || $lines[0] === '') return ['groups' => [], 'links' => []];
    $delim  = (substr_count($lines[0], ';') > substr_count($lines[0], ',')) ? ';' : ',';
    $header = array_map(fn($h) => strtolower(trim((string) $h)), str_getcsv($lines[0], $delim));
    $col    = function (string $name) use ($header) { $i = array_search($name, $header, true); return $i === false ? -1 : $i; };
    $iUrl = $col('url');
    if ($iUrl >= 0) { $iSlug = $col('slug'); $iGroup = $col('group'); $iTitle = $col('title'); $iExp = $col('expires'); $start = 1; }
    else            { $iUrl = 0; $iSlug = 1; $iGroup = 2; $iTitle = 3; $iExp = 4; $start = 0; }   // keine Kopfzeile
    $links = []; $groups = [];
    for ($r = $start; $r < count($lines); $r++) {
        if (trim($lines[$r]) === '') continue;
        $cols = str_getcsv($lines[$r], $delim);
        $get  = fn(int $i) => ($i >= 0 && isset($cols[$i])) ? trim((string) $cols[$i]) : '';
        $url  = $get($iUrl);
        if (!valid_url($url)) continue;
        $slug = clean_slug($get($iSlug));
        if ($slug === '') $slug = random_slug($links);
        if (isset($links[$slug])) continue;
        $group = clean_group($get($iGroup));
        if ($group !== '' && !in_array($group, $groups, true)) $groups[] = $group;
        $links[$slug] = ['url' => $url, 'group' => $group, 'title' => clean_title($get($iTitle)),
                         'expires' => clean_date($get($iExp)), 'created' => time()];
    }
    return ['groups' => $groups, 'links' => $links];
}

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
    // Schutz vor Aufblähen durch verteilte Angriffe: nur aktive Sperren behalten
    if (count($all) > 2000) {
        $all = array_filter($all, fn($r) => ($r['until'] ?? 0) >= $now);
        $all[$key] = $rec;
    }
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

// Sprachumschaltung (?lang=de|en) -> Cookie setzen und zurück
if (isset($_GET['lang']) && in_array($_GET['lang'], ['de', 'en'], true)) {
    setcookie('goto_lang', $_GET['lang'], [
        'expires' => time() + 31536000, 'path' => $cookieDir, 'samesite' => 'Lax',
    ]);
    header('Location: ' . $self);
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
$imgSrc = "img-src 'self' data:" . (SHOW_FAVICONS ? ' https://icons.duckduckgo.com' : '');
header(
    "Content-Security-Policy: default-src 'none'; " . $imgSrc . "; " .
    "style-src 'self' 'nonce-$nonce'; script-src 'self' 'nonce-$nonce'; " .
    "form-action 'self'; base-uri 'none'; frame-ancestors 'none'"
);

$clientKey = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli'));
$flashMsg  = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$base = ($https ? 'https' : 'http') . '://'
      . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $cookieDir;

/* ---------- Layout ------------------------------------------------- */

function head(string $title, string $nonce): void {
    global $lang;
    $dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/') . '/';
    ?>
<!DOCTYPE html>
<html lang="<?= e($lang ?? 'de') ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($title) ?></title>
<script nonce="<?= $nonce ?>">(function(){try{var t=localStorage.getItem('goto-theme');if(t&&t!=='system')document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
<link rel="stylesheet" href="<?= e($dir) ?>goto.css?v=<?= (int) @filemtime(__DIR__ . '/goto.css') ?>">
</head>
<body>
<?php }

function foot(string $nonce): void {
    $dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/') . '/';
    ?>
<script src="<?= e($dir) ?>qr.js" nonce="<?= $nonce ?>"></script>
<script src="<?= e($dir) ?>app.js?v=<?= (int) @filemtime(__DIR__ . '/app.js') ?>"></script>
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
    $out = '<option value=""' . ($selected === '' ? ' selected' : '') . '>' . t('– ohne Gruppe –') . '</option>';
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
        'undo'     => '<path d="M3 7v6h6"/><path d="M3.5 13a9 9 0 1 0 2.3-9.3L3 8"/>',
    ];
    return '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
         . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . ($paths[$name] ?? '') . '</svg>';
}

// Mini-Verlaufsgrafik der letzten $n Tage (leer, wenn keine Tagesdaten vorliegen)
function sparkline_svg(array $days, int $n = 14): string {
    $vals = [];
    for ($i = $n - 1; $i >= 0; $i--) $vals[] = (int) ($days[date('Y-m-d', time() - $i * 86400)] ?? 0);
    $max = max($vals);
    if ($max <= 0) return '';
    $w = 56; $h = 18; $step = $w / ($n - 1); $pts = [];
    foreach ($vals as $i => $v) {
        $pts[] = round($i * $step, 1) . ',' . round($h - 1 - ($v / $max) * ($h - 2), 1);
    }
    return '<svg class="spark" viewBox="0 0 ' . $w . ' ' . $h . '" width="' . $w . '" height="' . $h . '" '
         . 'preserveAspectRatio="none" aria-hidden="true"><polyline points="' . implode(' ', $pts)
         . '" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

function render_table(array $rows, string $base, string $self, string $csrf, ?string $editing, array $groups, array $clicks): void {
    if (!$rows) { echo '<p class="muted empty">' . t('Keine Links in dieser Gruppe.') . '</p>'; return; }
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
                  <span class="field-label"><?= t('Gewünschte Short-URL') ?></span>
                  <input form="editform" type="text" name="newslug" value="<?= e($slug) ?>" pattern="[a-z0-9\-]+" required>
                </label>
                <label class="field field--date">
                  <span class="field-label"><?= t('Ablaufdatum (optional)') ?></span>
                  <span class="datefield">
                    <input form="editform" type="date" name="expires" value="<?= e($l['expires']) ?>">
                    <button type="button" class="btn btn--ghost btn--small datefield-clear" data-clear-date title="<?= t('Ablauf entfernen') ?>"><?= icon('x') ?></button>
                  </span>
                </label>
              </div>
              <label class="field">
                <span class="field-label"><?= t('Ziel-URL') ?></span>
                <input form="editform" type="text" name="url" value="<?= e($url) ?>" required placeholder="https://…">
              </label>
              <label class="field">
                <span class="field-label"><?= t('Titel / Notiz') ?></span>
                <input form="editform" type="text" name="title" value="<?= e($l['title']) ?>" placeholder="optional">
              </label>
              <div class="erow erow--actions">
                <button form="editform" class="btn btn--primary btn--small"><?= icon('check') ?><?= t('Speichern') ?></button>
                <a class="btn btn--ghost btn--small" href="<?= e($self) ?>"><?= icon('x') ?><?= t('Abbrechen') ?></a>
              </div>
            </div>
          </td>
        </tr>
        <?php else: ?>
        <tr draggable="true" data-slug="<?= e($slug) ?>" data-search="<?= e($hay) ?>" data-created="<?= (int) $l['created'] ?>" data-clicks="<?= clicks_total($clicks, $slug) ?>" data-expired="<?= is_expired($l['expires']) ? '1' : '0' ?>">
          <td class="cbcell"><input class="rowchk" type="checkbox" name="slugs[]" value="<?= e($slug) ?>" form="bulk"></td>
          <td>
            <div class="linkcell">
              <?php if (SHOW_FAVICONS && $host !== ''): ?><img class="fav" src="https://icons.duckduckgo.com/ip3/<?= e($host) ?>.ico" alt="" width="16" height="16"><?php endif; ?>
              <div class="linkmeta">
                <a class="slug" href="<?= e($short) ?>" target="_blank" rel="noopener"><?= e($slug) ?></a>
                <?php if ($l['title'] !== ''): ?><span class="ltitle"><?= e($l['title']) ?></span><?php endif; ?>
              </div>
            </div>
          </td>
          <td class="target" title="<?= e($url) ?>"><?= e($url) ?><?php if ($l['expires'] !== ''): ?><span class="chip <?= is_expired($l['expires']) ? 'chip--exp' : 'chip--date' ?>"><?= icon('calendar') ?><?= is_expired($l['expires']) ? 'abgelaufen' : e($l['expires']) ?></span><?php endif; ?></td>
          <td class="clickcell"><span class="clicks" title="<?= t('Aufrufe gesamt (anonym) – Verlauf der letzten 14 Tage') ?>"><?php $spark = sparkline_svg(clicks_days($clicks, $slug)); echo $spark ?: icon('bars'); ?><?= clicks_total($clicks, $slug) ?></span></td>
          <td class="movecell">
            <form method="post" class="inline">
              <input type="hidden" name="action" value="move">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="slug" value="<?= e($slug) ?>">
              <select name="group" data-autosubmit><?= group_options($groups, $l['group']) ?></select>
            </form>
          </td>
          <td class="actions">
            <button type="button" class="btn btn--ghost btn--small" data-qr="<?= e($short) ?>" data-slug="<?= e($slug) ?>" title="<?= t('QR-Code') ?>" aria-label="<?= t('QR-Code') ?>"><?= icon('qr') ?></button>
            <button type="button" class="btn btn--ghost btn--small" data-copy="<?= e($short) ?>" title="<?= t('Kurzlink kopieren') ?>" aria-label="Kopieren"><?= icon('copy') ?></button>
            <a class="btn btn--ghost btn--small" href="<?= e($self) ?>?edit=<?= urlencode($slug) ?>" title="<?= t('Bearbeiten') ?>" aria-label="<?= t('Bearbeiten') ?>"><?= icon('edit') ?></a>
            <form class="inline" method="post" data-confirm="<?= e(t('„%s“ wirklich löschen?', $slug)) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="slug" value="<?= e($slug) ?>">
              <button class="btn btn--danger btn--small" title="<?= t('Löschen') ?>" aria-label="<?= t('Löschen') ?>"><?= icon('trash') ?></button>
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
            flash(t('Bitte mindestens 8 Zeichen wählen.'));
            redirect($self);
        }
        $hash = password_hash($pw, PASSWORD_DEFAULT);
        if (save_hash($hash)) {
            flash(t('Passwort gespeichert – du kannst dich jetzt anmelden.'), 'success');
            redirect($self);
        }
        $manualHash = $hash;   // Datenordner nicht beschreibbar -> manueller Weg
    }
    head('GOTO – ' . t('Einrichtung'), $nonce);
    echo '<div class="topbar"><div class="brand"><span class="brand-mark">' . logo_mark()
       . '</span><div><h1>GOTO</h1><p class="sub">' . t('Einrichtung') . '</p></div></div></div>';
    render_toasts($flashMsg ? [$flashMsg] : []);
    if ($manualHash) { ?>
        <p class="muted">Das automatische Speichern hat nicht geklappt (Schreibrechte im
        Datenverzeichnis fehlen). Trag diese Zeile in <code>config.php</code> ein:</p>
        <textarea class="hashbox" rows="3" readonly>'password_hash' => '<?= e($manualHash) ?>',</textarea>
    <?php } else { ?>
        <p class="muted"><?= t('Lege ein Passwort fest – es wird sicher (bcrypt) gespeichert. Danach kannst du dich direkt anmelden.') ?></p>
        <form class="card" method="post" autocomplete="off">
            <label><?= t('Passwort festlegen (mind. 8 Zeichen)') ?></label>
            <input type="password" name="new_password" minlength="8" autofocus required>
            <button class="btn btn--primary"><?= icon('lock') ?><?= t('Passwort speichern') ?></button>
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
    $loginInfo = t('Sitzung abgelaufen – bitte erneut anmelden.');
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
        $loginError = t('Zu viele Fehlversuche. Bitte %d Min. warten.', (int) ceil($wait / 60));
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
        $loginError = t('Falsches Passwort.');
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
            flash(t('Bitte eine gültige http(s)-URL angeben.'));
        } else {
            if ($slug === '') $slug = random_slug($data['links']);
            if (in_array($slug, ['admin', 'index'], true)) {
                flash(t('„%s“ ist reserviert – bitte ein anderes Kürzel wählen.', $slug));
            } elseif (isset($data['links'][$slug])) {
                flash(t('Kürzel „%s“ existiert bereits.', $slug));
            } else {
                $data['links'][$slug] = ['url' => $url, 'group' => $groupValid,
                    'title' => $title, 'expires' => $expires, 'created' => time()];
                save_data($data)
                    ? flash(t('„%s“ angelegt.', $slug), 'success')
                    : flash(t('Konnte nicht speichern – Schreibrechte für urls.json prüfen.'));
            }
        }
    } elseif ($action === 'update') {
        $newslug = clean_slug((string) ($_POST['newslug'] ?? ''));
        if (!isset($data['links'][$slug]))                    flash(t('Unbekanntes Kürzel.'));
        elseif (!valid_url($url))                             flash(t('Bitte eine gültige Ziel-URL angeben.'));
        elseif ($newslug === '')                              flash(t('Bitte eine Short-URL angeben.'));
        elseif (in_array($newslug, ['admin', 'index'], true)) flash(t('„%s“ ist reserviert.', $newslug));
        elseif ($newslug !== $slug && isset($data['links'][$newslug])) flash(t('„%s“ ist bereits vergeben.', $newslug));
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
            save_data($data) ? flash(t('„%s“ aktualisiert.', $newslug), 'success')
                             : flash(t('Konnte nicht speichern.'));
        }
    } elseif ($action === 'move') {
        if (isset($data['links'][$slug])) {
            $data['links'][$slug]['group'] = $groupValid;
            save_data($data);
            flash(t('„%s“ verschoben.', $slug), 'success');
        }
    } elseif ($action === 'delete') {
        if (isset($data['links'][$slug])) {
            $cl = load_clicks(); $tr = load_trash();
            $tr[$slug] = $data['links'][$slug];
            $tr[$slug]['deleted'] = time();
            $tr[$slug]['clicks']  = $cl[$slug] ?? 0;
            unset($data['links'][$slug], $cl[$slug]);
            save_data($data); save_clicks($cl); save_trash($tr);
            flash(t('„%s“ in den Papierkorb verschoben.', $slug), 'success');
        }
    } elseif ($action === 'bulk') {
        $op    = (string) ($_POST['op'] ?? '');
        $slugs = array_map('clean_slug', (array) ($_POST['slugs'] ?? []));
        $slugs = array_values(array_filter($slugs, fn($s) => $s !== '' && isset($data['links'][$s])));
        if (!$slugs) {
            flash(t('Keine Links markiert.'));
        } elseif ($op === 'move') {
            foreach ($slugs as $s) $data['links'][$s]['group'] = $groupValid;
            save_data($data);
            flash(t('%d Link(s) verschoben.', count($slugs)), 'success');
        } elseif ($op === 'delete') {
            $cl = load_clicks(); $tr = load_trash();
            foreach ($slugs as $s) {
                $tr[$s] = $data['links'][$s];
                $tr[$s]['deleted'] = time();
                $tr[$s]['clicks']  = $cl[$s] ?? 0;
                unset($data['links'][$s], $cl[$s]);
            }
            save_data($data); save_clicks($cl); save_trash($tr);
            flash(t('%d Link(s) in den Papierkorb verschoben.', count($slugs)), 'success');
        } elseif ($op === 'reset') {
            $cl = load_clicks(); foreach ($slugs as $s) unset($cl[$s]); save_clicks($cl);
            flash(t('Zähler von %d Link(s) zurückgesetzt.', count($slugs)), 'success');
        }
    } elseif ($action === 'group_add') {
        $g = clean_group((string) ($_POST['group_name'] ?? ''));
        if ($g === '')                                flash(t('Bitte einen Gruppennamen angeben.'));
        elseif (in_array($g, $data['groups'], true))  flash(t('Gruppe „%s“ existiert bereits.', $g));
        else { $data['groups'][] = $g; save_data($data); flash(t('Gruppe „%s“ angelegt.', $g), 'success'); }
    } elseif ($action === 'group_rename') {
        $old = clean_group((string) ($_POST['old'] ?? ''));
        $new = clean_group((string) ($_POST['new'] ?? ''));
        $i   = array_search($old, $data['groups'], true);
        if ($i === false)                                          flash(t('Unbekannte Gruppe.'));
        elseif ($new === '')                                       flash(t('Bitte einen Namen angeben.'));
        elseif ($new !== $old && in_array($new, $data['groups'], true)) flash(t('Gruppe „%s“ existiert bereits.', $new));
        else {
            $data['groups'][$i] = $new;
            foreach ($data['links'] as &$l) if ($l['group'] === $old) $l['group'] = $new;
            unset($l);
            save_data($data);
            flash(t('Gruppe umbenannt.'), 'success');
        }
    } elseif ($action === 'group_delete') {
        $g = clean_group((string) ($_POST['group'] ?? ''));
        $i = array_search($g, $data['groups'], true);
        if ($i !== false) {
            array_splice($data['groups'], $i, 1);
            foreach ($data['links'] as &$l) if ($l['group'] === $g) $l['group'] = '';
            unset($l);
            save_data($data);
            flash(t('Gruppe „%s“ gelöscht.', $g), 'success');
        }
    } elseif ($action === 'import') {
        $tmp = $_FILES['file']['tmp_name'] ?? '';
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            flash(t('Keine Datei empfangen.'));
        } else {
            $content  = (string) file_get_contents($tmp);
            $parsed   = json_decode($content, true);
            $imported = is_array($parsed) ? normalize_data($parsed) : csv_to_data($content);
            if (!$imported['links']) {
                flash(t('Keine gültigen Einträge gefunden (JSON oder CSV erwartet).'));
            } else {
                if (!empty($_POST['merge'])) {
                    foreach ($imported['links'] as $s => $l) $data['links'][$s] = $l;
                    foreach ($imported['groups'] as $g) if (!in_array($g, $data['groups'], true)) $data['groups'][] = $g;
                } else {
                    $data = $imported;
                }
                save_data($data)
                    ? flash(t('%d Link(s) importiert.', count($imported['links'])), 'success')
                    : flash(t('Konnte nicht speichern.'));
            }
        }
    } elseif ($action === 'change_password') {
        if (!$hashEditable) {
            flash(t('Das Passwort ist in config.php/ENV festgelegt und hier nicht änderbar.'));
        } else {
            $curp = (string) ($_POST['current'] ?? '');
            $newp = (string) ($_POST['new'] ?? '');
            if (!password_verify($curp, $passwordHash)) flash(t('Aktuelles Passwort ist falsch.'));
            elseif (strlen($newp) < 8)                  flash(t('Neues Passwort: mindestens 8 Zeichen.'));
            else {
                save_hash(password_hash($newp, PASSWORD_DEFAULT))
                    ? flash(t('Passwort geändert.'), 'success')
                    : flash(t('Konnte nicht speichern – Schreibrechte prüfen.'));
            }
        }
    } elseif ($action === 'restore') {
        $tr = load_trash();
        if (!isset($tr[$slug]))            flash(t('Eintrag nicht im Papierkorb.'));
        elseif (isset($data['links'][$slug])) flash(t('Kürzel „%s“ ist bereits vergeben.', $slug));
        else {
            $it = $tr[$slug];
            $g  = clean_group((string) ($it['group'] ?? ''));
            if ($g !== '' && !in_array($g, $data['groups'], true)) $data['groups'][] = $g;
            $data['links'][$slug] = ['url' => (string) ($it['url'] ?? ''), 'group' => $g,
                'title' => (string) ($it['title'] ?? ''), 'expires' => (string) ($it['expires'] ?? ''),
                'created' => (int) ($it['created'] ?? 0)];
            $clrec = $it['clicks'] ?? 0;
            unset($tr[$slug]);
            $cl = load_clicks(); if ($clrec) $cl[$slug] = $clrec; save_clicks($cl);
            save_data($data); save_trash($tr);
            flash(t('„%s“ wiederhergestellt.', $slug), 'success');
        }
    } elseif ($action === 'trash_delete') {
        $tr = load_trash();
        if (isset($tr[$slug])) { unset($tr[$slug]); save_trash($tr); flash(t('„%s“ endgültig gelöscht.', $slug), 'success'); }
    } elseif ($action === 'trash_empty') {
        save_trash([]);
        flash(t('Papierkorb geleert.'), 'success');
    }
    redirect($self);
}

/* ================================================================== *
 *  7) Login-Ansicht
 * ================================================================== */

if (!$loggedIn) {
    head('GOTO – ' . t('Anmelden'), $nonce);
    echo '<div class="topbar"><div class="brand"><span class="brand-mark">' . logo_mark()
       . '</span><div><h1>GOTO</h1><p class="sub">' . t('URL-Weiterleitungen &amp; QR-Codes') . '</p></div></div></div>';
    $lt = $flashMsg ? [$flashMsg] : [];
    if ($loginError) $lt[] = ['msg' => $loginError, 'type' => 'error'];
    if ($loginInfo)  $lt[] = ['msg' => $loginInfo,  'type' => 'info'];
    render_toasts($lt);
    ?>
    <form class="card" method="post" autocomplete="off">
        <label><?= t('Passwort') ?></label>
        <input type="password" name="password" autofocus required>
        <label class="chk chk--remember"><input type="checkbox" name="remember" value="1"> <?= t('Angemeldet bleiben') ?></label>
        <button class="btn btn--primary"><?= icon('lock') ?><?= t('Anmelden') ?></button>
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
$trash     = load_trash();
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
      <p class="sub"><?= t('URL-Weiterleitungen &amp; QR-Codes') ?></p>
    </div>
  </div>
  <div class="topbar-actions">
    <select id="lang" aria-label="<?= t('Sprache') ?>" title="<?= t('Sprache') ?>" data-self="<?= e($self) ?>">
      <option value="de"<?= $lang === 'de' ? ' selected' : '' ?>>Deutsch</option>
      <option value="en"<?= $lang === 'en' ? ' selected' : '' ?>>English</option>
    </select>
    <select id="theme" aria-label="<?= t('Darstellung') ?>" title="<?= t('Darstellung') ?>">
      <option value="system"><?= t('System') ?></option>
      <option value="light"><?= t('Hell') ?></option>
      <option value="dark"><?= t('Dunkel') ?></option>
    </select>
    <a class="btn btn--ghost btn--small" href="<?= e($self) ?>?logout"><?= icon('logout') ?><?= t('Abmelden') ?></a>
  </div>
</div>

<?php render_toasts($flashMsg ? [$flashMsg] : []); ?>

<div class="panel">
  <form method="post" autocomplete="off">
    <input type="hidden" name="action" value="add">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <div class="bar">
      <label class="field grow">
        <span class="field-label"><?= t('Ziel-URL') ?></span>
        <input type="text" name="url" placeholder="https://ziel-adresse.de/…" required>
      </label>
    </div>
    <div class="bar">
      <label class="field grow2">
        <span class="field-label"><?= t('Gewünschte Short-URL') ?></span>
        <input type="text" name="slug" placeholder="<?= t('leer lassen für zufälligen Wert') ?>" pattern="[a-z0-9\-]*">
      </label>
      <label class="field field--group">
        <span class="field-label"><?= t('Gruppe') ?></span>
        <select name="group"><?= group_options($groups, '') ?></select>
      </label>
      <label class="field field--date">
        <span class="field-label"><?= t('Ablaufdatum (optional)') ?></span>
        <span class="datefield">
          <input type="date" name="expires" title="Nach diesem Tag ist der Link gesperrt">
          <button type="button" class="btn btn--ghost btn--small datefield-clear" data-clear-date title="<?= t('Ablauf entfernen') ?>"><?= icon('x') ?></button>
        </span>
      </label>
    </div>
    <div class="bar bar--end">
      <label class="field grow">
        <span class="field-label"><?= t('Titel / Notiz') ?></span>
        <input type="text" name="title" placeholder="optional – z. B. „Intro-Video“">
      </label>
      <button class="btn btn--primary"><?= icon('plus') ?><?= t('Hinzufügen') ?></button>
    </div>
  </form>

  <form class="bar bar--end" method="post" autocomplete="off">
    <input type="hidden" name="action" value="group_add">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <label class="field grow">
      <span class="field-label"><?= t('Neue Gruppe / Projekt') ?></span>
      <input type="text" name="group_name" placeholder="z. B. Bachelorarbeit" maxlength="40" required>
    </label>
    <button class="btn btn--ghost"><?= icon('folder') ?><?= t('Gruppe anlegen') ?></button>
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
  <label class="chk"><input type="checkbox" id="selall"> <span id="selcount" data-tpl="<?= e(t('%d markiert')) ?>"><?= t('%d markiert', 0) ?></span></label>
  <span class="bulk-spacer"></span>
  <select name="group" aria-label="<?= t('Gruppe') ?>"><?= group_options($groups, '') ?></select>
  <button class="btn btn--ghost btn--small" name="op" value="move"><?= icon('folder') ?><?= t('Verschieben') ?></button>
  <button class="btn btn--ghost btn--small" name="op" value="reset"><?= icon('bars') ?><?= t('Zähler&nbsp;0') ?></button>
  <button class="btn btn--danger btn--small" name="op" value="delete" data-confirm="<?= t('Markierte Links wirklich löschen?') ?>"><?= icon('trash') ?><?= t('Löschen') ?></button>
</form>

<?php if ($links): ?>
  <div class="listctl">
    <div class="search">
      <?= icon('search') ?>
      <input type="text" id="search" placeholder="<?= t('Suchen … Kürzel, Titel, URL oder Gruppe') ?>" autocomplete="off">
    </div>
    <label class="ctl"><span class="ctl-label"><?= t('Sortieren') ?></span>
      <select id="sort">
        <option value="new"><?= t('Neueste zuerst') ?></option>
        <option value="old"><?= t('Älteste zuerst') ?></option>
        <option value="clicks"><?= t('Meiste Aufrufe') ?></option>
        <option value="az"><?= t('A – Z (Kürzel)') ?></option>
      </select>
    </label>
    <label class="ctl"><span class="ctl-label"><?= t('Anzeigen') ?></span>
      <select id="filter">
        <option value="all"><?= t('Alle') ?></option>
        <option value="active"><?= t('Nur aktive') ?></option>
        <option value="expired"><?= t('Nur abgelaufene') ?></option>
      </select>
    </label>
  </div>
<?php endif; ?>

<form id="dndform" method="post" hidden>
  <input type="hidden" name="action" value="move">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
  <input type="hidden" name="slug" value="">
  <input type="hidden" name="group" value="">
</form>

<?php if (!$links): ?>
  <p class="muted empty"><?= t('Noch keine Links angelegt.') ?></p>
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
          <button class="btn btn--primary btn--small"><?= icon('check') ?><?= t('Speichern') ?></button>
          <a class="btn btn--ghost btn--small" href="<?= e($self) ?>"><?= icon('x') ?><?= t('Abbrechen') ?></a>
        </form>
      <?php else: ?>
        <h2><?= e($g) ?><span class="count"><?= count($sections[$g]) ?></span></h2>
        <span class="group-actions">
          <a class="btn btn--ghost btn--small" href="<?= e($self) ?>?editgroup=<?= urlencode($g) ?>"><?= icon('edit') ?><?= t('Umbenennen') ?></a>
          <form class="inline" method="post" data-confirm="<?= e(t('Gruppe „%s“ löschen? Links wandern zu „ohne Gruppe".', $g)) ?>">
            <input type="hidden" name="action" value="group_delete">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="group" value="<?= e($g) ?>">
            <button class="btn btn--ghost btn--small"><?= icon('trash') ?><?= t('Löschen') ?></button>
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
      <h2><?= t('Ohne Gruppe') ?><span class="count"><?= count($ungrouped) ?></span></h2>
    </div>
    <?php render_table($ungrouped, $base, $self, $csrf, $editing, $groups, $clicks); ?>
  </section>
<?php endif; ?>

<?php if ($links): ?><p id="noresults" class="muted empty" hidden><?= t('Keine Treffer für die Suche.') ?></p><?php endif; ?>

<dialog id="qrdlg" class="qrdlg">
  <div class="qrdlg-head">
    <strong id="qrTitle"><?= t('QR-Code') ?></strong>
    <button type="button" class="btn btn--ghost btn--small" id="qrClose"><?= icon('x') ?><?= t('Schließen') ?></button>
  </div>
  <div class="qrdlg-body">
    <div class="qrprev" id="qrPrev"></div>
    <div class="qropts">
      <label><?= t('Fehlerkorrektur') ?>
        <select id="qrEcl">
          <option value="L"><?= t('L – niedrig (7 %)') ?></option>
          <option value="M" selected><?= t('M – mittel (15 %)') ?></option>
          <option value="Q"><?= t('Q – hoch (25 %)') ?></option>
          <option value="H"><?= t('H – maximal (30 %)') ?></option>
        </select>
      </label>
      <div class="qrrow">
        <label><?= t('Modulgröße (px)') ?><input type="number" id="qrScale" min="2" max="40" value="8"></label>
        <label><?= t('Rand (Module)') ?><input type="number" id="qrMargin" min="0" max="16" value="4"></label>
      </div>
      <div class="qrrow">
        <label><?= t('Vordergrund') ?><input type="color" id="qrFg" value="#000000"></label>
        <label><?= t('Hintergrund') ?><input type="color" id="qrBg" value="#ffffff"></label>
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
  <summary><?= t('Export / Import') ?></summary>
  <p class="toolbtns">
    <a class="btn btn--ghost btn--small" href="<?= e($self) ?>?export=1"><?= icon('download') ?><?= t('Export – JSON herunterladen') ?></a>
    <?php if ($links): ?><button type="button" id="qrAllZip" class="btn btn--ghost btn--small"><?= icon('qr') ?><?= t('Alle QR-Codes (ZIP)') ?></button><?php endif; ?>
  </p>
  <form class="bar" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="action" value="import">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="file" name="file" accept="application/json,.json,text/csv,.csv" required>
    <label class="chk"><input type="checkbox" name="merge" value="1"> <?= t('mit Bestand zusammenführen') ?></label>
    <button class="btn btn--ghost"><?= icon('upload') ?><?= t('Importieren') ?></button>
  </form>
  <p class="muted">Akzeptiert <strong>JSON</strong> oder <strong>CSV</strong> (Spalten: <code>url,slug,group,title,expires</code>).
  Ohne Häkchen werden alle bestehenden Einträge <strong>ersetzt</strong>. Klick-Zähler werden nicht exportiert.</p>
</details>

<?php if ($hashEditable): ?>
<details class="tools">
  <summary><?= t('Passwort ändern') ?></summary>
  <form class="bar bar--end" method="post" autocomplete="off">
    <input type="hidden" name="action" value="change_password">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <label class="field grow">
      <span class="field-label"><?= t('Aktuelles Passwort') ?></span>
      <input type="password" name="current" autocomplete="current-password" required>
    </label>
    <label class="field grow">
      <span class="field-label"><?= t('Neues Passwort (mind. 8 Zeichen)') ?></span>
      <input type="password" name="new" minlength="8" autocomplete="new-password" required>
    </label>
    <button class="btn btn--primary"><?= icon('lock') ?><?= t('Speichern') ?></button>
  </form>
</details>
<?php endif; ?>

<?php if ($trash): ?>
<details class="tools">
  <summary><?= t('Papierkorb') ?> <span class="count"><?= count($trash) ?></span></summary>
  <table class="trashlist">
    <?php foreach ($trash as $tslug => $ti): ?>
    <tr>
      <td><span class="slugmono"><?= e($tslug) ?></span><?php if (($ti['title'] ?? '') !== ''): ?> <span class="muted"><?= e((string) $ti['title']) ?></span><?php endif; ?></td>
      <td class="target" title="<?= e((string) ($ti['url'] ?? '')) ?>"><?= e((string) ($ti['url'] ?? '')) ?></td>
      <td class="muted nowrap"><?= isset($ti['deleted']) ? e(date('d.m.Y', (int) $ti['deleted'])) : '' ?></td>
      <td class="actions">
        <form class="inline" method="post">
          <input type="hidden" name="action" value="restore">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="slug" value="<?= e($tslug) ?>">
          <button class="btn btn--ghost btn--small"><?= icon('undo') ?><?= t('Wiederherstellen') ?></button>
        </form>
        <form class="inline" method="post" data-confirm="<?= e(t('„%s“ endgültig löschen?', $tslug)) ?>">
          <input type="hidden" name="action" value="trash_delete">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="slug" value="<?= e($tslug) ?>">
          <button class="btn btn--danger btn--small" title="<?= t('Endgültig löschen') ?>" aria-label="<?= t('Endgültig löschen') ?>"><?= icon('trash') ?></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <form class="inline" method="post" data-confirm="<?= t('Papierkorb endgültig leeren?') ?>">
    <input type="hidden" name="action" value="trash_empty">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <button class="btn btn--ghost btn--small"><?= icon('trash') ?><?= t('Papierkorb leeren') ?></button>
  </form>
  <p class="muted"><?= t('Gelöschte Links landen hier und leiten nicht mehr weiter. Wiederherstellen bringt auch den Klick-Zähler zurück.') ?></p>
</details>
<?php endif; ?>

<?php
foot($nonce);
