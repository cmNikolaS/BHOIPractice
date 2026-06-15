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
require_once __DIR__ . '/includes/judge.php';

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

$result = judge_run($source, $langs[$langKey]['id'], $stdin);

if (isset($result['error'])) {
    json_out(['error' => $result['error']], 502);
}

// Log the run (best-effort; never block the response on a logging failure).
try {
    $pdo = db();
    $pdo->prepare(
        'INSERT INTO submissions (task_id, language, source, status, time_ms, memory_kb)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([
        $taskId,
        $langKey,
        $source,
        $result['status'] ?? null,
        $result['time'] !== null ? (int) round(((float) $result['time']) * 1000) : null,
        $result['memory'] !== null ? (int) $result['memory'] : null,
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
