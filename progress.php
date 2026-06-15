<?php
/**
 * progress.php — per-user solved-task progress (JSON).
 *   GET  -> { solved: [taskId, ...] } for the logged-in user
 *   POST -> save { solved: "[...]" } (CSRF-protected)
 * Guests get an empty set; the client then falls back to localStorage.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_user()) {
    echo json_encode(['solved' => []]);
    exit;
}
$uid = (int) current_user()['id'];
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $raw = (string) ($_POST['solved'] ?? '[]');
    $arr = json_decode($raw, true);
    if (!is_array($arr)) { $arr = []; }
    // sanitise: ints, unique, cap
    $ids = [];
    foreach ($arr as $v) {
        if (is_int($v) || (is_string($v) && ctype_digit($v))) { $ids[(int) $v] = true; }
        if (count($ids) >= 5000) break;
    }
    $json = json_encode(array_map('intval', array_keys($ids)));
    $pdo->prepare('DELETE FROM user_progress WHERE user_id = ?')->execute([$uid]);
    $pdo->prepare('INSERT INTO user_progress (user_id, data) VALUES (?, ?)')->execute([$uid, $json]);
    echo json_encode(['ok' => true, 'count' => count($ids)]);
    exit;
}

// GET
$stmt = $pdo->prepare('SELECT data FROM user_progress WHERE user_id = ?');
$stmt->execute([$uid]);
$data = $stmt->fetchColumn();
$solved = $data ? json_decode((string) $data, true) : [];
echo json_encode(['solved' => is_array($solved) ? $solved : []]);
