<?php
/**
 * submit.php — "Run code" endpoint (JSON). Compiles + runs the user's
 * source against a custom stdin via Judge0 (see includes/judge.php), logs
 * the run in `submissions`, and returns the result. CSRF-protected.
 *
 * Dormant unless JUDGE0_URL is configured (returns 503 otherwise).
 * Judging against official test sets is phase 2 — see JUDGE.md.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/judge.php';

// Runs/day allowed for non-admins (admins are unlimited).
const RUN_LIMIT_PER_DAY = 5;

/** Best-effort client IP (Fly sets Fly-Client-IP at the edge). */
function client_ip(): string
{
    if (!empty($_SERVER['HTTP_FLY_CLIENT_IP'])) return $_SERVER['HTTP_FLY_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

header('Content-Type: application/json; charset=utf-8');

function json_out(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['error' => 'Method not allowed.'], 405);
}
if (!judge_enabled()) {
    json_out(['error' => 'Pokretanje koda trenutno nije omogućeno na ovom serveru.'], 503);
}
require_csrf();

$langKey = (string) ($_POST['language'] ?? '');
$source  = (string) ($_POST['source'] ?? '');
$stdin   = (string) ($_POST['stdin'] ?? '');
$taskId  = filter_input(INPUT_POST, 'task_id', FILTER_VALIDATE_INT) ?: null;
$mode    = (($_POST['mode'] ?? 'run') === 'judge') ? 'judge' : 'run';

$langs = judge_languages();
if (!isset($langs[$langKey])) {
    json_out(['error' => 'Nepoznat jezik.'], 422);
}
if (trim($source) === '') {
    json_out(['error' => 'Izvorni kod je prazan.'], 422);
}
if (strlen($source) > 200000 || strlen($stdin) > 1000000) {
    json_out(['error' => 'Ulaz je prevelik.'], 413);
}

$pdo = db();
$ip  = client_ip();

// --- Rate limit: non-admins get RUN_LIMIT_PER_DAY runs per rolling 24h ----
if (!is_admin()) {
    $cutoff = gmdate('Y-m-d H:i:s', time() - 86400);
    $cnt = $pdo->prepare('SELECT COUNT(*) FROM submissions WHERE ip = ? AND created_at >= ?');
    $cnt->execute([$ip, $cutoff]);
    if ((int) $cnt->fetchColumn() >= RUN_LIMIT_PER_DAY) {
        json_out(['error' => 'Dostigli ste dnevni limit od ' . RUN_LIMIT_PER_DAY . ' pokretanja. Pokušajte ponovo sutra.'], 429);
    }
}

// --- CPU time limit: the task's own limit, else 1.0s -----------------------
$cpu = 1.0;
if ($taskId) {
    $ts = $pdo->prepare('SELECT time_limit_ms FROM tasks WHERE id = ?');
    $ts->execute([$taskId]);
    $ms = (int) $ts->fetchColumn();
    if ($ms > 0) { $cpu = min(15.0, max(0.5, $ms / 1000)); }
}

// =====================================================================
//  JUDGE mode — run against the task's official in/out tests, count passes
// =====================================================================
if ($mode === 'judge') {
    if (!$taskId) {
        json_out(['error' => 'Nedostaje zadatak.'], 422);
    }
    $ts = $pdo->prepare('SELECT idx, input_path, output_path FROM task_tests WHERE task_id = ? ORDER BY idx');
    $ts->execute([$taskId]);
    $rows = $ts->fetchAll();
    if (!$rows) {
        json_out(['error' => 'Ovaj zadatak nema zvaničnih test primjera.'], 422);
    }

    $cppId = $langs['cpp']['id'];
    $total = count($rows);
    $cap   = judge_max_tests();
    $eval  = array_slice($rows, 0, $cap);
    $passed = 0; $details = []; $firstBad = null; $compile = '';

    foreach ($eval as $t) {
        $in  = gh_raw($t['input_path']);
        $exp = gh_raw($t['output_path']);
        if ($in === null || $exp === null) {
            $details[] = ['idx' => (int) $t['idx'], 'status' => 'Test nedostupan', 'ok' => false];
            continue;
        }
        $res = judge_run($source, $cppId, $in, $exp, $cpu);
        if (isset($res['error'])) { // judge/quota failure: stop, report partial
            json_out(['mode' => 'judge', 'error' => $res['error'],
                      'passed' => $passed, 'total' => $total, 'evaluated' => count($details)], 502);
        }
        $sid = (int) $res['status_id'];
        $ok  = ($sid === 3); // Accepted
        if ($ok) { $passed++; } elseif ($firstBad === null) { $firstBad = $res['status']; }
        $details[] = ['idx' => (int) $t['idx'], 'status' => $res['status'], 'ok' => $ok, 'time' => $res['time']];
        if ($sid === 6) { $compile = $res['compile']; break; } // compile error is the same for every test
    }

    $verdict = ($compile !== '') ? 'Greška pri kompajliranju'
             : (($passed === count($details) && $passed === $total) ? 'Sva prošla' : ($firstBad ?: 'Pogrešan odgovor'));

    try {
        $pdo->prepare('INSERT INTO submissions (task_id, language, source, status, ip) VALUES (?,?,?,?,?)')
            ->execute([$taskId, $langKey, $source, "judge: $passed/$total", $ip]);
    } catch (Throwable $e) {}

    json_out([
        'mode'      => 'judge',
        'passed'    => $passed,
        'total'     => $total,
        'evaluated' => count($details),
        'capped'    => $total > $cap,
        'verdict'   => $verdict,
        'compile'   => $compile,
        'details'   => $details,
    ]);
}

// =====================================================================
//  RUN mode — run once against custom stdin
// =====================================================================
$result = judge_run($source, $langs[$langKey]['id'], $stdin, null, $cpu);

if (isset($result['error'])) {
    json_out(['error' => $result['error']], 502);
}

// Log the run (best-effort; never block the response on a logging failure).
try {
    $pdo->prepare(
        'INSERT INTO submissions (task_id, language, source, status, time_ms, memory_kb, ip)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $taskId,
        $langKey,
        $source,
        $result['status'] ?? null,
        $result['time'] !== null ? (int) round(((float) $result['time']) * 1000) : null,
        $result['memory'] !== null ? (int) $result['memory'] : null,
        $ip,
    ]);
} catch (Throwable $e) {
    // ignore logging errors
}

json_out([
    'status'  => $result['status'],
    'stdout'  => $result['stdout'],
    'stderr'  => $result['stderr'],
    'compile' => $result['compile'],
    'time'    => $result['time'],
    'memory'  => $result['memory'],
]);
