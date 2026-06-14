<?php
/**
 * admin_task_save.php — Create or update a task (handles uploads + tags).
 * ----------------------------------------------------------------------
 * Admin-only, CSRF-protected. Wraps the task row, tag sync and file
 * uploads in a transaction so a failed upload never leaves a half-written
 * task. New uploads replace old files (the old file is deleted from disk).
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

$pdo = db();

// --- Collect & validate scalar fields --------------------------------
$id            = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: null;
$title         = trim((string) ($_POST['title'] ?? ''));
$statement     = trim((string) ($_POST['statement'] ?? ''));
$year          = filter_input(INPUT_POST, 'year', FILTER_VALIDATE_INT);
$levelId       = filter_input(INPUT_POST, 'level_id', FILTER_VALIDATE_INT);
$difficulty    = (string) ($_POST['difficulty'] ?? 'Srednje');
$problemIndex  = trim((string) ($_POST['problem_index'] ?? ''));
$timeLimit     = filter_input(INPUT_POST, 'time_limit_ms', FILTER_VALIDATE_INT) ?: null;
$memoryLimit   = filter_input(INPUT_POST, 'memory_limit_mb', FILTER_VALIDATE_INT) ?: null;
$tagIds        = array_map('intval', (array) ($_POST['tags'] ?? []));

$errors = [];
if ($title === '')                                   $errors[] = 'Naziv je obavezan.';
if (!$year || $year < 1990 || $year > 2100)          $errors[] = 'Godina je neispravna.';
if (!$levelId)                                       $errors[] = 'Nivo je obavezan.';
if (!in_array($difficulty, ['Lako', 'Srednje', 'Teško'], true)) $difficulty = 'Srednje';

if ($errors) {
    foreach ($errors as $msg) {
        flash('error', $msg);
    }
    redirect($id ? 'admin_dashboard.php?action=edit&id=' . $id : 'admin_dashboard.php?action=new');
}

// Build a unique slug (title + year, with a numeric suffix if needed).
$baseSlug = slugify($title . '-' . $year) ?: ('zadatak-' . $year);
$slug = $baseSlug;
$check = $pdo->prepare('SELECT id FROM tasks WHERE slug = ? AND id <> ? LIMIT 1');
$i = 2;
while (true) {
    $check->execute([$slug, $id ?? 0]);
    if (!$check->fetch()) {
        break;
    }
    $slug = $baseSlug . '-' . $i++;
}

try {
    $pdo->beginTransaction();

    // --- Insert or update the task row -------------------------------
    if ($id) {
        $stmt = $pdo->prepare("
            UPDATE tasks SET
                title = ?, slug = ?, statement = ?, year = ?, level_id = ?,
                difficulty = ?, problem_index = ?, time_limit_ms = ?, memory_limit_mb = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([
            $title, $slug, $statement, $year, $levelId, $difficulty,
            $problemIndex !== '' ? $problemIndex : null,
            $timeLimit, $memoryLimit, $id,
        ]);
        $taskId = $id;
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO tasks
                (title, slug, statement, year, level_id, difficulty, problem_index, time_limit_ms, memory_limit_mb)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $title, $slug, $statement, $year, $levelId, $difficulty,
            $problemIndex !== '' ? $problemIndex : null,
            $timeLimit, $memoryLimit,
        ]);
        $taskId = (int) $pdo->lastInsertId();
    }

    // --- Sync tags (clear + re-insert) -------------------------------
    $pdo->prepare('DELETE FROM task_tags WHERE task_id = ?')->execute([$taskId]);
    if ($tagIds) {
        $ins = $pdo->prepare('INSERT INTO task_tags (task_id, tag_id) VALUES (?, ?)');
        foreach (array_unique($tagIds) as $tagId) {
            $ins->execute([$taskId, $tagId]);
        }
    }

    // --- Fetch current file paths (for replace/remove logic) ---------
    $cur = $pdo->prepare('SELECT pdf_path, tests_path FROM tasks WHERE id = ?');
    $cur->execute([$taskId]);
    $current = $cur->fetch();
    $oldFilesToDelete = [];

    // --- PDF: optional remove, optional replace ----------------------
    if (!empty($_POST['remove_pdf']) && $current['pdf_path']) {
        $oldFilesToDelete[] = $current['pdf_path'];
        $pdo->prepare('UPDATE tasks SET pdf_path = NULL WHERE id = ?')->execute([$taskId]);
    }
    if (isset($_FILES['pdf']) && ($_FILES['pdf']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $saved = save_upload($_FILES['pdf'], 'pdf');
        if ($current['pdf_path']) {
            $oldFilesToDelete[] = $current['pdf_path'];
        }
        $pdo->prepare('UPDATE tasks SET pdf_path = ? WHERE id = ?')->execute([$saved['path'], $taskId]);
    }

    // --- Test cases ZIP: optional remove, optional replace -----------
    if (!empty($_POST['remove_tests']) && $current['tests_path']) {
        $oldFilesToDelete[] = $current['tests_path'];
        $pdo->prepare('UPDATE tasks SET tests_path = NULL WHERE id = ?')->execute([$taskId]);
    }
    if (isset($_FILES['tests']) && ($_FILES['tests']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $saved = save_upload($_FILES['tests'], 'tests');
        if ($current['tests_path']) {
            $oldFilesToDelete[] = $current['tests_path'];
        }
        $pdo->prepare('UPDATE tasks SET tests_path = ? WHERE id = ?')->execute([$saved['path'], $taskId]);
    }

    // --- Solutions: append any newly uploaded files ------------------
    if (isset($_FILES['solutions']) && is_array($_FILES['solutions']['name'])) {
        $solInsert = $pdo->prepare("
            INSERT INTO solutions (task_id, language, original_name, file_path, file_size)
            VALUES (?, ?, ?, ?, ?)
        ");
        $count = count($_FILES['solutions']['name']);
        for ($k = 0; $k < $count; $k++) {
            if (($_FILES['solutions']['error'][$k] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            // Re-shape the k-th file into a flat array for save_upload().
            $one = [
                'name'     => $_FILES['solutions']['name'][$k],
                'type'     => $_FILES['solutions']['type'][$k],
                'tmp_name' => $_FILES['solutions']['tmp_name'][$k],
                'error'    => $_FILES['solutions']['error'][$k],
                'size'     => $_FILES['solutions']['size'][$k],
            ];
            $saved = save_upload($one, 'solution');
            $language = language_from_ext(pathinfo($saved['original_name'], PATHINFO_EXTENSION));
            $solInsert->execute([$taskId, $language, $saved['original_name'], $saved['path'], $saved['size']]);
        }
    }

    $pdo->commit();

    // Delete superseded files only after the DB transaction succeeded.
    foreach ($oldFilesToDelete as $old) {
        delete_upload($old);
    }

    flash('success', $id ? 'Zadatak je ažuriran.' : 'Zadatak je dodan.');
    redirect('admin_dashboard.php?action=edit&id=' . $taskId);

} catch (Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', 'Greška pri spremanju: ' . $ex->getMessage());
    redirect($id ? 'admin_dashboard.php?action=edit&id=' . $id : 'admin_dashboard.php?action=new');
}

/** Map a source-file extension to a friendly language label. */
function language_from_ext(string $ext): string
{
    return match (strtolower($ext)) {
        'cpp', 'cc', 'cxx', 'c', 'h', 'hpp' => 'C++',
        'py'                                => 'Python',
        'java'                              => 'Java',
        'pas'                               => 'Pascal',
        'kt'                                => 'Kotlin',
        'js'                                => 'JavaScript',
        'rs'                                => 'Rust',
        'go'                                => 'Go',
        default                             => 'Izvorni kod',
    };
}
