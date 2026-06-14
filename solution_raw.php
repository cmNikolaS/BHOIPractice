<?php
/**
 * solution_raw.php — Return a solution's source as plain text (UTF-8).
 * ----------------------------------------------------------------------
 * Used by the in-page code-preview modal on task.php. Same safety model
 * as download.php: the file is looked up via the DB and confirmed to live
 * inside /uploads, then streamed inline as text/plain (never executed,
 * never served as an attachment).
 *
 *   solution_raw.php?id=<solutionId>
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/uploads.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit('Neispravan zahtjev.');
}

$stmt = db()->prepare('SELECT file_path FROM solutions WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    exit('Rješenje nije pronađeno.');
}

$abs = upload_abspath($row['file_path']);
if ($abs === null || !is_file($abs)) {
    http_response_code(404);
    exit('Datoteka nije pronađena.');
}

// Cap what we read into the preview (1 MB is plenty for source files).
$maxBytes = 1024 * 1024;
$code = (string) file_get_contents($abs, false, null, 0, $maxBytes);

header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');

while (ob_get_level() > 0) { ob_end_clean(); }
echo $code;
