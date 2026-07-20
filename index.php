<?php
declare(strict_types=1);

/* Öffentliche Weiterleitung:  <ordner>/<kürzel>  ->  Ziel-URL
 *
 * Versteht das flache Alt-Format {slug:url} und das neue Format
 * {groups:[], links:{slug:{url,group,title,expires,created}}}.
 * Zählt Aufrufe DSGVO-konform (nur ein Zähler je Kürzel in clicks.json –
 * keine IPs, Zeitstempel oder User-Agents). Abgelaufene Links sind gesperrt.
 *
 * Bewusst schlank gehalten (Hot-Path): liest urls.json gezielt selbst, statt
 * den vollen normalize_data()-Pfad aus lib.php zu nutzen.
 */

require __DIR__ . '/lib.php';   // Bootstrap ($cfg, $dataDir, Konstanten) + is_https()

$file = URLS_FILE;
$raw  = json_decode((string) @file_get_contents($file), true);
if (!is_array($raw) && is_file($file . '.bak')) {          // Fallback bei beschädigter Datei
    $raw = json_decode((string) @file_get_contents($file . '.bak'), true);
}
if (!is_array($raw)) $raw = [];

$links = (isset($raw['links']) && is_array($raw['links'])) ? $raw['links'] : $raw;

$slug = (string) ($_GET['slug'] ?? '');
if ($slug === '') {
    // Fallback: Kürzel aus dem Pfad ableiten (robust bei Rewrite-Eigenheiten)
    $path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
    $seg  = rawurldecode(basename($path));
    if ($seg !== '' && $seg !== 'index.php') $slug = $seg;
}

$target    = null;
$expired   = false;
$notyet    = false;
$linkTitle = '';
$linkPass  = '';
$linkPrev  = false;
$expUrl    = '';

if ($slug !== '' && isset($links[$slug])) {
    $entry = $links[$slug];
    if (is_array($entry)) {
        $url       = (string) ($entry['url'] ?? '');
        $expires   = (string) ($entry['expires'] ?? '');
        $starts    = (string) ($entry['starts'] ?? '');
        $linkTitle = (string) ($entry['title'] ?? '');
        $linkPass  = (string) ($entry['pass'] ?? '');
        $linkPrev  = !empty($entry['preview']);
        $expUrl    = (string) ($entry['expires_url'] ?? '');
        $alts      = (isset($entry['alts']) && is_array($entry['alts'])) ? $entry['alts'] : [];
        if ($expires !== '' && $expires < date('Y-m-d'))     $expired = true;
        elseif ($starts !== '' && $starts > date('Y-m-d'))   $notyet  = true;
        elseif ($url !== '')                                 $target  = ($alts ? weighted_pick($url, $alts) : $url);
    } else {
        $target = (string) $entry;
    }
}

// Nicht aktiv (abgelaufen ODER noch nicht gestartet), aber mit Ersatz-URL:
// dorthin weiterleiten statt der Fehlerseite. Nur echte http(s)-Ziele;
// Query-Parameter des Aufrufs werden mitgereicht.
// (merge_query() ist eine Top-Level-Funktion und daher hier bereits verfügbar.)
if (($expired || $notyet) && $expUrl !== ''
    && in_array(strtolower((string) parse_url($expUrl, PHP_URL_SCHEME)), ['http', 'https'], true)) {
    header('Referrer-Policy: no-referrer');
    header('Location: ' . merge_query($expUrl), true, 302);
    exit;
}

