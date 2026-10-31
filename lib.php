<?php
declare(strict_types=1);

/* ------------------------------------------------------------------ *
 *  GOTO – gemeinsame Basis für index.php, admin.php und api.php.
 *
 *  Enthält Bootstrap (Konfiguration, Datenpfad, Fehler-Logging,
 *  gemeinsame Pfad-Konstanten) sowie die reinen Helfer für Daten-,
 *  URL- und Slug-Verarbeitung. Bewusst OHNE Session, HTTP-Header oder
 *  Ausgabe – das bleibt Sache des jeweiligen Einstiegspunkts.
 *
 *  Datenmodell (urls.json):
 *    { "groups": ["Projekt A"],
 *      "links": { "slug": {
 *         "url":"", "group":"", "title":"", "expires":"YYYY-MM-DD",
 *         "starts":"YYYY-MM-DD",    // vor diesem Tag noch nicht aktiv ("" = sofort)
 *         "created":0, "pass":"",   // pass: bcrypt-Hash, ""=ohne Passwort
 *         "preview":false,          // true = Vorschau-Seite vor der Weiterleitung
 *         "alts":[{"url":"","weight":1}]  // weitere Ziele (gewichtete Rotation)
 *      } } }
 * ------------------------------------------------------------------ */

const GOTO_VERSION = '1.3.3';

@ini_set('display_errors', '0');   // Produktion: keine PHP-Fehler an Besucher ausgeben

$cfg = @include __DIR__ . '/config.php';
if (!is_array($cfg)) $cfg = [];

// Datenverzeichnis – idealerweise außerhalb des Web-Roots (siehe config.php).
$dataDir = rtrim((string) ($cfg['data_dir'] ?? __DIR__), '/');

// Fehler nicht anzeigen, aber in eine geschützte Datei loggen (Ferndiagnose)
@ini_set('log_errors', '1');
@ini_set('error_log', $dataDir . '/.ht_error.log');

// Von allen Einstiegspunkten genutzte Dateien.
define('URLS_FILE',       $dataDir . '/urls.json');
define('CLICKS_FILE',     $dataDir . '/clicks.json');
define('TRASH_FILE',      $dataDir . '/.ht_trash.json');
define('API_TOKENS_FILE', $dataDir . '/.ht_apitokens.json');
// Reine Sperrdatei für urls.json-Schreibzyklen (bleibt leer). Name mit „.ht_",
// damit die .htaccess-Regel für Punkt-Dateien sie ohnehin abdeckt.
define('DATA_LOCK_FILE',  $dataDir . '/.ht_data.lock');
define('ATTEMPTS_FILE',   $dataDir . '/.ht_attempts.json');   // Brute-Force-Bremse (Admin + Link-Passwort)

// Kürzel, die nie ein Link sein dürfen: Einstiegspunkte und echte Ordner
// (assets/, backups/) – Apache/nginx liefern dort den Ordner aus, der Link
// käme nie bei GOTO an.
const RESERVED_SLUGS = ['admin', 'index', 'api', 'assets', 'backups'];

/* ---- HTTPS-Erkennung (inkl. Reverse-Proxy) ----------------------- */

