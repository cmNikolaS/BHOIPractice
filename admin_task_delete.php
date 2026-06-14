<?php
/**
 * admin_task_delete.php — Delete a task or a single solution file.
 * ----------------------------------------------------------------------
 * Admin-only, CSRF-protected, POST only. Removes the DB row(s) and the
 * associated files on disk. Deleting a task cascades to its tags and
 * solutions via the schema's foreign keys; we additionally unlink the
 * physical files first.
 *
 *   POST type=task     id=<taskId>
 *   POST type=solution id=<solutionId> task_id=<taskId>
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/uploads.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin_dashboard.php');
}
require_csrf();

$pdo  = db();
$type = $_POST['type'] ?? '';
$id   = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    flash('error', 'Neispravan zahtjev.');
    redirect('admin_dashboard.php');
}

if ($type === 'task') {
    // Gather every file belonging to the task so we can unlink them.
    $t = $pdo->prepare('SELECT pdf_path, tests_path FROM tasks WHERE id = ?');
    $t->execute([$id]);
    $task = $t->fetch();

    if (!$task) {
        flash('error', 'Zadatak nije pronađen.');
        redirect('admin_dashboard.php');
    }

    $s = $pdo->prepare('SELECT file_path FROM solutions WHERE task_id = ?');
    $s->execute([$id]);
    $solutionPaths = $s->fetchAll(PDO::FETCH_COLUMN);

    // Delete the row (FK cascade removes task_tags + solutions rows).
    $pdo->prepare('DELETE FROM tasks WHERE id = ?')->execute([$id]);

    // Unlink files from disk.
    delete_upload($task['pdf_path']);
    delete_upload($task['tests_path']);
    foreach ($solutionPaths as $p) {
        delete_upload($p);
    }

    flash('success', 'Zadatak je obrisan.');
    redirect('admin_dashboard.php');

} elseif ($type === 'solution') {
    $taskId = filter_input(INPUT_POST, 'task_id', FILTER_VALIDATE_INT);

    $s = $pdo->prepare('SELECT file_path FROM solutions WHERE id = ?');
    $s->execute([$id]);
    $sol = $s->fetch();

    if ($sol) {
        $pdo->prepare('DELETE FROM solutions WHERE id = ?')->execute([$id]);
        delete_upload($sol['file_path']);
        flash('success', 'Rješenje je obrisano.');
    } else {
        flash('error', 'Rješenje nije pronađeno.');
    }

    redirect($taskId ? 'admin_dashboard.php?action=edit&id=' . $taskId : 'admin_dashboard.php');

} else {
    flash('error', 'Nepoznata akcija brisanja.');
    redirect('admin_dashboard.php');
}
