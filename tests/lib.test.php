<?php
declare(strict_types=1);

/* Test für with_locked_json() aus lib.php – darüber laufen Login-Sperre,
 * API-Rate-Limit, Auth-Datei und Klickzähler. Zwei Zusagen:
 *   1. Gleichzeitige Schreiber verlieren keine Änderung (jede zählt).
 *   2. Ein Leser OHNE Sperre (wie stored_hash() im Admin) sieht nie eine
 *      leere oder halb geschriebene Datei – leer hieße bei .ht_auth.json:
 *      Einrichtung offen für jeden.
 * Aufruf: php tests/lib.test.php   (lokal und in der CI) */

require __DIR__ . '/../lib.php';

// Kindprozess: zählt $argv[3]-mal hoch; das Polster verlängert jeden Schreibvorgang
if (($argv[1] ?? '') === 'worker') {
    for ($i = 0; $i < (int) $argv[3]; $i++) {
        with_locked_json($argv[2], function (array $d) {
            $d['n'] = (int) ($d['n'] ?? 0) + 1;
            $d['pad'] = str_repeat('x', 50000);
            return $d;
        });
    }
    exit(0);
}

$dir = sys_get_temp_dir() . '/goto-libtest-' . getmypid();
mkdir($dir);
$file = $dir . '/counter.json';
save_json($file, ['n' => 0]);

$workers = 8;
$each    = 50;
$procs   = [];
for ($w = 0; $w < $workers; $w++) {
    $procs[] = proc_open([PHP_BINARY, __FILE__, 'worker', $file, (string) $each], [], $pipes);
}

$reads = 0;
$bad   = 0;
do {
    $c = @file_get_contents($file);
    $reads++;
    if ($c === false || $c === '' || !is_array(json_decode($c, true))) $bad++;
    $running = false;
    foreach ($procs as $p) if (proc_get_status($p)['running']) $running = true;
} while ($running);
foreach ($procs as $p) proc_close($p);

$n = (int) (load_json($file)['n'] ?? -1);
foreach (array_merge(glob($dir . '/*') ?: [], glob($dir . '/.*.lock') ?: []) as $f) unlink($f);
rmdir($dir);

$fails = [];
if ($n !== $workers * $each) $fails[] = "Zähler $n statt " . ($workers * $each) . ' – Schreibvorgänge verloren';
if ($bad > 0)                $fails[] = "$bad von $reads Lesevorgängen sahen eine leere/kaputte Datei";

// is_nonhuman_request(): Menschen zählen, Bots/HEAD/Prefetch/Skripte nicht
$chrome = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';
$uaCases = [
    [['HTTP_USER_AGENT' => $chrome], false],
    [['HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1'], false],
    [['HTTP_USER_AGENT' => 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Mobile Safari/537.36'], false],
    [['HTTP_USER_AGENT' => $chrome, 'REQUEST_METHOD' => 'HEAD'], true],
    [['HTTP_USER_AGENT' => $chrome, 'HTTP_SEC_PURPOSE' => 'prefetch;prerender'], true],
    [['HTTP_USER_AGENT' => ''], true],
    [['HTTP_USER_AGENT' => 'curl/8.7.1'], true],
    [['HTTP_USER_AGENT' => 'python-requests/2.32'], true],
    [['HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'], true],
    [['HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0) HeadlessChrome/130.0'], true],
];
foreach ($uaCases as $i => [$srv, $want]) {
    if (is_nonhuman_request($srv) !== $want) $fails[] = "is_nonhuman_request Fall $i erwartet " . var_export($want, true);
}

// ip_is_public(): SSRF-Schutz für Titel-Abruf und Linkcheck
$ipCases = ['127.0.0.1' => false, '10.1.2.3' => false, '172.16.0.1' => false, '192.168.1.1' => false,
    '169.254.169.254' => false, '100.64.1.1' => false, '198.18.0.1' => false, '192.0.0.8' => false,
    '0.0.0.0' => false, '::1' => false, 'fe80::1' => false, 'fc00::1' => false, '::ffff:127.0.0.1' => false,
    '::ffff:10.0.0.1' => false, '64:ff9b::7f00:1' => false, 'kein-ip' => false,
    '1.1.1.1' => true, '93.184.215.14' => true, '2606:4700:4700::1111' => true];
foreach ($ipCases as $ip => $want) {
    if (ip_is_public((string) $ip) !== $want) $fails[] = "ip_is_public($ip) erwartet " . var_export($want, true);
}
if (resolve_public_ip('localhost') !== null) $fails[] = 'resolve_public_ip(localhost) muss null sein';
if (resolve_public_ip('[::1]') !== null)     $fails[] = 'resolve_public_ip([::1]) muss null sein';

if ($fails) {
    fwrite(STDERR, "lib-Tests FEHLGESCHLAGEN:\n  - " . implode("\n  - ", $fails) . "\n");
    exit(1);
}
echo "lib-Tests: alle bestanden ✓ ($n Schreibvorgänge, $reads Lesevorgänge ohne Fehlbefund)\n";