// Defense-in-Depth: nur echte http(s)-Ziele weiterleiten
if ($target !== null) {
    $scheme = strtolower((string) parse_url($target, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) $target = null;
}

if (is_https()) header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// Optionaler Quellen-/Kampagnen-Marker (?q=…) – wird DSGVO-sparsam nur als Zähler erfasst
$clickSource = clean_source((string) ($_GET['q'] ?? ''));

// Passwortgeschützte Links: statt 302 zuerst die Passwort-Seite (weiter unten)
$protected = $target !== null && $linkPass !== '';

/* Link-Preview-Bots (WhatsApp, Slack, …) bekommen statt des 302 eine kleine
 * Seite mit Open-Graph-Daten (Titel aus dem Eintrag) – Crawler würden der
 * Weiterleitung sonst bis zur Zielseite folgen und deren Vorschau zeigen.
 * Menschen erhalten weiterhin sofort das 302; Bot-Aufrufe zählen nicht als
 * Klick. Geschützte Links zeigen ohnehin allen dieselbe Passwort-Seite. */
$isPreviewBot = $target !== null && !$protected && preg_match(
    '/facebookexternalhit|whatsapp|twitterbot|slackbot|slack-imgproxy|telegrambot'
    . '|linkedinbot|discordbot|pinterest|skypeuripreview|mastodon|bluesky|redditbot'
    . '|iframely|embedly/i',
    (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')) === 1;

/* Zusätzliche Query-Parameter des Aufrufs (z. B. ?utm_source=flyer) an die
 * Ziel-URL anhängen – so lässt sich ein gedruckter Kurzlink pro Kanal tracken.
 * Ein evtl. Fragment (#…) im Ziel bleibt hinter den Parametern erhalten. */
function merge_query(string $target): string {
    $params = $_GET;
    unset($params['slug']);   // internes Routing-Artefakt (Rewrite-Variante B)
    unset($params['q']);      // GOTO-Quellen-Marker – wird intern gezählt, nicht ans Ziel gereicht
    if (!$params) return $target;
    $frag = '';
    if (($p = strpos($target, '#')) !== false) {
        $frag   = substr($target, $p);
        $target = substr($target, 0, $p);
    }
    return $target . (strpos($target, '?') === false ? '?' : '&')
         . http_build_query($params) . $frag;
}

// Aufruf zählen (reiner Zähler, datensparsam). $source = optionaler Quellen-Marker (?q=…)
function count_click(string $slug, string $source = ''): void {
    $fp = @fopen(CLICKS_FILE, 'c+');
    if ($fp && flock($fp, LOCK_EX)) {
        $cur    = stream_get_contents($fp);
        $clicks = $cur ? json_decode($cur, true) : [];
        if (!is_array($clicks)) $clicks = [];
        // Format: { slug: { t: gesamt, d: { "YYYY-MM-DD": n }, s: { quelle: n } } }  (Alt-Format int wird migriert)
        $rec = $clicks[$slug] ?? null;
        if (!is_array($rec)) $rec = ['t' => (int) ($rec ?? 0), 'd' => []];
        $rec['t'] = (int) ($rec['t'] ?? 0) + 1;
        $today = date('Y-m-d');
        $rec['d'][$today] = (int) (($rec['d'][$today] ?? 0)) + 1;
        if (count($rec['d']) > 90) {                       // nur letzte 90 Tage behalten
            $cut = date('Y-m-d', time() - 90 * 86400);
            foreach ($rec['d'] as $day => $n) if ($day < $cut) unset($rec['d'][$day]);
        }
        if ($source !== '') {                              // Quellen-Zähler (max. 50 verschiedene je Link)
            $s = (isset($rec['s']) && is_array($rec['s'])) ? $rec['s'] : [];
            if (isset($s[$source]) || count($s) < 50) {
                $s[$source] = (int) ($s[$source] ?? 0) + 1;
                $rec['s'] = $s;
            }
        }
        $clicks[$slug] = $rec;
        rewind($fp);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($clicks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    if ($fp) fclose($fp);
}

if ($target !== null && !$isPreviewBot && !$protected && !$linkPrev) {
    count_click($slug, $clickSource);
    header('Referrer-Policy: no-referrer');
    header('Location: ' . merge_query($target), true, 302);
    exit;
}

/* ---- Ab hier keine Weiterleitung: Bot-Vorschau oder Fehlerseite ---- */

// Sprache nur hier laden (Hot-Path der Weiterleitung oben bleibt schlank).
// Wie admin.php: Cookie > config.php > Deutsch; Übersetzung über lang.php.
$LANG_EN = (array) (@include __DIR__ . '/lang.php');
$lang = 'de';
if (in_array((string) ($_COOKIE['goto_lang'] ?? ''), ['de', 'en'], true))              $lang = (string) $_COOKIE['goto_lang'];
elseif (is_array($cfg) && in_array((string) ($cfg['lang'] ?? ''), ['de', 'en'], true)) $lang = (string) $cfg['lang'];
$t = function (string $de) use ($lang, $LANG_EN): string {
    return ($lang === 'en' && isset($LANG_EN[$de])) ? $LANG_EN[$de] : $de;
};
$ee = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

$dirUrl  = rtrim(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';
$baseUrl = (is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dirUrl;
// Eine Nonce pro Antwort – für die CSP-Header und ein evtl. Inline-Script.
$nonce = base64_encode(random_bytes(16));

// Kleine, in sich geschlossene Seite: Favicon, Hell/Dunkel-Modus, zentrierte
// Karte im GOTO-Look. $extraHead/$bodyHtml müssen bereits escaped sein.
function goto_page(string $lang, string $dirUrl, string $title, string $extraHead, string $bodyHtml, string $nonce): void {
    header('X-Goto-App: 1');   // Marker für die Diagnose (Rewrite-Check im Admin)
    header('Content-Type: text/html; charset=utf-8');
    // Härtung der öffentlichen Seiten (u. a. Clickjacking-Schutz für die
    // Passwort-Seite). Inline-<style>/<script> nur mit Nonce erlaubt.
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('X-Robots-Tag: noindex, nofollow');
    if (is_https()) header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    header(
        "Content-Security-Policy: default-src 'none'; img-src 'self' data:; "
        . "style-src 'self' 'nonce-$nonce'; script-src 'nonce-$nonce'; "
        . "form-action 'self'; base-uri 'none'; frame-ancestors 'none'"
    );
    $h  = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $d  = $h($dirUrl);
    $ti = $h($title);
    $la = $h($lang);
    echo <<<HTML
<!DOCTYPE html>
<html lang="{$la}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<link rel="icon" type="image/svg+xml" href="{$d}assets/favicon.svg">
<link rel="icon" href="{$d}favicon.ico" sizes="48x48">
<link rel="apple-touch-icon" href="{$d}assets/apple-touch-icon.png">
<meta name="theme-color" content="#f4f5f7" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0e1014" media="(prefers-color-scheme: dark)">
<title>{$ti}</title>
{$extraHead}<style nonce="{$nonce}">
:root{--bg:#f4f5f7;--card:#fff;--fg:#1f2328;--muted:#6b7280;--line:#ebedf0}
@media (prefers-color-scheme:dark){:root{--bg:#0e1014;--card:#171a21;--fg:#e7e9ec;--muted:#99a1ad;--line:#242935}}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:grid;place-items:center;background:var(--bg);color:var(--fg);
     font:16px/1.55 system-ui,-apple-system,"Segoe UI",sans-serif;padding:1rem}
.card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:2.2rem 2rem;
      max-width:26rem;width:100%;text-align:center;
      box-shadow:0 1px 2px rgba(16,24,40,.04),0 6px 20px rgba(16,24,40,.06)}
.mark{display:inline-flex;align-items:center;justify-content:center;width:56px;height:56px;
      border-radius:16px;background:linear-gradient(135deg,#6366f1,#2563eb)}
.mark svg{width:30px;height:30px}
h1{font-size:1.15rem;margin:1rem 0 .35rem}
p{color:var(--muted);margin:.25rem 0;overflow-wrap:anywhere}
.btn{display:inline-block;margin-top:1.1rem;padding:.6rem 1.2rem;border-radius:10px;font-weight:600;
     background:linear-gradient(135deg,#6366f1,#2563eb);color:#fff;text-decoration:none;
     border:0;font-size:1rem;cursor:pointer}
.pwform{margin-top:1rem}
.pwform input{width:100%;padding:.65rem .8rem;border:1px solid rgba(128,128,128,.35);border-radius:10px;
     font:inherit;background:var(--card);color:var(--fg);text-align:center}
.pwform input:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 4px rgba(37,99,235,.2)}
.err{color:#e03131;font-size:.88rem;margin-top:.6rem}
.mt{margin-top:.9rem}
</style>
</head>
<body>
<div class="card">
<span class="mark"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h12M12 6l6 6-6 6"/></svg></span>
{$bodyHtml}
</div>
</body>
</html>
HTML;
}

if ($protected) {
    /* Passwort-Seite: Zugang erst nach richtigem Link-Passwort (bcrypt-Hash im
     * Eintrag). Brute-Force-Bremse je IP+Kürzel über .ht_attempts.json. Die
     * Ziel-URL taucht vor der Freigabe nirgends in der Antwort auf. */
    $attFile = $dataDir . '/.ht_attempts.json';
    $attKey  = 'pw:' . hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $slug);
    $err     = '';
    $now     = time();
    $att     = load_json($attFile);
    $rec     = (array) ($att[$attKey] ?? ['count' => 0, 'until' => 0]);
    $locked  = (int) ($rec['until'] ?? 0) > $now;

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['linkpw'])) {
        if ($locked) {
            $err = sprintf($t('Zu viele Fehlversuche. Bitte %d Min. warten.'),
                           (int) ceil(((int) $rec['until'] - $now) / 60));
        } elseif (password_verify((string) $_POST['linkpw'], $linkPass)) {
            if (isset($att[$attKey])) { unset($att[$attKey]); save_json($attFile, $att); }
            count_click($slug, $clickSource);
            header('Referrer-Policy: no-referrer');
            header('Location: ' . merge_query($target), true, 302);
            exit;
        } else {
            $rec['count'] = (int) ($rec['count'] ?? 0) + 1;
            if ($rec['count'] >= 8) $rec = ['count' => 0, 'until' => $now + 900];
            foreach ($att as $k => $r) {   // abgelaufene Einträge aufräumen
                if (($r['until'] ?? 0) < $now && ($r['count'] ?? 0) === 0) unset($att[$k]);
            }
            $att[$attKey] = $rec;
            if (count($att) > 2000) {
                $att = array_filter($att, fn($r) => ($r['until'] ?? 0) >= $now);
                $att[$attKey] = $rec;
            }
            save_json($attFile, $att);
            usleep(300000);
            $err = ((int) ($rec['until'] ?? 0) > $now)
                ? sprintf($t('Zu viele Fehlversuche. Bitte %d Min. warten.'),
                          (int) ceil(((int) $rec['until'] - $now) / 60))
                : $t('Falsches Passwort.');
        }
    } elseif ($locked) {
        $err = sprintf($t('Zu viele Fehlversuche. Bitte %d Min. warten.'),
                       (int) ceil(((int) $rec['until'] - $now) / 60));
    }

    $pgTitle  = $linkTitle !== '' ? $linkTitle : 'GOTO – ' . $t('Kurzlink');
    $ogDesc   = $t('Passwortgeschützter Link');
    $shortUrl = $baseUrl . rawurlencode($slug);
    // OG-Daten für geteilte geschützte Links – ohne jeden Hinweis aufs Ziel
    $extra = '<meta property="og:type" content="website">' . "\n"
           . '<meta property="og:site_name" content="GOTO">' . "\n"
           . '<meta property="og:title" content="' . $ee($pgTitle) . '">' . "\n"
           . '<meta property="og:description" content="' . $ee($ogDesc) . '">' . "\n"
           . '<meta property="og:url" content="' . $ee($shortUrl) . '">' . "\n"
           . '<meta property="og:image" content="' . $ee($baseUrl . 'assets/og.png') . '">' . "\n"
           . '<meta name="twitter:card" content="summary_large_image">' . "\n";
    $body = '<h1>' . $ee($pgTitle) . '</h1>'
          . '<p>' . $ee($t('Dieser Link ist passwortgeschützt.')) . '</p>'
          . '<form class="pwform" method="post">'
          . '<input type="password" name="linkpw" required autofocus aria-label="' . $ee($t('Passwort')) . '" placeholder="' . $ee($t('Passwort')) . '">'
          . '<button class="btn">' . $ee($t('Weiter')) . '</button>'
          . '</form>'
          . ($err !== '' ? '<p class="err">' . $ee($err) . '</p>' : '');
    goto_page($lang, $dirUrl, $pgTitle, $extra, $body, $nonce);
    exit;
}

if ($target !== null && !$isPreviewBot) {
    /* Vorschau-Zwischenseite (opt-in je Link): zeigt Titel und Ziel-Domain,
     * leitet nach 3 Sekunden automatisch weiter (Button für Sofort-Klick).
     * Der Aufruf zählt hier – der Besucher hat den Link geöffnet. */
    count_click($slug, $clickSource);
    $dest    = merge_query($target);
    $host    = (string) parse_url($target, PHP_URL_HOST);
    $pgTitle = $linkTitle !== '' ? $linkTitle : 'GOTO – ' . $t('Kurzlink');
    $extra   = '<meta http-equiv="refresh" content="3;url=' . $ee($dest) . '">' . "\n"
             . '<meta name="referrer" content="no-referrer">' . "\n";
    $body = '<h1>' . $ee($pgTitle) . '</h1>'
          . '<p>' . $ee(sprintf($t('Weiterleitung zu %s'), $host)) . '</p>'
          . '<a class="btn" href="' . $ee($dest) . '" rel="noreferrer">' . $ee($t('Jetzt weiter')) . '</a>'
          . '<p class="muted mt">'
          . sprintf($ee($t('Automatische Weiterleitung in %s Sekunden …')), '<span id="cd">3</span>')
          . '</p>'
          . '<script nonce="' . $ee($nonce) . '">(function(){var n=3,e=document.getElementById("cd");'
          . 'setInterval(function(){n=Math.max(0,n-1);if(e)e.textContent=n;},1000);})();</script>';
    goto_page($lang, $dirUrl, $pgTitle, $extra, $body, $nonce);
    exit;
}

if ($target !== null) {
    // Bot-Vorschau: Open-Graph-Daten mit dem Titel aus dem Eintrag.
    $host     = (string) parse_url($target, PHP_URL_HOST);
    $ogTitle  = $linkTitle !== '' ? $linkTitle : 'GOTO – ' . $t('Kurzlink');
    $ogDesc   = sprintf($t('Weiterleitung zu %s'), $host);
    $shortUrl = $baseUrl . rawurlencode($slug);
    $extra = '<meta property="og:type" content="website">' . "\n"
           . '<meta property="og:site_name" content="GOTO">' . "\n"
           . '<meta property="og:title" content="' . $ee($ogTitle) . '">' . "\n"
           . '<meta property="og:description" content="' . $ee($ogDesc) . '">' . "\n"
           . '<meta property="og:url" content="' . $ee($shortUrl) . '">' . "\n"
           . '<meta property="og:image" content="' . $ee($baseUrl . 'assets/og.png') . '">' . "\n"
           . '<meta property="og:image:width" content="1200">' . "\n"
           . '<meta property="og:image:height" content="630">' . "\n"
           . '<meta name="twitter:card" content="summary_large_image">' . "\n"
           . '<meta name="description" content="' . $ee($ogDesc) . '">' . "\n"
           . '<meta http-equiv="refresh" content="1;url=' . $ee(merge_query($target)) . '">' . "\n";
    $body = '<h1>' . $ee($ogTitle) . '</h1>'
          . '<p>' . $ee($ogDesc) . ' …</p>'
          . '<a class="btn" href="' . $ee(merge_query($target)) . '">' . $ee($t('Weiter zur Zielseite')) . '</a>';
    goto_page($lang, $dirUrl, $ogTitle, $extra, $body, $nonce);
    exit;
}

http_response_code($expired ? 410 : 404);
if ($expired) {
    $head = $t('Link abgelaufen');    $msg = $t('Dieser Link ist abgelaufen.');           $tab = $t('Abgelaufen');
} elseif ($notyet) {
    $head = $t('Link noch nicht aktiv'); $msg = $t('Dieser Link ist noch nicht aktiv.');    $tab = $t('Noch nicht aktiv');
} else {
    $head = $t('Kurz-URL nicht gefunden'); $msg = $t('Diese Kurz-URL existiert nicht (mehr).'); $tab = $t('Nicht gefunden');
}
$body = '<h1>' . $ee($head) . '</h1>'
      . '<p>' . $ee($msg) . '</p>';
goto_page($lang, $dirUrl, $tab, '', $body, $nonce);
