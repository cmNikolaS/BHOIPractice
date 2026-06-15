<?php
/**
 * classify_tasks.php  —  Curated per-task difficulty + algorithm categories.
 * ----------------------------------------------------------------------
 * One-off (re-runnable) classifier for the imported BHOI archive. For each
 * task it sets:
 *   - difficulty_rating : a custom 1–10 estimate (my own per-task judgement,
 *                         NOT the competition round), source of truth.
 *   - difficulty        : the Lako/Srednje/Teško band, derived from the rating.
 *   - algorithm tags    : DP / Greedy / Grafovi / Matematika / ... so the
 *                         catalog "Kategorija" filter actually works.
 *
 * It is idempotent: it migrates the difficulty_rating column if missing,
 * preserves the school-category tags (osnovna-skola/srednja-skola/kombinovano)
 * and replaces only the algorithm tags. Tasks are matched by slug.
 *
 * Run (PowerShell, against the preview DB on 3307):
 *     $env:DB_PORT="3307"; C:\xampp\php\php.exe classify_tasks.php
 * Run against the SQLite (Fly) DB:
 *     DB_DRIVER=sqlite DB_SQLITE_PATH=/data/app.sqlite php classify_tasks.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('Run this from the command line.');
}

require __DIR__ . '/config.php';

function out(string $msg): void { fwrite(STDOUT, $msg . PHP_EOL); }

/** 1–10 rating -> band label (mirrors difficulty_band() in bootstrap.php). */
function band_for_rating(int $rating): string {
    if ($rating <= 3) return 'Lako';
    if ($rating <= 6) return 'Srednje';
    return 'Teško';
}

/* ---------------------------------------------------------------------
 *  Algorithm-category tags (slug => display name). These form the closed
 *  set of "algorithm" tags; everything else (school categories) is left
 *  untouched on each task.
 * ------------------------------------------------------------------- */
$ALG_TAGS = [
    'dp'                 => 'Dinamičko programiranje',
    'greedy'             => 'Pohlepni algoritmi',
    'grafovi'            => 'Grafovi',
    'matematika'         => 'Matematika',
    'sortiranje'         => 'Sortiranje',
    'pretraga'           => 'Pretraga',
    'stringovi'          => 'Stringovi',
    'strukture-podataka' => 'Strukture podataka',
    'geometrija'         => 'Geometrija',
    'implementacija'     => 'Implementacija',
    'teorija-brojeva'    => 'Teorija brojeva',
    'rekurzija'          => 'Rekurzija',
];

/* ---------------------------------------------------------------------
 *  Curated classification: task slug => [rating 1–10, [algorithm tag slugs]].
 *  Ratings are my own per-task estimate based on the problem statement.
 * ------------------------------------------------------------------- */
