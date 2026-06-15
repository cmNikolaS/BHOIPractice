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
$levels = $pdo->query('SELECT id, name, slug FROM levels ORDER BY sort_order DESC')->fetchAll();
$tags   = $pdo->query('SELECT id, name FROM tags ORDER BY name')->fetchAll();

$page_title = 'Admin panel';
require __DIR__ . '/includes/admin_header.php';

// Shared input classes (kept DRY).
$inputCls = 'w-full rounded-xl border border-line bg-card px-3 py-2.5 text-sm text-fg outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/30';

/* =====================================================================
 *  EDIT / NEW FORM
 * ================================================================== */
if ($action === 'new' || $action === 'edit'):

    $task = [
        'id' => null, 'title' => '', 'statement' => '', 'year' => (int) date('Y'),
        'level_id' => $levels[0]['id'] ?? null, 'difficulty' => 'Srednje', 'difficulty_rating' => 5,
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
            echo '<p class="text-muted">Zadatak nije pronađen.</p>';
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
            <a href="<?= e(url('admin_dashboard.php')) ?>" class="text-sm text-muted transition hover:text-accent">← Svi zadaci</a>
            <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-fg">
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
            <div class="rounded-2xl border border-line bg-card p-6 shadow-sm">
                <h2 class="mb-4 text-xs font-semibold uppercase tracking-wide text-muted">Osnovni podaci</h2>

                <label class="mb-1 block text-sm font-medium text-fg" for="title">Naziv zadatka *</label>
                <input id="title" name="title" type="text" required value="<?= e($task['title']) ?>"
                       class="mb-4 <?= $inputCls ?>">

                <label class="mb-1 block text-sm font-medium text-fg" for="statement">Tekst / opis zadatka (Markdown)</label>
                <textarea id="statement" name="statement" rows="12"
                          class="mb-1 <?= $inputCls ?> leading-6"><?= e($task['statement']) ?></textarea>
                <p class="text-xs text-muted">Podržan je Markdown; prikazuje se formatirano na stranici zadatka.</p>
            </div>

            <!-- ===== Files ===== -->
            <div class="rounded-2xl border border-line bg-card p-6 shadow-sm">
                <h2 class="mb-4 text-xs font-semibold uppercase tracking-wide text-muted">Datoteke</h2>

                <?php
                $fileCls = 'block w-full text-sm text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-accent/15 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-accent hover:file:bg-accent/25';
                ?>

                <!-- PDF -->
                <label class="mb-1 block text-sm font-medium text-fg" for="pdf">Tekst zadatka (PDF)</label>
                <?php if ($isEdit && $task['pdf_path']): ?>
                    <div class="mb-2 flex items-center gap-2 text-sm">
                        <span class="rounded-md bg-easy/15 px-2 py-0.5 text-xs font-medium text-easy">Postoji PDF</span>
                        <label class="flex items-center gap-1 text-muted">
                            <input type="checkbox" name="remove_pdf" value="1" class="rounded border-line"> Ukloni postojeći
                        </label>
                    </div>
                <?php endif; ?>
                <input id="pdf" name="pdf" type="file" accept="application/pdf" class="mb-4 <?= $fileCls ?>">

                <!-- Test cases -->
                <label class="mb-1 block text-sm font-medium text-fg" for="tests">Test primjeri (ZIP)</label>
                <?php if ($isEdit && $task['tests_path']): ?>
                    <div class="mb-2 flex items-center gap-2 text-sm">
                        <span class="rounded-md bg-easy/15 px-2 py-0.5 text-xs font-medium text-easy">Postoji ZIP</span>
                        <label class="flex items-center gap-1 text-muted">
                            <input type="checkbox" name="remove_tests" value="1" class="rounded border-line"> Ukloni postojeći
                        </label>
                    </div>
                <?php endif; ?>
                <input id="tests" name="tests" type="file" accept=".zip,application/zip" class="mb-4 <?= $fileCls ?>">

                <!-- Solutions (multiple) -->
                <label class="mb-1 block text-sm font-medium text-fg" for="solutions">Rješenja (više datoteka)</label>
                <input id="solutions" name="solutions[]" type="file" multiple
                       accept=".cpp,.cc,.cxx,.c,.h,.hpp,.py,.java,.pas,.txt,.kt,.js,.rs,.go" class="<?= $fileCls ?>">
                <p class="mt-1 text-xs text-muted">Možeš odabrati više datoteka (C++, Python, Pascal…). Maks. <?= e(human_size(MAX_UPLOAD_BYTES)) ?> po datoteci.</p>

                <!-- Existing solutions (edit mode) -->
                <?php if ($isEdit && $solutions): ?>
                    <div class="mt-4 border-t border-line pt-4">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">Postojeća rješenja</p>
                        <ul class="space-y-2">
                            <?php foreach ($solutions as $sol): ?>
                                <li class="flex items-center justify-between gap-3 rounded-lg bg-elevated px-3 py-2 text-sm">
                                    <span class="min-w-0 flex-1 truncate">
                                        <span class="font-medium text-fg"><?= e($sol['original_name']) ?></span>
                                        <span class="text-muted"><?= e($sol['language'] ? ' · ' . $sol['language'] : '') ?><?= $sol['file_size'] ? ' · ' . e(human_size((int) $sol['file_size'])) : '' ?></span>
                                    </span>
                                    <!-- The delete form lives OUTSIDE the main edit form (below); we
                                         associate this button with it via the form= attribute. Nesting
                                         a <form> here would prematurely close the main edit form. -->
                                    <button type="submit" form="del-sol-<?= (int) $sol['id'] ?>"
                                            class="rounded-md px-2 py-1 text-xs font-medium text-hard transition hover:bg-hard/15">Obriši</button>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== Right: metadata + tags ===== -->
        <div class="space-y-6">
            <div class="rounded-2xl border border-line bg-card p-6 shadow-sm">
                <h2 class="mb-4 text-xs font-semibold uppercase tracking-wide text-muted">Klasifikacija</h2>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-fg" for="year">Godina *</label>
                        <input id="year" name="year" type="number" min="1990" max="2100" required value="<?= e((string) $task['year']) ?>" class="<?= $inputCls ?>">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-fg" for="problem_index">Oznaka</label>
                        <input id="problem_index" name="problem_index" type="text" maxlength="10" placeholder="npr. 1 ili A" value="<?= e((string) $task['problem_index']) ?>" class="<?= $inputCls ?>">
                    </div>
                </div>

                <label class="mb-1 mt-3 block text-sm font-medium text-fg" for="level_id">Nivo takmičenja *</label>
                <select id="level_id" name="level_id" required class="mb-3 <?= $inputCls ?>">
                    <?php foreach ($levels as $lvl): ?>
                        <option value="<?= (int) $lvl['id'] ?>" <?= (int) $task['level_id'] === (int) $lvl['id'] ? 'selected' : '' ?>>
                            <?= e($lvl['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label class="mb-1 block text-sm font-medium text-fg" for="difficulty_rating">Težina (1–10)</label>
                <?php $curRating = (int) ($task['difficulty_rating'] ?? 5); ?>
                <input id="difficulty_rating" name="difficulty_rating" type="number" min="1" max="10" step="1" required
                       value="<?= e((string) ($curRating ?: 5)) ?>" class="mb-1 <?= $inputCls ?>">
                <p class="mb-3 text-xs text-muted">
                    Vlastita procjena težine. Bedž se izvodi automatski:
                    <span class="font-medium text-easy">1–3 Lako</span> ·
                    <span class="font-medium text-medium">4–6 Srednje</span> ·
                    <span class="font-medium text-hard">7–10 Teško</span>
                </p>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-fg" for="time_limit_ms">Vrijeme (ms)</label>
                        <input id="time_limit_ms" name="time_limit_ms" type="number" min="0" placeholder="npr. 1000" value="<?= e((string) $task['time_limit_ms']) ?>" class="<?= $inputCls ?>">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-fg" for="memory_limit_mb">Memorija (MB)</label>
                        <input id="memory_limit_mb" name="memory_limit_mb" type="number" min="0" placeholder="npr. 256" value="<?= e((string) $task['memory_limit_mb']) ?>" class="<?= $inputCls ?>">
                    </div>
                </div>
            </div>

            <!-- Tags -->
            <div class="rounded-2xl border border-line bg-card p-6 shadow-sm">
                <h2 class="mb-4 text-xs font-semibold uppercase tracking-wide text-muted">Algoritmi / kategorije</h2>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <?php foreach ($tags as $tag): ?>
                        <label class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm transition hover:bg-elevated">
                            <input type="checkbox" name="tags[]" value="<?= (int) $tag['id'] ?>"
                                   <?= in_array((int) $tag['id'], $selectedTags, true) ? 'checked' : '' ?>
                                   class="rounded border-line text-accent focus:ring-accent">
                            <span class="text-fg"><?= e($tag['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-3">
                <button type="submit"
                        class="flex-1 rounded-xl bg-accent px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-110">
                    <?= $isEdit ? 'Sačuvaj izmjene' : 'Dodaj zadatak' ?>
                </button>
                <a href="<?= e(url('admin_dashboard.php')) ?>"
                   class="rounded-xl border border-line px-4 py-2.5 text-sm font-medium text-fg transition hover:bg-elevated">
                    Odustani
                </a>
            </div>
        </div>
    </form>

    <?php /* Per-solution delete forms, kept outside the main edit form (see note above). */ ?>
    <?php if ($isEdit && $solutions): ?>
        <?php foreach ($solutions as $sol): ?>
            <form id="del-sol-<?= (int) $sol['id'] ?>" method="post" action="<?= e(url('admin_task_delete.php')) ?>"
                  class="hidden" onsubmit="return confirm('Obrisati ovo rješenje?');">
                <?= csrf_field() ?>
                <input type="hidden" name="type" value="solution">
                <input type="hidden" name="id" value="<?= (int) $sol['id'] ?>">
                <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">
            </form>
        <?php endforeach; ?>
    <?php endif; ?>

<?php
/* =====================================================================
 *  LIST VIEW (default)
 * ================================================================== */
else:
    $sql = "
        SELECT t.id, t.title, t.year, t.difficulty, t.difficulty_rating, t.problem_index, t.pdf_path, t.tests_path,
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
            <h1 class="text-2xl font-extrabold tracking-tight text-fg">Upravljanje zadacima</h1>
            <p class="text-sm text-muted"><?= count($rows) ?> zadataka u arhivi</p>
        </div>
        <a href="<?= e(url('admin_dashboard.php?action=new')) ?>"
           class="inline-flex items-center gap-1.5 rounded-xl bg-accent px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-110">
            <span class="text-base leading-none">+</span> Novi zadatak
        </a>
    </div>

    <div class="overflow-hidden rounded-2xl border border-line bg-card shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-line text-sm">
                <thead class="bg-elevated text-left text-xs font-semibold uppercase tracking-wide text-muted">
                    <tr>
                        <th class="px-5 py-3">Zadatak</th>
                        <th class="px-5 py-3">Godina</th>
                        <th class="px-5 py-3">Nivo</th>
                        <th class="px-5 py-3">Težina</th>
                        <th class="px-5 py-3">Datoteke</th>
                        <th class="px-5 py-3 text-right">Akcije</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    <?php foreach ($rows as $r): ?>
                    <tr class="transition hover:bg-elevated/60">
                        <td class="px-5 py-3.5">
                            <a href="<?= e(url('task.php?id=' . (int) $r['id'])) ?>" target="_blank" class="font-semibold text-fg hover:text-accent">
                                <?php if ($r['problem_index']): ?><span class="text-muted"><?= e($r['problem_index']) ?>. </span><?php endif; ?>
                                <?= e($r['title']) ?>
                            </a>
                        </td>
                        <td class="px-5 py-3.5 tabular-nums text-muted"><?= e((string) $r['year']) ?></td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?= level_badge($r['level_slug']) ?>"><?= e($r['level_name']) ?></span>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset <?= difficulty_badge($r['difficulty']) ?>"><?= e($r['difficulty']) ?> · <?= (int) $r['difficulty_rating'] ?></span>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-1 text-xs">
                                <?php if ($r['pdf_path']): ?><span class="rounded bg-hard/15 px-1.5 py-0.5 font-medium text-hard">PDF</span><?php endif; ?>
                                <?php if ((int) $r['solution_count'] > 0): ?><span class="rounded bg-accent/15 px-1.5 py-0.5 font-medium text-accent"><?= (int) $r['solution_count'] ?> rj.</span><?php endif; ?>
                                <?php if ($r['tests_path']): ?><span class="rounded bg-easy/15 px-1.5 py-0.5 font-medium text-easy">ZIP</span><?php endif; ?>
                                <?php if (!$r['pdf_path'] && !$r['tests_path'] && !$r['solution_count']): ?><span class="text-muted opacity-50">—</span><?php endif; ?>
                            </div>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center justify-end gap-1">
                                <a href="<?= e(url('admin_dashboard.php?action=edit&id=' . (int) $r['id'])) ?>"
                                   class="rounded-lg px-2.5 py-1 text-sm font-medium text-accent transition hover:bg-accent/15">Uredi</a>
                                <form method="post" action="<?= e(url('admin_task_delete.php')) ?>"
                                      onsubmit="return confirm('Obrisati zadatak &quot;<?= e(addslashes($r['title'])) ?>&quot; i sve njegove datoteke?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="type" value="task">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <button type="submit" class="rounded-lg px-2.5 py-1 text-sm font-medium text-hard transition hover:bg-hard/15">Obriši</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if (!$rows): ?>
                    <tr>
                        <td colspan="6" class="px-5 py-16 text-center">
                            <p class="text-base font-semibold text-fg">Još nema zadataka.</p>
                            <p class="mt-1 text-sm text-muted">Klikni „Novi zadatak" da dodaš prvi.</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
