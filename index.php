<?php
declare(strict_types=1);

@ini_set('display_errors', '0');   // Produktion: keine PHP-Fehler an Besucher ausgeben

/* Öffentliche Weiterleitung:  <ordner>/<kürzel>  ->  Ziel-URL
 *
 * Versteht das flache Alt-Format {slug:url} und das neue Format
 * {groups:[], links:{slug:{url,group,title,expires,created}}}.
 * Zählt Aufrufe DSGVO-konform (nur ein Zähler je Kürzel in clicks.json –
 * keine IPs, Zeitstempel oder User-Agents). Abgelaufene Links sind gesperrt.
 */

$cfg     = @include __DIR__ . '/config.php';
$dataDir = rtrim((string) ((is_array($cfg) ? ($cfg['data_dir'] ?? null) : null) ?? __DIR__), '/');

@ini_set('log_errors', '1');
@ini_set('error_log', $dataDir . '/.ht_error.log');

$file = $dataDir . '/urls.json';
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

$target  = null;
$expired = false;

if ($slug !== '' && isset($links[$slug])) {
    $entry = $links[$slug];
    if (is_array($entry)) {
        $url     = (string) ($entry['url'] ?? '');
        $expires = (string) ($entry['expires'] ?? '');
        if ($expires !== '' && $expires < date('Y-m-d')) $expired = true;
        elseif ($url !== '')                              $target = $url;
    } else {
        $target = (string) $entry;
    }
}

// Defense-in-Depth: nur echte http(s)-Ziele weiterleiten
if ($target !== null) {
    $scheme = strtolower((string) parse_url($target, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) $target = null;
}

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443')
      || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
if ($https) header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

if ($target !== null) {
    // Aufruf zählen (reiner Zähler, datensparsam)
    $fp = @fopen($dataDir . '/clicks.json', 'c+');
    if ($fp && flock($fp, LOCK_EX)) {
        $cur    = stream_get_contents($fp);
        $clicks = $cur ? json_decode($cur, true) : [];
        if (!is_array($clicks)) $clicks = [];
        // Format: { slug: { t: gesamt, d: { "YYYY-MM-DD": n } } }  (Alt-Format int wird migriert)
        $rec = $clicks[$slug] ?? null;
        if (!is_array($rec)) $rec = ['t' => (int) ($rec ?? 0), 'd' => []];
        $rec['t'] = (int) ($rec['t'] ?? 0) + 1;
        $today = date('Y-m-d');
        $rec['d'][$today] = (int) (($rec['d'][$today] ?? 0)) + 1;
        if (count($rec['d']) > 90) {                       // nur letzte 90 Tage behalten
            $cut = date('Y-m-d', time() - 90 * 86400);
            foreach ($rec['d'] as $day => $n) if ($day < $cut) unset($rec['d'][$day]);
        }
        $clicks[$slug] = $rec;
        rewind($fp);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($clicks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    if ($fp) fclose($fp);

    header('Referrer-Policy: no-referrer');
    header('Location: ' . $target, true, 302);
    exit;
}

// Sprache nur im Fehlerfall laden (Hot-Path der Weiterleitung bleibt schlank).
// Wie admin.php: Cookie > config.php > Deutsch; Übersetzung über lang.php.
$LANG_EN = (array) (@include __DIR__ . '/lang.php');
$lang = 'de';
if (in_array((string) ($_COOKIE['goto_lang'] ?? ''), ['de', 'en'], true))                 $lang = (string) $_COOKIE['goto_lang'];
elseif (is_array($cfg) && in_array((string) ($cfg['lang'] ?? ''), ['de', 'en'], true))     $lang = (string) $cfg['lang'];
$t = function (string $de) use ($lang, $LANG_EN): string {
    return ($lang === 'en' && isset($LANG_EN[$de])) ? $LANG_EN[$de] : $de;
};
$ee = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

http_response_code($expired ? 410 : 404);
header('Content-Type: text/html; charset=utf-8');
$msg = $expired
    ? $t('Dieser Link ist abgelaufen.')
    : $t('Diese Kurz-URL existiert nicht (mehr).');
?><!DOCTYPE html>
<html lang="<?= $ee($lang) ?>">
<meta charset="utf-8">
<title><?= $ee($expired ? $t('Abgelaufen') : $t('Nicht gefunden')) ?></title>
<body style="font:16px/1.5 system-ui,sans-serif;max-width:30rem;margin:4rem auto;padding:0 1rem;color:#1f2328">
  <h1 style="font-size:1.2rem"><?= $ee($expired ? $t('Link abgelaufen') : $t('Kurz-URL nicht gefunden')) ?></h1>
  <p style="color:#6b7280"><?= $ee($msg) ?></p>
</body>
</html>