$CLASSIFY = [
    // ---- 2022 ----
    'bond-jbhoi-2022'                          => [4, ['implementacija', 'pretraga']],
    'kusur-jbhoi-2022'                         => [5, ['dp']],
    'tabija-jbhoi-2022'                        => [6, ['matematika', 'teorija-brojeva']],
    'xo-jbhoi-2022'                            => [3, ['implementacija']],
    'bfs-print-bhoi2022-2022'                  => [6, ['grafovi']],
    'civilizacija-bhoi2022-2022'               => [8, ['grafovi', 'strukture-podataka']],
    'ms-kalkulator-bhoi2022-2022'              => [7, ['matematika']],
    'parovi-bhoi2022-2022'                     => [5, ['matematika', 'implementacija']],
    'mor-kvalifikacije-2022'                   => [4, ['greedy']],
    'os-kvalifikacije-2022'                    => [6, ['implementacija', 'strukture-podataka']],
    'sredina-kvalifikacije-2022'               => [7, ['strukture-podataka', 'matematika']],
    'trake-kvalifikacije-2022'                 => [6, ['grafovi', 'pretraga']],
    // ---- 2023 ----
    'asimetrija-jbhoi-2023'                    => [6, ['pretraga', 'greedy']],
    'okviri-jbhoi-2023'                        => [5, ['greedy', 'sortiranje']],
    'pustolovina-jbhoi-2023'                   => [6, ['grafovi']],
    'skocko-jbhoi-2023'                        => [6, ['matematika']],
    'ajoi-kvalifikacije-2023'                  => [5, ['sortiranje', 'implementacija']],
    'cudnost-permutacije-kvalifikacije-2023'   => [7, ['matematika']],
    'matrix-kvalifikacije-2023'                => [7, ['strukture-podataka', 'matematika']],
    'prof-putovanje-kvalifikacije-2023'        => [6, ['geometrija', 'matematika']],
    'alphabot-bhoi-2023'                       => [6, ['strukture-podataka', 'stringovi', 'pretraga']],
    'bonbon-bhoi-2023'                         => [6, ['matematika', 'pretraga']],
    'cestice-bhoi-2023'                        => [8, ['dp', 'stringovi']],
    'zid-bhoi-2023'                            => [7, ['stringovi']],
    'drvosjeca-kvalifikacije-2023'             => [7, ['grafovi', 'greedy']],
    'ms-pejnt-kvalifikacije-2023'              => [7, ['grafovi']],
    'numero-kvalifikacije-2023'                => [6, ['greedy', 'stringovi']],
    'tokio-kvalifikacije-2023'                 => [7, ['strukture-podataka']],
    // ---- 2024 ----
    'dioba-jbhoi-2024'                         => [5, ['grafovi']],
    'euro2024-jbhoi-2024'                      => [5, ['implementacija', 'sortiranje']],
    'spiderman-jbhoi-2024'                     => [5, ['greedy', 'strukture-podataka']],
    'susjedi-jbhoi-2024'                       => [2, ['implementacija']],
    'dina-bhgoi-2024'                          => [5, ['grafovi', 'matematika']],
    'dupli-lanci-bhgoi-2024'                   => [7, ['dp', 'matematika']],
    'kolekcija-filmova-bhgoi-2024'             => [4, ['sortiranje', 'pretraga']],
    'lego-bhgoi-2024'                          => [3, ['implementacija']],
    'aperiodicni-niz-bhoi-2024'                => [4, ['matematika', 'pretraga']],
    'djeljivost-bhoi-2024'                     => [4, ['matematika', 'teorija-brojeva']],
    'hurry-doo-bhoi-2024'                      => [8, ['strukture-podataka', 'greedy']],
    'potraga-za-blagom-bhoi-2024'              => [7, ['pretraga']],
    'suma-bhoi-2024'                           => [8, ['dp', 'matematika']],
    // ---- 2025 ----
    'letjelica-kvalifikacije-2025'             => [4, ['matematika']],
    'lunine-gumice-za-kosu-kvalifikacije-2025' => [6, ['grafovi']],
    'meteorologija-kvalifikacije-2025'         => [3, ['sortiranje']],
    'ping-pong-kvalifikacije-2025'             => [4, ['dp']],
    'radna-mjesta-kvalifikacije-2025'          => [8, ['matematika']],
    'semafor-kvalifikacije-2025'               => [2, ['matematika', 'implementacija']],
    'skrivena-poruka-kvalifikacije-2025'       => [2, ['stringovi', 'implementacija']],
    'hoteli-jbhoi-2025'                        => [5, ['pretraga', 'greedy']],
    'lijevo-ili-desno-jbhoi-2025'              => [1, ['implementacija', 'stringovi']],
    'penjac-jbhoi-2025'                        => [7, ['grafovi', 'dp']],
    'ronjenje-jbhoi-2025'                      => [4, ['implementacija', 'grafovi']],
    'amazon-jboi-kvalifikaciono-2025'          => [7, ['implementacija', 'geometrija']],
    'cizme-jboi-kvalifikaciono-2025'           => [6, ['greedy', 'implementacija']],
    'dzak-jboi-kvalifikaciono-2025'            => [6, ['matematika', 'strukture-podataka']],
    'elena-bhgoi-2025'                         => [8, ['dp', 'matematika']],
    'film-bhgoi-2025'                          => [2, ['implementacija']],
    'sushi-bhgoi-2025'                         => [5, ['strukture-podataka']],
    'troskok-bhgoi-2025'                       => [6, ['strukture-podataka']],
    'cudnovati-sat-bhoi-2025'                  => [7, ['matematika']],
    'kolegice-bhoi-2025'                       => [3, ['implementacija', 'sortiranje']],
    'lezaljke-bhoi-2025'                       => [3, ['geometrija', 'implementacija']],
    'pneumatske-cijevi-bhoi-2025'              => [8, ['grafovi']],
    'povlacenje-uzeta-bhoi-2025'               => [9, ['grafovi', 'dp']],
    'izbirljivi-gosti-boi-kvalifikaciono-2025' => [7, ['strukture-podataka', 'grafovi']],
    'lutke-boi-kvalifikaciono-2025'            => [8, ['strukture-podataka']],
    'oblakinator-boi-kvalifikaciono-2025'      => [7, ['pretraga', 'greedy']],
    // ---- 2026 ----
    'brojevi-online-kvalifikaciono-2026'       => [2, ['matematika']],
    'igraliste-online-kvalifikaciono-2026'     => [3, ['grafovi']],
    'labirint-online-kvalifikaciono-2026'      => [6, ['grafovi', 'pretraga']],
    'sp-online-kvalifikaciono-2026'            => [5, ['rekurzija', 'implementacija']],
    'timovi-online-kvalifikaciono-2026'        => [6, ['grafovi']],
    'trka-online-kvalifikaciono-2026'          => [3, ['geometrija', 'matematika']],
    'brisanje-jbhoi-2026'                      => [5, ['dp']],
    'ceste-jbhoi-2026'                         => [6, ['grafovi']],
    'nutella-jbhoi-2026'                       => [2, ['matematika']],
    'poruke-jbhoi-2026'                        => [3, ['stringovi']],
    'suncobrani-jbhoi-2026'                    => [7, ['strukture-podataka']],
    'algalord-bhgoi-2026'                      => [9, ['grafovi', 'strukture-podataka']],
    'bazen-bhgoi-2026'                         => [8, ['dp']],
    'pin-bhgoi-2026'                           => [5, ['implementacija', 'stringovi']],
    'tunguzija-bhgoi-2026'                     => [5, ['matematika', 'implementacija']],
    'bananko-bhoi-2026'                        => [6, ['teorija-brojeva', 'matematika']],
    'kisobrani-bhoi-2026'                      => [8, ['strukture-podataka', 'implementacija']],
    'krempita-bhoi-2026'                       => [6, ['sortiranje', 'implementacija']],
    'linijari-bhoi-2026'                       => [2, ['implementacija', 'matematika']],
    'blok-bhoi-2026'                           => [7, ['grafovi', 'pretraga']],
    'gume-bhoi-2026'                           => [7, ['pretraga', 'greedy']],
    'klikeri-bhoi-2026'                        => [4, ['greedy', 'implementacija']],
    'tri-kljuca-mudrosti-bhoi-2026'            => [10, ['grafovi']],
];

