<?php
/**
 * task.php — Single task view with downloadable materials.
 * ----------------------------------------------------------------------
 * Renders the problem statement, metadata badges, the list of tags, and
 * download buttons for: the task PDF, each official solution, and the
 * test-case ZIP. Buttons appear only when the corresponding file exists.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(404);
    $page_title = 'Zadatak nije pronađen';
    require __DIR__ . '/includes/header.php';
    echo '<p class="text-slate-600">Zadatak nije pronađen.</p>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$pdo = db();

// --- Task + level ----------------------------------------------------
$stmt = $pdo->prepare("
    SELECT t.*, l.name AS level_name, l.slug AS level_slug
    FROM tasks t
    JOIN levels l ON l.id = t.level_id
    WHERE t.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$task = $stmt->fetch();

if (!$task) {
    http_response_code(404);
    $page_title = 'Zadatak nije pronađen';
    require __DIR__ . '/includes/header.php';
    echo '<p class="text-slate-600">Zadatak nije pronađen.</p>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// --- Tags ------------------------------------------------------------
$tagStmt = $pdo->prepare("
    SELECT tg.name, tg.slug
    FROM task_tags tt
    JOIN tags tg ON tg.id = tt.tag_id
    WHERE tt.task_id = ?
    ORDER BY tg.name
");
$tagStmt->execute([$id]);
$tags = $tagStmt->fetchAll();

// --- Solutions -------------------------------------------------------
$solStmt = $pdo->prepare("
    SELECT id, language, original_name, file_size, author
    FROM solutions
    WHERE task_id = ?
    ORDER BY language, original_name
");
$solStmt->execute([$id]);
$solutions = $solStmt->fetchAll();

$page_title = $task['title'];
require __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumb -->
<nav class="mb-6 text-sm text-slate-500">
    <a href="<?= e(url('index.php')) ?>" class="transition hover:text-indigo-600">Zadaci</a>
    <span class="mx-1.5 text-slate-300">/</span>
    <span class="text-slate-700"><?= e($task['title']) ?></span>
</nav>

<div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
    <!-- ===== Main column ===== -->
    <article class="lg:col-span-2">
        <header class="mb-5">
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?= level_badge($task['level_slug']) ?>">
                    <?= e($task['level_name']) ?>
                </span>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?= difficulty_badge($task['difficulty']) ?>">
                    <?= e($task['difficulty']) ?>
                </span>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 tabular-nums">
                    <?= e((string) $task['year']) ?>
                </span>
            </div>
            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">
                <?php if ($task['problem_index'] !== null && $task['problem_index'] !== ''): ?>
                    <span class="text-slate-400"><?= e($task['problem_index']) ?>.</span>
                <?php endif; ?>
                <?= e($task['title']) ?>
            </h1>
        </header>

        <!-- Problem statement: readable typography -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <h2 class="mb-4 text-xs font-semibold uppercase tracking-wide text-slate-400">Tekst zadatka</h2>
            <?php if (trim((string) $task['statement']) !== ''): ?>
                <!-- Raw Markdown, rendered + sanitised client-side into #statement-body -->
                <textarea id="statement-md" hidden><?= e($task['statement']) ?></textarea>
                <div id="statement-body" class="prose-task max-w-none text-[15px] leading-7 text-slate-700"></div>
            <?php else: ?>
                <p class="text-slate-500">
                    Tekst nije unesen. Preuzmite PDF s desne strane za potpun opis zadatka.
                </p>
            <?php endif; ?>
        </div>
    </article>

    <!-- ===== Sidebar ===== -->
    <aside class="space-y-6">
        <!-- Materials / downloads -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-xs font-semibold uppercase tracking-wide text-slate-400">Materijali</h2>

            <!-- PDF: open inline in a new tab, with a separate download link -->
            <?php if ($task['pdf_path']): ?>
                <div class="mb-2 flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 transition hover:border-indigo-300 hover:bg-indigo-50">
                    <span class="grid h-9 w-9 place-items-center rounded-lg bg-red-100 text-red-600 font-bold text-xs">PDF</span>
                    <a href="<?= e(url('download.php?type=pdf&inline=1&task=' . (int) $task['id'])) ?>" target="_blank" rel="noopener"
                       class="flex-1 min-w-0">
                        <span class="block text-sm font-semibold text-slate-800">Tekst zadatka</span>
                        <span class="block text-xs text-slate-500">Otvori PDF u novom prozoru</span>
                    </a>
                    <a href="<?= e(url('download.php?type=pdf&task=' . (int) $task['id'])) ?>"
                       title="Preuzmi PDF"
                       class="rounded-lg px-2 py-1 text-xs font-semibold text-indigo-600 transition hover:bg-indigo-100">↓ Preuzmi</a>
                </div>
            <?php endif; ?>

            <!-- Test cases -->
            <?php if ($task['tests_path']): ?>
                <a href="<?= e(url('download.php?type=tests&task=' . (int) $task['id'])) ?>"
                   class="mb-2 flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 transition hover:border-emerald-300 hover:bg-emerald-50">
                    <span class="grid h-9 w-9 place-items-center rounded-lg bg-emerald-100 text-emerald-700 font-bold text-xs">ZIP</span>
                    <span class="flex-1">
                        <span class="block text-sm font-semibold text-slate-800">Test primjeri</span>
                        <span class="block text-xs text-slate-500">Preuzmi ZIP arhivu</span>
                    </span>
                    <span class="text-slate-400">↓</span>
                </a>
            <?php endif; ?>

            <!-- Solutions: click to preview the code in a modal, or download -->
            <?php if ($solutions): ?>
                <p class="mb-2 mt-4 text-xs font-semibold uppercase tracking-wide text-slate-400">Službena rješenja</p>
                <?php foreach ($solutions as $sol): ?>
                    <div class="mb-2 flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 transition hover:border-indigo-300 hover:bg-indigo-50">
                        <button type="button"
                                class="sol-preview flex flex-1 min-w-0 items-center gap-3 text-left"
                                data-id="<?= (int) $sol['id'] ?>"
                                data-name="<?= e($sol['original_name']) ?>"
                                data-lang="<?= e($sol['language'] ?: '') ?>">
                            <span class="grid h-9 w-9 place-items-center rounded-lg bg-indigo-100 text-indigo-700 font-bold text-[10px]">
                                <?= e($sol['language'] ? mb_strtoupper(mb_substr($sol['language'], 0, 3)) : 'SRC') ?>
                            </span>
                            <span class="flex-1 min-w-0">
                                <span class="block truncate text-sm font-semibold text-slate-800 group-hover:text-indigo-600"><?= e($sol['original_name']) ?></span>
                                <span class="block text-xs text-slate-500">
                                    <?= e($sol['language'] ?: 'Izvorni kod') ?><?php if ($sol['file_size']): ?> · <?= e(human_size((int) $sol['file_size'])) ?><?php endif; ?>
                                    · <span class="text-indigo-500">Pregledaj</span>
                                </span>
                            </span>
                        </button>
                        <a href="<?= e(url('download.php?type=solution&id=' . (int) $sol['id'])) ?>"
                           title="Preuzmi rješenje"
                           class="rounded-lg px-2 py-1 text-slate-400 transition hover:bg-indigo-100 hover:text-indigo-600">↓</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!$task['pdf_path'] && !$task['tests_path'] && !$solutions): ?>
                <p class="text-sm text-slate-500">Materijali za ovaj zadatak još nisu dodani.</p>
            <?php endif; ?>
        </div>

        <!-- Details -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-xs font-semibold uppercase tracking-wide text-slate-400">Detalji</h2>
            <dl class="space-y-2.5 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Godina</dt>
                    <dd class="font-medium text-slate-800 tabular-nums"><?= e((string) $task['year']) ?></dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Nivo</dt>
                    <dd class="font-medium text-slate-800"><?= e($task['level_name']) ?></dd>
                </div>
                <?php if ($task['time_limit_ms']): ?>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Vremensko ograničenje</dt>
                    <dd class="font-medium text-slate-800 tabular-nums"><?= e((string) ($task['time_limit_ms'] / 1000)) ?> s</dd>
                </div>
                <?php endif; ?>
                <?php if ($task['memory_limit_mb']): ?>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Memorija</dt>
                    <dd class="font-medium text-slate-800 tabular-nums"><?= e((string) $task['memory_limit_mb']) ?> MB</dd>
                </div>
                <?php endif; ?>
            </dl>

            <?php if ($tags): ?>
                <div class="mt-4 border-t border-slate-100 pt-4">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Kategorije</p>
                    <div class="flex flex-wrap gap-1.5">
                        <?php foreach ($tags as $tg): ?>
                            <a href="<?= e(url('index.php?tag=' . urlencode($tg['slug']))) ?>"
                               class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 transition hover:bg-indigo-100 hover:text-indigo-700">
                                <?= e($tg['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </aside>
</div>

<!-- ===== Solution preview modal ===== -->
<div id="sol-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" data-close></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="flex max-h-[85vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-900/10">
            <header class="flex items-center gap-3 border-b border-slate-200 px-5 py-3">
                <span id="sol-modal-lang" class="rounded-md bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-700">CODE</span>
                <span id="sol-modal-name" class="min-w-0 flex-1 truncate text-sm font-semibold text-slate-800"></span>
                <button type="button" id="sol-copy" class="rounded-lg px-2.5 py-1 text-xs font-medium text-slate-600 transition hover:bg-slate-100">Kopiraj</button>
                <a id="sol-download" href="#" class="rounded-lg px-2.5 py-1 text-xs font-medium text-indigo-600 transition hover:bg-indigo-50">↓ Preuzmi</a>
                <button type="button" class="rounded-lg px-2 py-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" data-close aria-label="Zatvori">✕</button>
            </header>
            <div class="overflow-auto bg-[#0d1117]">
                <pre class="m-0 p-0"><code id="sol-code" class="hljs text-[13px] leading-6"></code></pre>
            </div>
        </div>
    </div>
</div>

<!-- Markdown + syntax highlighting (CDN) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
<script src="https://cdn.jsdelivr.net/npm/marked@12.0.0/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3.0.9/dist/purify.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>

<style>
    /* Rendered Markdown statement */
    .prose-task h1 { font-size: 1.4rem; font-weight: 800; margin: 1.2em 0 .5em; color: #0f172a; }
    .prose-task h2 { font-size: 1.15rem; font-weight: 700; margin: 1.2em 0 .4em; color: #1e293b; }
    .prose-task h3 { font-size: 1rem; font-weight: 700; margin: 1em 0 .3em; color: #334155; }
    .prose-task p  { margin: .7em 0; }
    .prose-task ul, .prose-task ol { margin: .7em 0; padding-left: 1.4em; }
    .prose-task ul { list-style: disc; } .prose-task ol { list-style: decimal; }
    .prose-task li { margin: .25em 0; }
    .prose-task code { background: #f1f5f9; padding: .1em .35em; border-radius: 4px; font-size: .9em; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
    .prose-task pre { background: #0d1117; color: #e6edf3; padding: 1em; border-radius: 10px; overflow-x: auto; margin: .9em 0; }
    .prose-task pre code { background: none; padding: 0; color: inherit; }
    .prose-task blockquote { border-left: 3px solid #c7d2fe; padding-left: 1em; color: #475569; margin: .8em 0; font-style: italic; }
    .prose-task table { border-collapse: collapse; margin: .9em 0; width: 100%; }
    .prose-task th, .prose-task td { border: 1px solid #e2e8f0; padding: .4em .6em; text-align: left; }
    .prose-task th { background: #f8fafc; font-weight: 600; }
    .prose-task img { max-width: 100%; border-radius: 8px; }
    .prose-task a { color: #4f46e5; text-decoration: underline; }
    #sol-code { display: block; padding: 1.1rem 1.25rem; white-space: pre; }
</style>

<script>
(function () {
    'use strict';

    // --- Render the Markdown statement, then highlight any code blocks ---
    var md = document.getElementById('statement-md');
    var body = document.getElementById('statement-body');
    if (md && body && window.marked && window.DOMPurify) {
        body.innerHTML = DOMPurify.sanitize(marked.parse(md.value));
        if (window.hljs) {
            body.querySelectorAll('pre code').forEach(function (el) { hljs.highlightElement(el); });
        }
    }

    // --- Map a filename extension to a highlight.js language ---
    function hljsLang(name) {
        var ext = (name.split('.').pop() || '').toLowerCase();
        switch (ext) {
            case 'cpp': case 'cc': case 'cxx': case 'c': case 'h': case 'hpp': return 'cpp';
            case 'py': return 'python';
            case 'java': return 'java';
            case 'pas': return 'delphi';
            case 'kt': return 'kotlin';
            case 'js': return 'javascript';
            case 'rs': return 'rust';
            case 'go': return 'go';
            default: return 'plaintext';
        }
    }

    // --- Solution preview modal ---
    var modal    = document.getElementById('sol-modal');
    var codeEl   = document.getElementById('sol-code');
    var nameEl   = document.getElementById('sol-modal-name');
    var langEl   = document.getElementById('sol-modal-lang');
    var dlEl     = document.getElementById('sol-download');
    var copyEl   = document.getElementById('sol-copy');
    var base     = <?= json_encode(rtrim(url(''), '/')) ?>;

    function openModal() { modal.classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
    function closeModal() { modal.classList.add('hidden'); document.body.style.overflow = ''; }

    document.querySelectorAll('.sol-preview').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.dataset.id, name = btn.dataset.name, lang = btn.dataset.lang;
            nameEl.textContent = name;
            langEl.textContent = (lang || hljsLang(name)).toUpperCase();
            dlEl.href = base + '/download.php?type=solution&id=' + encodeURIComponent(id);
            codeEl.textContent = 'Učitavanje…';
            codeEl.className = 'hljs text-[13px] leading-6';
            openModal();

            fetch(base + '/solution_raw.php?id=' + encodeURIComponent(id))
                .then(function (r) { return r.ok ? r.text() : Promise.reject(); })
                .then(function (txt) {
                    codeEl.textContent = txt;
                    codeEl.className = 'hljs language-' + hljsLang(name) + ' text-[13px] leading-6';
                    if (window.hljs) { hljs.highlightElement(codeEl); }
                })
                .catch(function () { codeEl.textContent = 'Greška pri učitavanju rješenja.'; });
        });
    });

    if (copyEl) {
        copyEl.addEventListener('click', function () {
            navigator.clipboard.writeText(codeEl.textContent).then(function () {
                var t = copyEl.textContent; copyEl.textContent = 'Kopirano!';
                setTimeout(function () { copyEl.textContent = t; }, 1500);
            });
        });
    }

    modal.querySelectorAll('[data-close]').forEach(function (el) { el.addEventListener('click', closeModal); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
