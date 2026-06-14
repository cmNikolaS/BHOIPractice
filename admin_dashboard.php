<?php
/**
 * admin_dashboard.php — Back-office hub.
 * ----------------------------------------------------------------------
 * Three views, selected by ?action=
 *   (default) list  — table of all tasks with edit/delete actions
 *   new             — blank "Add task" form
 *   edit&id=<id>    — pre-filled "Edit task" form + file management
 *
 * The form posts to admin_task_save.php; deletes post to admin_task_delete.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

require_admin();

$pdo    = db();
$action = $_GET['action'] ?? 'list';

// Reference data shared by the forms.
$levels = $pdo->query('SELECT id, name, slug FROM levels ORDER BY sort_order')->fetchAll();
$tags   = $pdo->query('SELECT id, name FROM tags ORDER BY name')->fetchAll();

$page_title = 'Admin panel';
require __DIR__ . '/includes/admin_header.php';

/* =====================================================================
 *  EDIT / NEW FORM
 * ================================================================== */
if ($action === 'new' || $action === 'edit'):

    $task = [
        'id' => null, 'title' => '', 'statement' => '', 'year' => (int) date('Y'),
        'level_id' => $levels[0]['id'] ?? null, 'difficulty' => 'Srednje',
        'problem_index' => '', 'time_limit_ms' => '', 'memory_limit_mb' => '',
        'pdf_path' => null, 'tests_path' => null,
    ];
    $selectedTags = [];
    $solutions = [];

    if ($action === 'edit') {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $stmt = $pdo->prepare('SELECT * FROM tasks WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $loaded = $stmt->fetch();
        if (!$loaded) {
            echo '<p class="text-slate-600">Zadatak nije pronađen.</p>';
            require __DIR__ . '/includes/admin_footer.php';
            exit;
        }
        $task = $loaded;

        $ts = $pdo->prepare('SELECT tag_id FROM task_tags WHERE task_id = ?');
        $ts->execute([$id]);
        $selectedTags = array_map('intval', $ts->fetchAll(PDO::FETCH_COLUMN));

        $ss = $pdo->prepare('SELECT id, language, original_name, file_size FROM solutions WHERE task_id = ? ORDER BY language');
        $ss->execute([$id]);
        $solutions = $ss->fetchAll();
    }

    $isEdit = $action === 'edit';
?>

    <div class="mb-6 flex items-center justify-between">
        <div>
            <a href="<?= e(url('admin_dashboard.php')) ?>" class="text-sm text-slate-500 transition hover:text-indigo-600">← Svi zadaci</a>
            <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-slate-900">
                <?= $isEdit ? 'Uredi zadatak' : 'Novi zadatak' ?>
            </h1>
        </div>
    </div>

    <form method="post" action="<?= e(url('admin_task_save.php')) ?>" enctype="multipart/form-data"
          class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        <?= csrf_field() ?>
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
        <?php endif; ?>

        <!-- ===== Left: core fields ===== -->
        <div class="space-y-6 lg:col-span-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-xs font-semibold uppercase tracking-wide text-slate-400">Osnovni podaci</h2>

                <label class="mb-1 block text-sm font-medium text-slate-700" for="title">Naziv zadatka *</label>
                <input id="title" name="title" type="text" required value="<?= e($task['title']) ?>"
                       class="mb-4 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30">

                <label class="mb-1 block text-sm font-medium text-slate-700" for="statement">Tekst / opis zadatka</label>
                <textarea id="statement" name="statement" rows="12"
                          class="mb-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm leading-6 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30"><?= e($task['statement']) ?></textarea>
                <p class="text-xs text-slate-400">Običan tekst; prelomi redova se zadržavaju pri prikazu.</p>
            </div>

            <!-- ===== Files ===== -->
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-xs font-semibold uppercase tracking-wide text-slate-400">Datoteke</h2>

                <!-- PDF -->
                <label class="mb-1 block text-sm font-medium text-slate-700" for="pdf">Tekst zadatka (PDF)</label>
                <?php if ($isEdit && $task['pdf_path']): ?>
                    <div class="mb-2 flex items-center gap-2 text-sm">
                        <span class="rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Postoji PDF</span>
                        <label class="flex items-center gap-1 text-slate-500">
                            <input type="checkbox" name="remove_pdf" value="1" class="rounded border-slate-300"> Ukloni postojeći
                        </label>
                    </div>
                <?php endif; ?>
                <input id="pdf" name="pdf" type="file" accept="application/pdf"
                       class="mb-4 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">

                <!-- Test cases -->
                <label class="mb-1 block text-sm font-medium text-slate-700" for="tests">Test primjeri (ZIP)</label>
                <?php if ($isEdit && $task['tests_path']): ?>
                    <div class="mb-2 flex items-center gap-2 text-sm">
                        <span class="rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Postoji ZIP</span>
                        <label class="flex items-center gap-1 text-slate-500">
                            <input type="checkbox" name="remove_tests" value="1" class="rounded border-slate-300"> Ukloni postojeći
                        </label>
                    </div>
                <?php endif; ?>
                <input id="tests" name="tests" type="file" accept=".zip,application/zip"
                       class="mb-4 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">

                <!-- Solutions (multiple) -->
                <label class="mb-1 block text-sm font-medium text-slate-700" for="solutions">Rješenja (više datoteka)</label>
                <input id="solutions" name="solutions[]" type="file" multiple
                       accept=".cpp,.cc,.cxx,.c,.h,.hpp,.py,.java,.pas,.txt,.kt,.js,.rs,.go"
                       class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
                <p class="mt-1 text-xs text-slate-400">Možeš odabrati više datoteka (C++, Python, Pascal…). Maks. <?= e(human_size(MAX_UPLOAD_BYTES)) ?> po datoteci.</p>

                <!-- Existing solutions (edit mode) -->
                <?php if ($isEdit && $solutions): ?>
                    <div class="mt-4 border-t border-slate-100 pt-4">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Postojeća rješenja</p>
                        <ul class="space-y-2">
                            <?php foreach ($solutions as $sol): ?>
                                <li class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2 text-sm">
                                    <span class="min-w-0 flex-1 truncate">
                                        <span class="font-medium text-slate-800"><?= e($sol['original_name']) ?></span>
                                        <span class="text-slate-400"><?= e($sol['language'] ? ' · ' . $sol['language'] : '') ?><?= $sol['file_size'] ? ' · ' . e(human_size((int) $sol['file_size'])) : '' ?></span>
                                    </span>
                                    <form method="post" action="<?= e(url('admin_task_delete.php')) ?>"
                                          onsubmit="return confirm('Obrisati ovo rješenje?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="type" value="solution">
                                        <input type="hidden" name="id" value="<?= (int) $sol['id'] ?>">
                                        <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">
                                        <button type="submit" class="rounded-md px-2 py-1 text-xs font-medium text-red-600 transition hover:bg-red-50">Obriši</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== Right: metadata + tags ===== -->
        <div class="space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-xs font-semibold uppercase tracking-wide text-slate-400">Klasifikacija</h2>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700" for="year">Godina *</label>
                        <input id="year" name="year" type="number" min="1990" max="2100" required value="<?= e((string) $task['year']) ?>"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700" for="problem_index">Oznaka</label>
                        <input id="problem_index" name="problem_index" type="text" maxlength="10" placeholder="npr. 1 ili A" value="<?= e((string) $task['problem_index']) ?>"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30">
                    </div>
                </div>

                <label class="mb-1 mt-3 block text-sm font-medium text-slate-700" for="level_id">Nivo takmičenja *</label>
                <select id="level_id" name="level_id" required
                        class="mb-3 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30">
                    <?php foreach ($levels as $lvl): ?>
                        <option value="<?= (int) $lvl['id'] ?>" <?= (int) $task['level_id'] === (int) $lvl['id'] ? 'selected' : '' ?>>
                            <?= e($lvl['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label class="mb-1 block text-sm font-medium text-slate-700" for="difficulty">Težina</label>
                <select id="difficulty" name="difficulty"
                        class="mb-3 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30">
                    <?php foreach (['Lako', 'Srednje', 'Teško'] as $d): ?>
                        <option value="<?= e($d) ?>" <?= $task['difficulty'] === $d ? 'selected' : '' ?>><?= e($d) ?></option>
                    <?php endforeach; ?>
                </select>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700" for="time_limit_ms">Vrijeme (ms)</label>
                        <input id="time_limit_ms" name="time_limit_ms" type="number" min="0" placeholder="npr. 1000" value="<?= e((string) $task['time_limit_ms']) ?>"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700" for="memory_limit_mb">Memorija (MB)</label>
                        <input id="memory_limit_mb" name="memory_limit_mb" type="number" min="0" placeholder="npr. 256" value="<?= e((string) $task['memory_limit_mb']) ?>"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30">
                    </div>
                </div>
            </div>

            <!-- Tags -->
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-xs font-semibold uppercase tracking-wide text-slate-400">Algoritmi / kategorije</h2>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <?php foreach ($tags as $tag): ?>
                        <label class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm transition hover:bg-slate-50">
                            <input type="checkbox" name="tags[]" value="<?= (int) $tag['id'] ?>"
                                   <?= in_array((int) $tag['id'], $selectedTags, true) ? 'checked' : '' ?>
                                   class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-slate-700"><?= e($tag['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-3">
                <button type="submit"
                        class="flex-1 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                    <?= $isEdit ? 'Sačuvaj izmjene' : 'Dodaj zadatak' ?>
                </button>
                <a href="<?= e(url('admin_dashboard.php')) ?>"
                   class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    Odustani
                </a>
            </div>
        </div>
    </form>

<?php
/* =====================================================================
 *  LIST VIEW (default)
 * ================================================================== */
else:
    $sql = "
        SELECT t.id, t.title, t.year, t.difficulty, t.problem_index, t.pdf_path, t.tests_path,
               l.name AS level_name, l.slug AS level_slug,
               (SELECT COUNT(*) FROM solutions s WHERE s.task_id = t.id) AS solution_count
        FROM tasks t
        JOIN levels l ON l.id = t.level_id
        ORDER BY t.year DESC, t.title ASC
    ";
    $rows = $pdo->query($sql)->fetchAll();
?>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">Upravljanje zadacima</h1>
            <p class="text-sm text-slate-500"><?= count($rows) ?> zadataka u arhivi</p>
        </div>
        <a href="<?= e(url('admin_dashboard.php?action=new')) ?>"
           class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">
            <span class="text-base leading-none">+</span> Novi zadatak
        </a>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Zadatak</th>
                        <th class="px-5 py-3">Godina</th>
                        <th class="px-5 py-3">Nivo</th>
                        <th class="px-5 py-3">Težina</th>
                        <th class="px-5 py-3">Datoteke</th>
                        <th class="px-5 py-3 text-right">Akcije</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($rows as $r): ?>
                    <tr class="transition hover:bg-slate-50">
                        <td class="px-5 py-3.5">
                            <a href="<?= e(url('task.php?id=' . (int) $r['id'])) ?>" target="_blank" class="font-semibold text-slate-900 hover:text-indigo-600">
                                <?php if ($r['problem_index']): ?><span class="text-slate-400"><?= e($r['problem_index']) ?>. </span><?php endif; ?>
                                <?= e($r['title']) ?>
                            </a>
                        </td>
                        <td class="px-5 py-3.5 tabular-nums text-slate-600"><?= e((string) $r['year']) ?></td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?= level_badge($r['level_slug']) ?>"><?= e($r['level_name']) ?></span>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?= difficulty_badge($r['difficulty']) ?>"><?= e($r['difficulty']) ?></span>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-1 text-xs">
                                <?php if ($r['pdf_path']): ?><span class="rounded bg-red-50 px-1.5 py-0.5 font-medium text-red-600">PDF</span><?php endif; ?>
                                <?php if ((int) $r['solution_count'] > 0): ?><span class="rounded bg-indigo-50 px-1.5 py-0.5 font-medium text-indigo-600"><?= (int) $r['solution_count'] ?> rj.</span><?php endif; ?>
                                <?php if ($r['tests_path']): ?><span class="rounded bg-emerald-50 px-1.5 py-0.5 font-medium text-emerald-600">ZIP</span><?php endif; ?>
                                <?php if (!$r['pdf_path'] && !$r['tests_path'] && !$r['solution_count']): ?><span class="text-slate-300">—</span><?php endif; ?>
                            </div>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center justify-end gap-1">
                                <a href="<?= e(url('admin_dashboard.php?action=edit&id=' . (int) $r['id'])) ?>"
                                   class="rounded-lg px-2.5 py-1 text-sm font-medium text-indigo-600 transition hover:bg-indigo-50">Uredi</a>
                                <form method="post" action="<?= e(url('admin_task_delete.php')) ?>"
                                      onsubmit="return confirm('Obrisati zadatak &quot;<?= e(addslashes($r['title'])) ?>&quot; i sve njegove datoteke?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="type" value="task">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <button type="submit" class="rounded-lg px-2.5 py-1 text-sm font-medium text-red-600 transition hover:bg-red-50">Obriši</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if (!$rows): ?>
                    <tr>
                        <td colspan="6" class="px-5 py-16 text-center">
                            <p class="text-base font-semibold text-slate-700">Još nema zadataka.</p>
                            <p class="mt-1 text-sm text-slate-500">Klikni „Novi zadatak" da dodaš prvi.</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
