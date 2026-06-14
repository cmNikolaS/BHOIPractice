<?php
/**
 * index.php — Task catalog with instant filtering + per-browser progress.
 * ----------------------------------------------------------------------
 * All tasks are rendered server-side with their metadata as data-*
 * attributes. assets/app.js filters instantly (search/difficulty/level/
 * tag/year/status) and tracks "completed" tasks in localStorage, so the
 * Status column and the progress bar work without any login.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = db();

// --- Reference data for the filter controls ---------------------------
$levels = $pdo->query('SELECT id, name, slug FROM levels ORDER BY sort_order DESC')->fetchAll();
$tags   = $pdo->query('SELECT id, name, slug FROM tags ORDER BY name')->fetchAll();
$years  = $pdo->query('SELECT DISTINCT year FROM tasks ORDER BY year DESC')->fetchAll(PDO::FETCH_COLUMN);

// --- Tasks (with level + solution count) ------------------------------
$sql = "
    SELECT
        t.id, t.title, t.slug, t.year, t.difficulty, t.problem_index,
        t.pdf_path, t.tests_path,
        l.name  AS level_name,
        l.slug  AS level_slug,
        (SELECT COUNT(*) FROM solutions s WHERE s.task_id = t.id) AS solution_count
    FROM tasks t
    JOIN levels l ON l.id = t.level_id
    ORDER BY t.year DESC, l.sort_order DESC, t.problem_index ASC, t.title ASC
";
$tasks = $pdo->query($sql)->fetchAll();

// Tags grouped per task (separate query keeps the SQL portable).
$tagsByTask = [];
$tagRows = $pdo->query("
    SELECT tt.task_id, tg.name, tg.slug
    FROM task_tags tt
    JOIN tags tg ON tg.id = tt.tag_id
    ORDER BY tg.name
")->fetchAll();
foreach ($tagRows as $tr) {
    $tagsByTask[$tr['task_id']][] = ['name' => $tr['name'], 'slug' => $tr['slug']];
}

$total = count($tasks);
$page_title = 'Zadaci';
require __DIR__ . '/includes/header.php';
?>

<style>
    .status-toggle { width: 1.55rem; height: 1.55rem; border-radius: 9999px; border: 1.5px solid var(--line);
        display: grid; place-items: center; color: transparent; transition: all .15s; }
    .status-toggle:hover { border-color: var(--muted); }
    .status-toggle.done { background: #2ea043; border-color: #2ea043; color: #fff; }
    tr.row-done .row-title { color: var(--muted); }
    .filter-field { } /* spacing handled by grid */
    select.filter, input.filter { color-scheme: dark; }
</style>

<!-- ===== Header + progress ===== -->
<section class="mb-6 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h1 class="text-3xl font-extrabold tracking-tight text-fg sm:text-4xl">Problemi</h1>
        <p class="mt-2 max-w-2xl text-muted">
            Zadaci sa takmičenja iz informatike u BiH. Filtriraj, riješi i prati svoj napredak.
        </p>
    </div>

    <!-- Progress (filled client-side) -->
    <div class="w-full sm:w-72">
        <div class="mb-1.5 flex items-baseline justify-between text-sm">
            <span class="font-medium text-muted">Napredak</span>
            <span class="font-semibold text-fg"><span id="solved-count">0</span> / <?= $total ?> riješeno</span>
        </div>
        <div class="h-2.5 w-full overflow-hidden rounded-full bg-elevated">
            <div id="progress-bar" class="h-full rounded-full bg-done transition-all duration-500" style="width:0%"></div>
        </div>
    </div>
</section>

