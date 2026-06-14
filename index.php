<?php
/**
 * index.php — Task catalog with instant filtering.
 * ----------------------------------------------------------------------
 * All tasks are rendered server-side (one DB round-trip) with their
 * metadata embedded as data-* attributes. assets/app.js then filters the
 * rows instantly on the client by year, level, tag and free-text search,
 * with the filter state mirrored to the URL so views are shareable.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = db();

// --- Reference data for the filter controls ---------------------------
$levels = $pdo->query('SELECT id, name, slug FROM levels ORDER BY sort_order')->fetchAll();
$tags   = $pdo->query('SELECT id, name, slug FROM tags ORDER BY name')->fetchAll();
$years  = $pdo->query('SELECT DISTINCT year FROM tasks ORDER BY year DESC')->fetchAll(PDO::FETCH_COLUMN);

// --- Tasks (with level + aggregated tags + solution count) ------------
$sql = "
    SELECT
        t.id, t.title, t.slug, t.year, t.difficulty, t.problem_index,
        t.pdf_path, t.tests_path,
        l.name  AS level_name,
        l.slug  AS level_slug,
        GROUP_CONCAT(DISTINCT tg.name ORDER BY tg.name SEPARATOR '||') AS tag_names,
        GROUP_CONCAT(DISTINCT tg.slug ORDER BY tg.slug SEPARATOR '||') AS tag_slugs,
        (SELECT COUNT(*) FROM solutions s WHERE s.task_id = t.id)       AS solution_count
    FROM tasks t
    JOIN levels l       ON l.id = t.level_id
    LEFT JOIN task_tags tt ON tt.task_id = t.id
    LEFT JOIN tags tg      ON tg.id = tt.tag_id
    GROUP BY t.id
    ORDER BY t.year DESC, l.sort_order DESC, t.problem_index ASC, t.title ASC
";
$tasks = $pdo->query($sql)->fetchAll();

$page_title = 'Zadaci';
require __DIR__ . '/includes/header.php';
?>

<!-- ===== Hero ===== -->
<section class="mb-8">
    <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">
        Arhiva takmičarskih zadataka
    </h1>
    <p class="mt-2 max-w-2xl text-slate-600">
        Pretraži i filtriraj zadatke sa takmičenja iz informatike u BiH — po godini, nivou i algoritmu.
        Preuzmi tekst zadatka, službena rješenja i test primjere.
    </p>
</section>

<!-- ===== Filter bar ===== -->
<section class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-12">
        <!-- Search -->
        <div class="lg:col-span-5">
            <label for="f-search" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Pretraga</label>
            <div class="relative">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd"/></svg>
                <input id="f-search" type="search" placeholder="Naziv zadatka…"
                       class="w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-9 pr-3 text-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30">
            </div>
        </div>

        <!-- Year -->
        <div class="lg:col-span-2">
            <label for="f-year" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Godina</label>
            <select id="f-year" class="w-full rounded-xl border border-slate-300 bg-white py-2.5 px-3 text-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30">
                <option value="">Sve godine</option>
                <?php foreach ($years as $y): ?>
                    <option value="<?= e((string) $y) ?>"><?= e((string) $y) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Level -->
        <div class="lg:col-span-2">
            <label for="f-level" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Nivo</label>
            <select id="f-level" class="w-full rounded-xl border border-slate-300 bg-white py-2.5 px-3 text-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30">
                <option value="">Svi nivoi</option>
                <?php foreach ($levels as $lvl): ?>
                    <option value="<?= e($lvl['slug']) ?>"><?= e($lvl['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Tag -->
        <div class="lg:col-span-3">
            <label for="f-tag" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Algoritam / kategorija</label>
            <select id="f-tag" class="w-full rounded-xl border border-slate-300 bg-white py-2.5 px-3 text-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30">
                <option value="">Sve kategorije</option>
                <?php foreach ($tags as $tag): ?>
                    <option value="<?= e($tag['slug']) ?>"><?= e($tag['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="mt-4 flex items-center justify-between">
        <p class="text-sm text-slate-500">
            Prikazano <span id="result-count" class="font-semibold text-slate-700"><?= count($tasks) ?></span>
            od <?= count($tasks) ?> zadataka
        </p>
        <button id="clear-filters" class="rounded-lg px-3 py-1.5 text-sm font-medium text-indigo-600 transition hover:bg-indigo-50">
            Poništi filtere
        </button>
    </div>
</section>

<!-- ===== Task table ===== -->
<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">Zadatak</th>
                    <th class="px-5 py-3">Godina</th>
                    <th class="px-5 py-3">Nivo</th>
                    <th class="px-5 py-3">Težina</th>
                    <th class="px-5 py-3">Kategorije</th>
                    <th class="px-5 py-3 text-right">Materijali</th>
                </tr>
            </thead>
            <tbody id="task-rows" class="divide-y divide-slate-100">
                <?php foreach ($tasks as $t):
                    $tagNames = $t['tag_names'] ? explode('||', $t['tag_names']) : [];
                    $tagSlugs = $t['tag_slugs'] ? explode('||', $t['tag_slugs']) : [];
                    $searchBlob = mb_strtolower($t['title'] . ' ' . $t['level_name'] . ' ' . implode(' ', $tagNames), 'UTF-8');
                    $taskUrl = url('task.php?id=' . (int) $t['id']);
                ?>
                <tr class="task-row group transition hover:bg-slate-50"
                    data-year="<?= e((string) $t['year']) ?>"
                    data-level="<?= e($t['level_slug']) ?>"
                    data-tags="<?= e(implode(' ', $tagSlugs)) ?>"
                    data-search="<?= e($searchBlob) ?>">

                    <td class="px-5 py-4">
                        <a href="<?= e($taskUrl) ?>" class="font-semibold text-slate-900 transition group-hover:text-indigo-600">
                            <?php if ($t['problem_index'] !== null && $t['problem_index'] !== ''): ?>
                                <span class="mr-1 text-slate-400"><?= e($t['problem_index']) ?>.</span>
                            <?php endif; ?>
                            <?= e($t['title']) ?>
                        </a>
                    </td>

                    <td class="px-5 py-4 tabular-nums text-slate-600"><?= e((string) $t['year']) ?></td>

                    <td class="px-5 py-4">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?= level_badge($t['level_slug']) ?>">
                            <?= e($t['level_name']) ?>
                        </span>
                    </td>

                    <td class="px-5 py-4">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?= difficulty_badge($t['difficulty']) ?>">
                            <?= e($t['difficulty']) ?>
                        </span>
                    </td>

                    <td class="px-5 py-4">
                        <div class="flex flex-wrap gap-1.5">
                            <?php foreach ($tagNames as $i => $name): ?>
                                <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                                    <?= e($name) ?>
                                </span>
                            <?php endforeach; ?>
                            <?php if (!$tagNames): ?>
                                <span class="text-xs text-slate-300">—</span>
                            <?php endif; ?>
                        </div>
                    </td>

                    <td class="px-5 py-4">
                        <div class="flex items-center justify-end gap-2 text-slate-400">
                            <?php if ($t['pdf_path']): ?>
                                <span title="Tekst zadatka (PDF)" class="inline-flex items-center gap-1 rounded-md bg-red-50 px-1.5 py-0.5 text-xs font-medium text-red-600">PDF</span>
                            <?php endif; ?>
                            <?php if ((int) $t['solution_count'] > 0): ?>
                                <span title="Rješenja" class="inline-flex items-center gap-1 rounded-md bg-indigo-50 px-1.5 py-0.5 text-xs font-medium text-indigo-600"><?= (int) $t['solution_count'] ?> rj.</span>
                            <?php endif; ?>
                            <?php if ($t['tests_path']): ?>
                                <span title="Test primjeri (ZIP)" class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-1.5 py-0.5 text-xs font-medium text-emerald-600">ZIP</span>
                            <?php endif; ?>
                            <a href="<?= e($taskUrl) ?>" class="ml-1 rounded-lg px-2.5 py-1 text-sm font-medium text-indigo-600 transition hover:bg-indigo-50">Otvori →</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Empty state (shown by JS when filters match nothing, or if there are no tasks) -->
    <div id="empty-state" class="<?= $tasks ? 'hidden' : '' ?> px-5 py-16 text-center">
        <p class="text-base font-semibold text-slate-700">Nema zadataka koji odgovaraju filterima.</p>
        <p class="mt-1 text-sm text-slate-500">Pokušaj promijeniti pretragu ili poništi filtere.</p>
    </div>
</section>

<script src="<?= e(url('assets/app.js')) ?>" defer></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
