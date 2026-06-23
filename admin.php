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
define('SHOW_FAVICONS', (bool) ($cfg['favicons'] ?? true));

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
    $dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/') . '/';
    ?>
<!DOCTYPE html>
<html lang="de">
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
              <?php if (SHOW_FAVICONS && $host !== ''): ?><img class="fav" src="https://icons.duckduckgo.com/ip3/<?= e($host) ?>.ico" alt="" width="16" height="16"><?php endif; ?>
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