/* ------------------------------------------------------------------- main */

$pdo = db();

/* --- 1. Migrate: add difficulty_rating column if it doesn't exist ---- */
function tasks_has_column(PDO $pdo, string $col): bool {
    if (DB_DRIVER === 'sqlite') {
        foreach ($pdo->query('PRAGMA table_info(tasks)')->fetchAll() as $c) {
            if (($c['name'] ?? '') === $col) return true;
        }
        return false;
    }
    $s = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $s->execute(['tasks', $col]);
    return (int) $s->fetchColumn() > 0;
}

if (!tasks_has_column($pdo, 'difficulty_rating')) {
    out('Migrating: adding tasks.difficulty_rating ...');
    $ddl = DB_DRIVER === 'sqlite'
        ? 'ALTER TABLE tasks ADD COLUMN difficulty_rating INTEGER NOT NULL DEFAULT 5'
        : 'ALTER TABLE tasks ADD COLUMN difficulty_rating TINYINT UNSIGNED NOT NULL DEFAULT 5';
    $pdo->exec($ddl);
}

/* --- 2. Ensure the algorithm tags exist; collect their ids ---------- */
$algTagIds = []; // slug => id
foreach ($ALG_TAGS as $slug => $name) {
    $s = $pdo->prepare('SELECT id FROM tags WHERE slug = ?');
    $s->execute([$slug]);
    $id = (int) $s->fetchColumn();
    if ($id === 0) {
        $pdo->prepare('INSERT INTO tags (name, slug) VALUES (?, ?)')->execute([$name, $slug]);
        $id = (int) $pdo->lastInsertId();
    }
    $algTagIds[$slug] = $id;
}
$allAlgIds = array_values($algTagIds);

/* --- 3. Map task slug => id ----------------------------------------- */
$taskIdBySlug = [];
foreach ($pdo->query('SELECT id, slug FROM tasks')->fetchAll() as $row) {
    $taskIdBySlug[$row['slug']] = (int) $row['id'];
}

/* --- 4. Apply classification --------------------------------------- */
$updTask  = $pdo->prepare('UPDATE tasks SET difficulty_rating = ?, difficulty = ? WHERE id = ?');
$insTag   = $pdo->prepare('INSERT INTO task_tags (task_id, tag_id) VALUES (?, ?)');
// Delete only this task's algorithm tags (keeps the school-category tag).
$algPlaceholders = implode(',', array_fill(0, count($allAlgIds), '?'));
$delAlg   = $pdo->prepare("DELETE FROM task_tags WHERE task_id = ? AND tag_id IN ($algPlaceholders)");

$applied = 0;
$missing = [];

$pdo->beginTransaction();
try {
    foreach ($CLASSIFY as $slug => [$rating, $tagSlugs]) {
        if (!isset($taskIdBySlug[$slug])) { $missing[] = $slug; continue; }
        $taskId = $taskIdBySlug[$slug];
        $rating = max(1, min(10, (int) $rating));
        $updTask->execute([$rating, band_for_rating($rating), $taskId]);

        $delAlg->execute(array_merge([$taskId], $allAlgIds));
        foreach (array_unique($tagSlugs) as $ts) {
            if (!isset($algTagIds[$ts])) { out("  ! unknown tag slug '$ts' on $slug"); continue; }
            $insTag->execute([$taskId, $algTagIds[$ts]]);
        }
        $applied++;
    }
    $pdo->commit();
} catch (Throwable $ex) {
    $pdo->rollBack();
    exit('Greška: ' . $ex->getMessage() . PHP_EOL);
}

/* --- 5. Report ------------------------------------------------------ */
out('');
out('=== DONE ===');
out("Classified: $applied / " . count($CLASSIFY) . ' tasks.');
if ($missing) {
    out('Not found in DB (skipped): ' . implode(', ', $missing));
}
$untagged = (int) $pdo->query(
    "SELECT COUNT(*) FROM tasks t
     WHERE NOT EXISTS (
        SELECT 1 FROM task_tags tt JOIN tags g ON g.id = tt.tag_id
        WHERE tt.task_id = t.id AND g.slug IN ('" . implode("','", array_keys($ALG_TAGS)) . "')
     )"
)->fetchColumn();
out("Tasks still without an algorithm tag: $untagged");
