<?php
/**
 * download.php — Secure file delivery.
 * ----------------------------------------------------------------------
 * Files are never linked to directly. Every download is resolved through
 * the database (so the on-disk names stay random and unguessable) and the
 * resolved path is confirmed to live inside /uploads before streaming.
 *
 *   download.php?type=pdf&task=<id>
 *   download.php?type=tests&task=<id>
 *   download.php?type=solution&id=<solutionId>
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/uploads.php';

$type = $_GET['type'] ?? '';
$pdo  = db();

$relPath = null;   // path stored in DB, relative to /uploads
$downloadName = 'download';

if ($type === 'pdf' || $type === 'tests') {
    $taskId = filter_input(INPUT_GET, 'task', FILTER_VALIDATE_INT);
    if (!$taskId) {
        http_response_code(400);
        exit('Neispravan zahtjev.');
    }
    $column = $type === 'pdf' ? 'pdf_path' : 'tests_path';
    $stmt = $pdo->prepare("SELECT title, slug, `$column` AS path FROM tasks WHERE id = ? LIMIT 1");
    $stmt->execute([$taskId]);
    $row = $stmt->fetch();

    if ($row && $row['path']) {
        $relPath = $row['path'];
        $ext = pathinfo($relPath, PATHINFO_EXTENSION);
        $base = $row['slug'] ?: ('zadatak-' . $taskId);
        $downloadName = $base . ($type === 'tests' ? '-testovi' : '') . '.' . $ext;
    }

} elseif ($type === 'solution') {
    $solId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$solId) {
        http_response_code(400);
        exit('Neispravan zahtjev.');
    }
    $stmt = $pdo->prepare('SELECT original_name, file_path FROM solutions WHERE id = ? LIMIT 1');
    $stmt->execute([$solId]);
    $row = $stmt->fetch();

    if ($row) {
        $relPath = $row['file_path'];
        // Keep the admin-provided filename, but strip any path components.
        $downloadName = basename($row['original_name']);
    }

} else {
    http_response_code(400);
    exit('Nepoznat tip preuzimanja.');
}

if ($relPath === null) {
    http_response_code(404);
    exit('Datoteka nije pronađena.');
}

// Resolve to a real path and guarantee it is inside /uploads.
$abs = upload_abspath($relPath);
if ($abs === null || !is_file($abs)) {
    http_response_code(404);
    exit('Datoteka nije pronađena na disku.');
}

// Stream it. PDFs may be viewed inline (?inline=1, e.g. "open in browser");
// everything else is always an attachment.
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($abs) ?: 'application/octet-stream';

$inline = ($type === 'pdf' && ($_GET['inline'] ?? '') === '1');
$disposition = $inline ? 'inline' : 'attachment';

header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $downloadName) . '"');
header('Content-Length: ' . filesize($abs));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');

// Clear any output buffering so the binary is not corrupted.
while (ob_get_level() > 0) {
    ob_end_clean();
}
readfile($abs);
exit;
