<?php
/**
 * import_tests.php — Map each task to its official in/out test cases.
 * ----------------------------------------------------------------------
 * Stores only the GitHub repo PATHS of each task's input/output files in
 * `task_tests` (the actual data, ~1GB, is fetched on demand at judge time —
 * see submit.php?mode=judge + JUDGE.md). Covers the modern task.yaml layout
 * (<dir>/input/inputK.txt + <dir>/output/outputK.txt). Idempotent: rebuilds
 * the table each run. Tasks without tests simply get no rows (the task page
 * then shows "no official test cases").
 *
 *   $env:DB_PORT="3307"; C:\xampp\php\php.exe import_tests.php
 *   DB_DRIVER=sqlite DB_SQLITE_PATH=/data/app.sqlite php import_tests.php
 */

declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit('Run this from the command line.'); }
require __DIR__ . '/config.php';

const TREE_JSON = __DIR__ . '/_import/tree.json';

function out(string $m): void { fwrite(STDOUT, $m . PHP_EOL); }
function slugify(string $text): string {
    $map = ['č'=>'c','ć'=>'c','đ'=>'d','š'=>'s','ž'=>'z','Č'=>'c','Ć'=>'c','Đ'=>'d','Š'=>'s','Ž'=>'z'];
    $text = mb_strtolower(strtr($text, $map), 'UTF-8');
    return trim(preg_replace('/[^a-z0-9]+/u', '-', $text) ?? '', '-');
}
function round_slug(string $round): string {
    if (str_starts_with($round, 'bhoi')) return 'drzavno-bhoi';
    if ($round === 'jbhoi') return 'jbhoi';
    if ($round === 'bhgoi') return 'bhgoi';
    return 'kvalifikacije';
}

$tree = json_decode((string) file_get_contents(TREE_JSON), true);
$paths = [];
foreach ($tree['tree'] as $n) { if (($n['type'] ?? '') === 'blob') $paths[] = $n['path']; }

// Group input/ and output/ files by their task directory.
$inByDir = []; $outByDir = [];
foreach ($paths as $p) {
    if (preg_match('#^(.*)/input/[^/]*?(\d+)\.txt$#', $p, $m))  { $inByDir[$m[1]][(int) $m[2]] = $p; }
    elseif (preg_match('#^(.*)/output/[^/]*?(\d+)\.txt$#', $p, $m)) { $outByDir[$m[1]][(int) $m[2]] = $p; }
}

// task dir -> slug (same derivation as import_bhoi.php)
function dir_to_slug(string $dir): string {
    $seg = explode('/', $dir);
    $year = (int) $seg[0];
    $round = $seg[2] ?? '';
    $name = $seg[count($seg) - 1];
    return slugify($name . '-' . $round . '-' . $year) ?: ('zadatak-' . $year);
}

$pdo = db();
$idBySlug = [];
foreach ($pdo->query('SELECT id, slug FROM tasks')->fetchAll() as $r) { $idBySlug[$r['slug']] = (int) $r['id']; }

$pdo->beginTransaction();
$pdo->exec('DELETE FROM task_tests');
$ins = $pdo->prepare('INSERT INTO task_tests (task_id, idx, input_path, output_path) VALUES (?,?,?,?)');

$tasksWithTests = 0; $pairs = 0; $unmatched = [];
foreach ($inByDir as $dir => $ins_files) {
    $outs = $outByDir[$dir] ?? [];
    if (!$outs) continue;
    $slug = dir_to_slug($dir);
    if (!isset($idBySlug[$slug])) { $unmatched[$dir] = $slug; continue; }
    $taskId = $idBySlug[$slug];
    ksort($ins_files);
    $n = 0;
    foreach ($ins_files as $k => $inPath) {
        if (!isset($outs[$k])) continue;
        $ins->execute([$taskId, $k, $inPath, $outs[$k]]);
        $pairs++; $n++;
    }
    if ($n > 0) $tasksWithTests++;
}
$pdo->commit();

out('=== DONE ===');
out("Tasks with official tests: $tasksWithTests | test pairs: $pairs");
if ($unmatched) { out('Unmatched test dirs (no task with that slug): ' . count($unmatched)); }
$noTests = (int) $pdo->query('SELECT COUNT(*) FROM tasks t WHERE NOT EXISTS (SELECT 1 FROM task_tests x WHERE x.task_id = t.id)')->fetchColumn();
out("Tasks WITHOUT any test cases: $noTests");
