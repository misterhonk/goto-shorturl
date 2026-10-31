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

if ($fails) {
    fwrite(STDERR, "lib-Tests FEHLGESCHLAGEN:\n  - " . implode("\n  - ", $fails) . "\n");
    exit(1);
}
echo "lib-Tests: alle bestanden ✓ ($n Schreibvorgänge, $reads Lesevorgänge ohne Fehlbefund)\n";