<!-- ===== Filter bar ===== -->
<section class="mb-5 rounded-2xl border border-line bg-card p-4 shadow-sm sm:p-5">
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-12">
        <div class="col-span-2 lg:col-span-4">
            <label for="f-search" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted">Pretraga <span class="font-normal normal-case opacity-60">(/)</span></label>
            <div class="relative">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd"/></svg>
                <input id="f-search" type="search" placeholder="Naziv zadatka…"
                       class="filter w-full rounded-xl border border-line bg-elevated py-2.5 pl-9 pr-3 text-sm text-fg outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/30">
            </div>
        </div>

        <div class="lg:col-span-2">
            <label for="f-status" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted">Status</label>
            <select id="f-status" class="filter w-full rounded-xl border border-line bg-elevated py-2.5 px-3 text-sm text-fg outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/30">
                <option value="">Svi</option>
                <option value="solved">Riješeni</option>
                <option value="unsolved">Neriješeni</option>
            </select>
        </div>

        <div class="lg:col-span-2">
            <label for="f-difficulty" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted">Težina</label>
            <select id="f-difficulty" class="filter w-full rounded-xl border border-line bg-elevated py-2.5 px-3 text-sm text-fg outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/30">
                <option value="">Sve</option>
                <option value="Lako">Lako</option>
                <option value="Srednje">Srednje</option>
                <option value="Teško">Teško</option>
            </select>
        </div>

        <div class="lg:col-span-2">
            <label for="f-level" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted">Nivo</label>
            <select id="f-level" class="filter w-full rounded-xl border border-line bg-elevated py-2.5 px-3 text-sm text-fg outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/30">
                <option value="">Svi nivoi</option>
                <?php foreach ($levels as $lvl): ?>
                    <option value="<?= e($lvl['slug']) ?>"><?= e($lvl['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="lg:col-span-2">
            <label for="f-year" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted">Godina</label>
            <select id="f-year" class="filter w-full rounded-xl border border-line bg-elevated py-2.5 px-3 text-sm text-fg outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/30">
                <option value="">Sve</option>
                <?php foreach ($years as $y): ?>
                    <option value="<?= e((string) $y) ?>"><?= e((string) $y) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-span-2 lg:col-span-12">
            <label for="f-tag" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted">Kategorija</label>
            <select id="f-tag" class="filter w-full rounded-xl border border-line bg-elevated py-2.5 px-3 text-sm text-fg outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/30 lg:w-72">
                <option value="">Sve kategorije</option>
                <?php foreach ($tags as $tag): ?>
                    <option value="<?= e($tag['slug']) ?>"><?= e($tag['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-muted">
            Prikazano <span id="result-count" class="font-semibold text-fg"><?= $total ?></span> od <?= $total ?>
        </p>
        <div class="flex items-center gap-2">
            <button id="pick-random" class="inline-flex items-center gap-1.5 rounded-lg bg-accent/15 px-3 py-1.5 text-sm font-semibold text-accent transition hover:bg-accent/25">
                🎲 Nasumičan
            </button>
            <button id="clear-filters" class="rounded-lg px-3 py-1.5 text-sm font-medium text-muted transition hover:bg-elevated hover:text-fg">
                Poništi filtere
            </button>
        </div>
    </div>
</section>

<!-- ===== Task table ===== -->
<section class="overflow-hidden rounded-2xl border border-line bg-card shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-line text-sm">
            <thead class="bg-elevated text-left text-xs font-semibold uppercase tracking-wide text-muted">
                <tr>
                    <th class="px-5 py-3 w-12">Status</th>
                    <th class="px-5 py-3">Zadatak</th>
                    <th class="px-5 py-3">Težina</th>
                    <th class="px-5 py-3">Nivo</th>
                    <th class="px-5 py-3">Godina</th>
                    <th class="px-5 py-3">Kategorije</th>
                    <th class="px-5 py-3 text-right">Materijali</th>
                </tr>
            </thead>
            <tbody id="task-rows" class="divide-y divide-line">
                <?php foreach ($tasks as $t):
                    $taskTags = $tagsByTask[$t['id']] ?? [];
                    $tagNames = array_column($taskTags, 'name');
                    $tagSlugs = array_column($taskTags, 'slug');
                    $searchBlob = mb_strtolower($t['title'] . ' ' . $t['level_name'] . ' ' . implode(' ', $tagNames), 'UTF-8');
                    $taskUrl = url('task.php?id=' . (int) $t['id']);
                ?>
                <tr class="task-row group transition hover:bg-elevated/60"
                    data-id="<?= (int) $t['id'] ?>"
                    data-year="<?= e((string) $t['year']) ?>"
                    data-level="<?= e($t['level_slug']) ?>"
                    data-difficulty="<?= e($t['difficulty']) ?>"
                    data-tags="<?= e(implode(' ', $tagSlugs)) ?>"
                    data-search="<?= e($searchBlob) ?>"
                    data-url="<?= e($taskUrl) ?>">

                    <td class="px-5 py-4">
                        <button type="button" class="status-toggle" data-id="<?= (int) $t['id'] ?>" title="Označi kao riješeno" aria-label="Označi kao riješeno">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4 10l4 4 8-9"/></svg>
                        </button>
                    </td>

                    <td class="px-5 py-4">
                        <a href="<?= e($taskUrl) ?>" class="row-title font-semibold text-fg transition group-hover:text-accent">
                            <?php if ($t['problem_index'] !== null && $t['problem_index'] !== ''): ?>
                                <span class="mr-1 text-muted"><?= e($t['problem_index']) ?>.</span>
                            <?php endif; ?>
                            <?= e($t['title']) ?>
                        </a>
                    </td>

                    <td class="px-5 py-4">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset <?= difficulty_badge($t['difficulty']) ?>">
                            <?= e($t['difficulty']) ?>
                        </span>
                    </td>

                    <td class="px-5 py-4">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?= level_badge($t['level_slug']) ?>">
                            <?= e($t['level_name']) ?>
                        </span>
                    </td>

                    <td class="px-5 py-4 tabular-nums text-muted"><?= e((string) $t['year']) ?></td>

                    <td class="px-5 py-4">
                        <div class="flex flex-wrap gap-1.5">
                            <?php foreach ($tagNames as $name): ?>
                                <span class="inline-flex items-center rounded-md bg-elevated px-2 py-0.5 text-xs font-medium text-muted"><?= e($name) ?></span>
                            <?php endforeach; ?>
                            <?php if (!$tagNames): ?><span class="text-xs text-muted opacity-50">—</span><?php endif; ?>
                        </div>
                    </td>

                    <td class="px-5 py-4">
                        <div class="flex items-center justify-end gap-2">
                            <?php if ($t['pdf_path']): ?>
                                <span title="Tekst zadatka (PDF)" class="rounded-md bg-hard/15 px-1.5 py-0.5 text-xs font-medium text-hard">PDF</span>
                            <?php endif; ?>
                            <?php if ((int) $t['solution_count'] > 0): ?>
                                <span title="Rješenja" class="rounded-md bg-accent/15 px-1.5 py-0.5 text-xs font-medium text-accent"><?= (int) $t['solution_count'] ?> rj.</span>
                            <?php endif; ?>
                            <a href="<?= e($taskUrl) ?>" class="ml-1 rounded-lg px-2.5 py-1 text-sm font-medium text-accent transition hover:bg-accent/15">Otvori →</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div id="empty-state" class="<?= $tasks ? 'hidden' : '' ?> px-5 py-16 text-center">
        <p class="text-base font-semibold text-fg">Nema zadataka koji odgovaraju filterima.</p>
        <p class="mt-1 text-sm text-muted">Pokušaj promijeniti pretragu ili poništi filtere.</p>
    </div>
</section>

<script src="<?= e(url('assets/app.js')) ?>" defer></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