// Öffentliche Basis-Adresse des GOTO-Ordners, z. B. https://example.org/goto/
function base_url(): string {
    return (is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
         . rtrim(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';
}

// Oberflächensprache: Cookie > config.php > Deutsch
function ui_lang(array $cfg): string {
    foreach ([(string) ($_COOKIE['goto_lang'] ?? ''), (string) ($cfg['lang'] ?? '')] as $l) {
        if (in_array($l, ['de', 'en'], true)) return $l;
    }
    return 'de';
}

function is_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
}

/* ---- JSON lesen / atomar schreiben ------------------------------- */

function load_json(string $file): array {
    if (!is_file($file)) return [];
    $data = json_decode((string) file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function save_json(string $file, array $data): bool {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) return false;                    // ungültige Daten -> nicht schreiben
    // Eindeutiger Temp-Name je Prozess: bei einem gemeinsamen „.tmp" könnte
    // Prozess A die halb geschriebene Datei von Prozess B an ihr Ziel umbenennen.
    $tmp = $file . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    if (@rename($tmp, $file)) return true;                // rename ist atomar (gleiches Dateisystem)
    @unlink($tmp);
    return false;
}

/* ---- Lesen-Ändern-Schreiben unter Sperre ---------------------------
 * load_json() + save_json() nacheinander verliert bei gleichzeitigen
 * Zugriffen Änderungen (letzter Schreiber gewinnt): zwei parallele Anfragen
 * lesen denselben Stand und überschreiben sich gegenseitig. Das trifft
 * ausgerechnet die Zähler, auf die Verlass sein muss – Login-Sperre,
 * API-Rate-Limit, Protokoll.
 *
 * with_locked_json() hält den ganzen Zyklus exklusiv gesperrt. Die Sperre
 * liegt auf einer eigenen Punkt-Datei (von allen Server-Configs gesperrt),
 * geschrieben wird per save_json() – also Temp-Datei + rename. NICHT die
 * Datei selbst leeren und neu beschreiben: Leser ohne Sperre sähen sie dann
 * kurz leer, und ein abgebrochener Schreibvorgang (Platte voll) ließe sie
 * dauerhaft leer – bei .ht_auth.json hieße das: Einrichtung offen für jeden.
 * $fn bekommt den aktuellen Stand und gibt den neuen zurück; null = nicht
 * schreiben. Rückgabe: true, wenn gelesen und ggf. geschrieben werden konnte. */
function with_locked_json(string $file, callable $fn): bool {
    $lock = @fopen(dirname($file) . '/.' . basename($file) . '.lock', 'c');
    if (!$lock) return false;
    try {
        if (!flock($lock, LOCK_EX)) return false;
        $new = $fn(load_json($file));
        return $new === null || save_json($file, $new);   // null = nichts zu ändern
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/* Exklusive Sperre für einen kompletten urls.json-Zyklus (load_data() …
 * save_data()). Die Sperre liegt auf einer eigenen Datei, weil save_data()
 * urls.json per rename() ersetzt – eine Sperre auf urls.json selbst wäre
 * danach wertlos. Das zurückgegebene Handle offen halten, solange der Zyklus
 * läuft; PHP gibt die Sperre spätestens am Skriptende frei.
 * Rückgabe: Handle oder null (dann läuft es wie bisher ungesperrt weiter –
 * eine fehlende Sperrdatei darf das Speichern nicht verhindern). */
function lock_data() {
    $fp = @fopen(DATA_LOCK_FILE, 'c');
    if (!$fp) return null;
    if (!flock($fp, LOCK_EX)) { fclose($fp); return null; }
    return $fp;
}
function unlock_data($fp): void {
    if ($fp) { flock($fp, LOCK_UN); fclose($fp); }
}

/* ---- Validierung / Bereinigung ----------------------------------- */

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

/* Nur echte Strings aus $_GET/$_POST übernehmen: ?q[]=x liefert sonst ein Array,
 * das der (string)-Cast zu „Array" macht (samt PHP-Warnung im Log). */
function req_str($v): string { return is_string($v) ? $v : ''; }

/* Quellen-/Kampagnen-Marker (?gq=…): kleiner, dateisystem-/JSON-sicherer Bezeichner.
 * Datensparsam – der Name wird vom Nutzer selbst vergeben (kein PII).
 * ACHTUNG: cleanSource() in assets/app.js ist die Spiegelung davon – Änderungen
 * hier müssen dort mitgezogen werden, sonst zählen gedruckte Codes anders. */
function clean_source(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9._\-]+/', '-', $s) ?? '';
    $s = trim($s, '-');
    return substr($s, 0, 32);
}

function clean_date(string $s): string {
    $s = trim($s);
    if ($s === '') return '';
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return ($d && $d->format('Y-m-d') === $s) ? $s : '';
}

/* ---- Zeitfenster eines Links -------------------------------------
 * Tagesgenauer Vergleich in der Server-Zeitzone. Beide Prädikate gehören
 * hierher und nicht in die Einstiegspunkte: index.php entscheidet damit über
 * die Weiterleitung, admin.php über die Chips – das darf nicht auseinander-
 * laufen. */

function is_expired(string $expires): bool {
    return $expires !== '' && $expires < date('Y-m-d');
}
function is_pending(string $starts): bool {
    return $starts !== '' && $starts > date('Y-m-d');
}

/* ---- Rotation / mehrere Ziele ------------------------------------ */

// Weitere Ziele auf gültige [{url,weight}] normalisieren (max. 20, Gewicht 1–100)
function normalize_alts($raw): array {
    if (!is_array($raw)) return [];
    if (isset($raw['url'])) $raw = [$raw];   // einzelnes Objekt statt Liste – Gewicht sonst verloren
    $out = [];
    foreach ($raw as $a) {
        $u = is_array($a) ? (string) ($a['url'] ?? '') : (string) $a;
        $u = trim($u);
        if (!valid_url($u)) continue;
        $w = is_array($a) ? (int) ($a['weight'] ?? 1) : 1;
        $w = max(1, min(100, $w));
        $out[] = ['url' => $u, 'weight' => $w];
        if (count($out) >= 20) break;
    }
    return $out;
}

// Freitext (eine URL je Zeile, optional abschließendes Gewicht) → [{url,weight}]
function parse_alts_text(string $text): array {
    $out = [];
    foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $weight = 1;
        // Abschließende Ziffern = Gewicht (beliebig lang; normalize_alts kappt auf 1–100).
        // Sonst bliebe die Zahl Teil der „URL" und das ganze Ziel fiele stillschweigend weg.
        if (preg_match('/^(.*\S)\s+(\d+)$/', $line, $m)) { $line = $m[1]; $weight = (int) $m[2]; }
        $out[] = ['url' => $line, 'weight' => $weight];
    }
    return normalize_alts($out);
}

// Zielauswahl: gewichteter Zufall über das Primärziel (Gewicht 1) + weitere Ziele.
// Zustandslos – kein zusätzlicher Schreibzugriff im Hot-Path.
function weighted_pick(string $primary, array $alts): string {
    $pool = [];
    if (valid_url($primary)) $pool[] = ['url' => $primary, 'weight' => 1];
    foreach (normalize_alts($alts) as $a) $pool[] = $a;
    if (!$pool) return $primary;
    $total = 0; foreach ($pool as $p) $total += $p['weight'];
    $r = random_int(1, $total);
    foreach ($pool as $p) { $r -= $p['weight']; if ($r <= 0) return $p['url']; }
    return $pool[0]['url'];
}

function random_slug(array $links): string {
    $alphabet = 'abcdefghijkmnpqrstuvwxyz23456789';   // ohne l,o,0,1
    do {
        $s = '';
        for ($i = 0; $i < 6; $i++) $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    } while (isset($links[$s]) || in_array($s, RESERVED_SLUGS, true));
    return $s;
}

/* ---- Datenmodell (urls.json) ------------------------------------- */

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
        $starts  = is_array($item) ? clean_date((string) ($item['starts'] ?? '')) : '';
        $created = is_array($item) ? (int) ($item['created'] ?? 0) : 0;
        $pass    = is_array($item) ? (string) ($item['pass'] ?? '') : '';
        $preview = is_array($item) && !empty($item['preview']);
        $expUrl  = is_array($item) ? (string) ($item['expires_url'] ?? '') : '';
        if (!valid_url($expUrl)) $expUrl = '';
        $alts    = is_array($item) ? normalize_alts($item['alts'] ?? []) : [];
        if ($group !== '' && !in_array($group, $groups, true)) $groups[] = $group;
        $links[$slug] = ['url' => $url, 'group' => $group, 'title' => $title,
                         'expires' => $expires, 'starts' => $starts, 'created' => $created,
                         'pass' => $pass, 'preview' => $preview, 'expires_url' => $expUrl,
                         'alts' => $alts];
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

/* Tages-Backup: beim ersten Schreibvorgang eines Tages wandert der aktuelle
 * Stand nach backups/urls-JJJJ-MM-TT.json; die letzten 7 Tage bleiben erhalten.
 * (Die .htaccess-Regeln gelten auch im Unterordner – *.json ist gesperrt.) */
function backup_rotate(): void {
    $dir   = dirname(URLS_FILE) . '/backups';
    $today = $dir . '/urls-' . date('Y-m-d') . '.json';
    if (is_file($today)) return;                       // heute schon gesichert
    if (!is_dir($dir) && !@mkdir($dir, 0775)) return;  // nicht anlegbar -> still überspringen
    @copy(URLS_FILE, $today);
    $files = glob($dir . '/urls-*.json') ?: [];
    sort($files);
    while (count($files) > 7) @unlink(array_shift($files));
}

function save_data(array $d): bool {
    if (is_file(URLS_FILE)) {
        @copy(URLS_FILE, URLS_FILE . '.bak');   // letzte Schreib-Generation
        backup_rotate();                         // + Tages-Generationen (7 Tage)
    }
    return save_json(URLS_FILE, ['groups' => array_values($d['groups'] ?? []), 'links' => $d['links'] ?? []]);
}

/* ---- Brute-Force-Bremse (Admin-Login + Link-Passwort, dateibasiert) - */

/* Einen Versuch reservieren: prüfen UND hochzählen in einem Schritt, unter
 * einer Sperre. Getrennt (erst prüfen, später zählen) passieren bei
 * gleichzeitigen Anfragen alle das Tor, bevor die erste
 * hochgezählt hat – gemessen 10 statt 5 erlaubter Versuche. Deshalb wird der
 * Versuch VOR der Passwortprüfung verbucht und bei Erfolg per clear_fails()
 * wieder zurückgenommen.
 * Rückgabe: 0 = Versuch erlaubt, sonst verbleibende Sperrsekunden. */
function attempt_begin(string $key, int $max, int $lockSecs): int {
    $now  = time();
    $wait = 0;
    with_locked_json(ATTEMPTS_FILE, function (array $all) use ($key, $max, $lockSecs, $now, &$wait) {
        $rec = (array) ($all[$key] ?? ['count' => 0, 'until' => 0]);
        if ((int) ($rec['until'] ?? 0) > $now) {       // gesperrt -> nicht mitzählen
            $wait = (int) $rec['until'] - $now;
            return null;
        }
        foreach ($all as $k => $r) {                   // abgelaufene Einträge aufräumen
            if (($r['until'] ?? 0) < $now && ($r['count'] ?? 0) === 0) unset($all[$k]);
        }
        $rec['count'] = (int) ($rec['count'] ?? 0) + 1;
        if ($rec['count'] >= $max) $rec = ['count' => 0, 'until' => $now + $lockSecs];
        $all[$key] = $rec;
        // Schutz vor Aufblähen durch verteilte Angriffe: nur aktive Sperren behalten
        if (count($all) > 2000) {
            $all = array_filter($all, fn($r) => ($r['until'] ?? 0) >= $now);
            $all[$key] = $rec;
        }
        return $all;
    });
    return $wait;
}
function clear_fails(string $key): void {
    with_locked_json(ATTEMPTS_FILE, function (array $all) use ($key) {
        unset($all[$key]);
        return $all;
    });
}

/* ---- Klicks & Papierkorb (Admin + API) ---------------------------- */

function load_clicks(): array  { return load_json(CLICKS_FILE); }

// clicks.json wird nur unter Sperre geschrieben (wie count_click() im Hot-Path) –
// sonst überschreibt ein Admin-Speichervorgang gerade eingehende Klicks.
// Entfernt die Datensätze der Kürzel und liefert sie zurück (für den Papierkorb).
function clicks_take(array $slugs): array {
    $out = [];
    with_locked_json(CLICKS_FILE, function (array $cl) use ($slugs, &$out) {
        foreach ($slugs as $s) if (isset($cl[$s])) { $out[$s] = $cl[$s]; unset($cl[$s]); }
        return $out ? $cl : null;
    });
    return $out;
}
function clicks_rename(string $old, string $new): void {
    with_locked_json(CLICKS_FILE, function (array $cl) use ($old, $new) {
        if (!isset($cl[$old])) return null;
        $cl[$new] = $cl[$old];
        unset($cl[$old]);
        return $cl;
    });
}

// Klick-Datensatz kann int (Alt-Format) oder {t:total, d:{tag:n}} sein
function clicks_total(array $clicks, string $slug): int {
    $c = $clicks[$slug] ?? 0;
    return is_array($c) ? (int) ($c['t'] ?? 0) : (int) $c;
}
function clicks_days(array $clicks, string $slug): array {
    $c = $clicks[$slug] ?? null;
    return (is_array($c) && isset($c['d']) && is_array($c['d'])) ? $c['d'] : [];
}
// Quellen-Aufschlüsselung { quelle: n } (aus ?gq=…), absteigend sortiert
function clicks_sources(array $clicks, string $slug): array {
    $c = $clicks[$slug] ?? null;
    $s = (is_array($c) && isset($c['s']) && is_array($c['s'])) ? $c['s'] : [];
    arsort($s);
    return $s;
}

function load_trash(): array  { return load_json(TRASH_FILE); }
function save_trash(array $t): bool { return save_json(TRASH_FILE, $t); }
