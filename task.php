<?php
/**
 * task.php — Single task view with downloadable materials.
 * ----------------------------------------------------------------------
 * Renders the problem statement (Markdown), metadata badges, tags, a
 * "mark as completed" toggle (localStorage, shared with the catalog), and
 * download/preview actions for the PDF, official solutions and tests.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/judge.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(404);
    $page_title = 'Zadatak nije pronađen';
    require __DIR__ . '/includes/header.php';
    echo '<p class="text-muted">Zadatak nije pronađen.</p>';
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
    echo '<p class="text-muted">Zadatak nije pronađen.</p>';
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
<nav class="mb-6 text-sm text-muted">
    <a href="<?= e(url('index.php')) ?>" class="transition hover:text-accent">Zadaci</a>
    <span class="mx-1.5 opacity-50">/</span>
    <span class="text-fg"><?= e($task['title']) ?></span>
</nav>

<div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
    <!-- ===== Main column ===== -->
    <article class="lg:col-span-2">
        <header class="mb-5">
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?= level_badge($task['level_slug']) ?>">
                    <?= e($task['level_name']) ?>
                </span>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset <?= difficulty_badge($task['difficulty']) ?>">
                    <?= e($task['difficulty']) ?>
                </span>
                <span class="inline-flex items-center rounded-full bg-elevated px-2.5 py-0.5 text-xs font-medium text-muted tabular-nums">
                    <?= e((string) $task['year']) ?>
                </span>
            </div>
            <h1 class="text-3xl font-extrabold tracking-tight text-fg">
                <?php if ($task['problem_index'] !== null && $task['problem_index'] !== ''): ?>
                    <span class="text-muted"><?= e($task['problem_index']) ?>.</span>
                <?php endif; ?>
                <?= e($task['title']) ?>
            </h1>

            <!-- Mark as completed (localStorage, shared with the catalog) -->
            <button id="mark-done" type="button" data-id="<?= (int) $task['id'] ?>"
                    class="mt-4 inline-flex items-center gap-2 rounded-xl border border-line bg-card px-4 py-2 text-sm font-semibold text-muted transition hover:border-done/50 hover:text-fg">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 10l4 4 8-9"/></svg>
                <span id="mark-done-label">Označi kao riješeno</span>
            </button>
        </header>

        <!-- Problem statement: readable typography -->
        <div class="rounded-2xl border border-line bg-card p-6 shadow-sm sm:p-8">
            <h2 class="mb-4 text-xs font-semibold uppercase tracking-wide text-muted">Tekst zadatka</h2>
            <?php if (trim((string) $task['statement']) !== ''): ?>
                <!-- Raw Markdown, rendered + sanitised client-side into #statement-body -->
                <textarea id="statement-md" hidden><?= e($task['statement']) ?></textarea>
                <div id="statement-body" class="prose-task max-w-none text-[15px] leading-7 text-fg"></div>
            <?php else: ?>
                <p class="text-muted">
                    Tekst nije unesen. Preuzmite PDF s desne strane za potpun opis zadatka.
                </p>
            <?php endif; ?>
        </div>
    </article>

    <!-- ===== Sidebar ===== -->
    <aside class="space-y-6">
        <!-- Materials / downloads -->
        <div class="rounded-2xl border border-line bg-card p-5 shadow-sm">
            <h2 class="mb-4 text-xs font-semibold uppercase tracking-wide text-muted">Materijali</h2>

            <!-- PDF: open inline in a new tab, with a separate download link -->
            <?php if ($task['pdf_path']): ?>
                <div class="mb-2 flex items-center gap-3 rounded-xl border border-line px-4 py-3 transition hover:border-accent/40 hover:bg-accent/10">
                    <span class="grid h-9 w-9 place-items-center rounded-lg bg-hard/15 text-hard font-bold text-xs">PDF</span>
                    <a href="<?= e(url('download.php?type=pdf&inline=1&task=' . (int) $task['id'])) ?>" target="_blank" rel="noopener"
                       class="flex-1 min-w-0">
                        <span class="block text-sm font-semibold text-fg">Tekst zadatka</span>
                        <span class="block text-xs text-muted">Otvori PDF u novom prozoru</span>
                    </a>
                    <a href="<?= e(url('download.php?type=pdf&task=' . (int) $task['id'])) ?>"
                       title="Preuzmi PDF"
                       class="rounded-lg px-2 py-1 text-xs font-semibold text-accent transition hover:bg-accent/15">↓ Preuzmi</a>
                </div>
            <?php endif; ?>

            <!-- Test cases -->
            <?php if ($task['tests_path']): ?>
                <a href="<?= e(url('download.php?type=tests&task=' . (int) $task['id'])) ?>"
                   class="mb-2 flex items-center gap-3 rounded-xl border border-line px-4 py-3 transition hover:border-easy/40 hover:bg-easy/10">
                    <span class="grid h-9 w-9 place-items-center rounded-lg bg-easy/15 text-easy font-bold text-xs">ZIP</span>
                    <span class="flex-1">
                        <span class="block text-sm font-semibold text-fg">Test primjeri</span>
                        <span class="block text-xs text-muted">Preuzmi ZIP arhivu</span>
                    </span>
                    <span class="text-muted">↓</span>
                </a>
            <?php endif; ?>

            <!-- Solutions: click to preview the code in a modal, or download -->
            <?php if ($solutions): ?>
                <p class="mb-2 mt-4 text-xs font-semibold uppercase tracking-wide text-muted">Službena rješenja</p>
                <?php foreach ($solutions as $sol): ?>
                    <div class="group mb-2 flex items-center gap-3 rounded-xl border border-line px-4 py-3 transition hover:border-accent/40 hover:bg-accent/10">
                        <button type="button"
                                class="sol-preview flex flex-1 min-w-0 items-center gap-3 text-left"
                                data-id="<?= (int) $sol['id'] ?>"
                                data-name="<?= e($sol['original_name']) ?>"
                                data-lang="<?= e($sol['language'] ?: '') ?>">
                            <span class="grid h-9 w-9 place-items-center rounded-lg bg-accent/15 text-accent font-bold text-[10px]">
                                <?= e($sol['language'] ? mb_strtoupper(mb_substr($sol['language'], 0, 3)) : 'SRC') ?>
                            </span>
                            <span class="flex-1 min-w-0">
                                <span class="block truncate text-sm font-semibold text-fg group-hover:text-accent"><?= e($sol['original_name']) ?></span>
                                <span class="block text-xs text-muted">
                                    <?= e($sol['language'] ?: 'Izvorni kod') ?><?php if ($sol['file_size']): ?> · <?= e(human_size((int) $sol['file_size'])) ?><?php endif; ?>
                                    · <span class="text-accent">Pregledaj</span>
                                </span>
                            </span>
                        </button>
                        <a href="<?= e(url('download.php?type=solution&id=' . (int) $sol['id'])) ?>"
                           title="Preuzmi rješenje"
                           class="rounded-lg px-2 py-1 text-muted transition hover:bg-accent/15 hover:text-accent">↓</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!$task['pdf_path'] && !$task['tests_path'] && !$solutions): ?>
                <p class="text-sm text-muted">Materijali za ovaj zadatak još nisu dodani.</p>
            <?php endif; ?>
        </div>

        <!-- Details -->
        <div class="rounded-2xl border border-line bg-card p-5 shadow-sm">
            <h2 class="mb-4 text-xs font-semibold uppercase tracking-wide text-muted">Detalji</h2>
            <dl class="space-y-2.5 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-muted">Godina</dt>
                    <dd class="font-medium text-fg tabular-nums"><?= e((string) $task['year']) ?></dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted">Nivo</dt>
                    <dd class="font-medium text-fg"><?= e($task['level_name']) ?></dd>
                </div>
                <?php if ($task['time_limit_ms']): ?>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted">Vremensko ograničenje</dt>
                    <dd class="font-medium text-fg tabular-nums"><?= e((string) ($task['time_limit_ms'] / 1000)) ?> s</dd>
                </div>
                <?php endif; ?>
                <?php if ($task['memory_limit_mb']): ?>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted">Memorija</dt>
                    <dd class="font-medium text-fg tabular-nums"><?= e((string) $task['memory_limit_mb']) ?> MB</dd>
                </div>
                <?php endif; ?>
            </dl>

            <?php if ($tags): ?>
                <div class="mt-4 border-t border-line pt-4">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">Kategorije</p>
                    <div class="flex flex-wrap gap-1.5">
                        <?php foreach ($tags as $tg): ?>
                            <a href="<?= e(url('index.php?tag=' . urlencode($tg['slug']))) ?>"
                               class="inline-flex items-center rounded-md bg-elevated px-2 py-0.5 text-xs font-medium text-muted transition hover:bg-accent/15 hover:text-accent">
                                <?= e($tg['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </aside>
</div>

<?php if (judge_enabled()): ?>
<!-- ===== Run code (Judge0) — shown only when the judge is configured ===== -->
<section class="mt-6 overflow-hidden rounded-2xl border border-line bg-card shadow-sm">
    <div class="flex items-center justify-between border-b border-line px-5 py-3">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-muted">Pokreni kod</h2>
        <select id="run-lang" class="rounded-lg border border-line bg-elevated px-3 py-1.5 text-sm text-fg outline-none">
            <?php foreach (judge_languages() as $key => $l): ?>
                <option value="<?= e($key) ?>"<?= $key === 'cpp' ? ' selected' : '' ?>><?= e($l['label']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="grid gap-4 p-5 lg:grid-cols-2">
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted" for="run-source">Izvorni kod</label>
            <textarea id="run-source" rows="14" spellcheck="false"
                class="w-full rounded-xl border border-line bg-[#0d1117] px-3 py-2.5 font-mono text-[13px] leading-6 text-fg outline-none focus:border-accent focus:ring-2 focus:ring-accent/30"
                placeholder="// zalijepi svoj kod ovdje"></textarea>
        </div>
        <div class="flex flex-col gap-4">
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted" for="run-stdin">Ulaz (stdin)</label>
                <textarea id="run-stdin" rows="5" spellcheck="false"
                    class="w-full rounded-xl border border-line bg-elevated px-3 py-2.5 font-mono text-[13px] leading-6 text-fg outline-none focus:border-accent focus:ring-2 focus:ring-accent/30"
                    placeholder="ulazni podaci za test"></textarea>
            </div>
            <div>
                <div class="mb-1 flex items-center justify-between">
                    <label class="text-xs font-semibold uppercase tracking-wide text-muted">Izlaz</label>
                    <span id="run-meta" class="text-xs text-muted"></span>
                </div>
                <pre id="run-output" class="m-0 max-h-48 overflow-auto rounded-xl border border-line bg-[#0d1117] px-3 py-2.5 font-mono text-[13px] leading-6 text-fg whitespace-pre-wrap"></pre>
            </div>
        </div>
    </div>
    <div class="flex items-center gap-3 border-t border-line px-5 py-3">
        <button id="run-btn" type="button"
            class="inline-flex items-center gap-1.5 rounded-xl bg-accent px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:brightness-110 disabled:opacity-60">
            ▶ Pokreni
        </button>
        <span id="run-status" class="text-sm font-medium text-muted"></span>
        <span class="ml-auto text-xs text-muted">Kompajlira i pokreće preko Judge0 · provjera protiv zvaničnih test primjera uskoro</span>
    </div>
</section>

<script>
(function () {
    var btn = document.getElementById('run-btn'); if (!btn) return;
    var CSRF = <?= json_encode(csrf_token()) ?>;
    var TASK = <?= (int) $task['id'] ?>;
    var out = document.getElementById('run-output');
    var statusEl = document.getElementById('run-status');
    var metaEl = document.getElementById('run-meta');
    btn.addEventListener('click', function () {
        var src = document.getElementById('run-source').value;
        if (!src.trim()) { statusEl.textContent = 'Unesi kod.'; return; }
        btn.disabled = true; statusEl.textContent = 'Pokrećem…'; out.textContent = ''; metaEl.textContent = '';
        var body = new URLSearchParams();
        body.set('csrf_token', CSRF);
        body.set('task_id', TASK);
        body.set('language', document.getElementById('run-lang').value);
        body.set('source', src);
        body.set('stdin', document.getElementById('run-stdin').value);
        fetch('<?= e(url('submit.php')) ?>', { method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                var j = res.j;
                if (!res.ok || j.error) { statusEl.textContent = j.error || 'Greška.'; return; }
                statusEl.textContent = j.status || 'Gotovo';
                var t = j.time != null ? (j.time + ' s') : '';
                var m = j.memory != null ? (Math.round(j.memory / 1024) + ' MB') : '';
                metaEl.textContent = [t, m].filter(Boolean).join(' · ');
                out.textContent = j.compile ? j.compile : (j.stdout || '') + (j.stderr ? '\n' + j.stderr : '');
            })
            .catch(function () { statusEl.textContent = 'Mreža/server greška.'; })
            .finally(function () { btn.disabled = false; });
    });
})();
</script>
<?php endif; ?>

<!-- ===== Solution preview modal ===== -->
<div id="sol-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" data-close></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="flex max-h-[85vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-card shadow-2xl ring-1 ring-line">
            <header class="flex items-center gap-3 border-b border-line px-5 py-3">
                <span id="sol-modal-lang" class="rounded-md bg-accent/15 px-2 py-0.5 text-xs font-semibold text-accent">CODE</span>
                <span id="sol-modal-name" class="min-w-0 flex-1 truncate text-sm font-semibold text-fg"></span>
                <button type="button" id="sol-copy" class="rounded-lg px-2.5 py-1 text-xs font-medium text-muted transition hover:bg-elevated">Kopiraj</button>
                <a id="sol-download" href="#" class="rounded-lg px-2.5 py-1 text-xs font-medium text-accent transition hover:bg-accent/15">↓ Preuzmi</a>
                <button type="button" class="rounded-lg px-2 py-1 text-muted transition hover:bg-elevated hover:text-fg" data-close aria-label="Zatvori">✕</button>
            </header>
            <div class="overflow-auto bg-[#0d1117]">
                <pre class="m-0 p-0"><code id="sol-code" class="hljs text-[13px] leading-6"></code></pre>
            </div>
        </div>
    </div>
</div>

<!-- Markdown + syntax highlighting + math (CDN) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css">
<script src="https://cdn.jsdelivr.net/npm/marked@12.0.0/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3.0.9/dist/purify.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/contrib/auto-render.min.js"></script>

<style>
    /* Rendered Markdown statement (theme-aware) */
    .prose-task h1 { font-size: 1.4rem; font-weight: 800; margin: 1.2em 0 .5em; color: var(--fg); }
    .prose-task h2 { font-size: 1.15rem; font-weight: 700; margin: 1.2em 0 .4em; color: var(--fg); }
    .prose-task h3 { font-size: 1rem; font-weight: 700; margin: 1em 0 .3em; color: var(--fg); }
    .prose-task p  { margin: .7em 0; }
    .prose-task ul, .prose-task ol { margin: .7em 0; padding-left: 1.4em; }
    .prose-task ul { list-style: disc; } .prose-task ol { list-style: decimal; }
    .prose-task li { margin: .25em 0; }
    .prose-task code { background: var(--elevated); padding: .1em .35em; border-radius: 4px; font-size: .9em; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
    .prose-task pre { background: #0d1117; color: #e6edf3; padding: 1em; border-radius: 10px; overflow-x: auto; margin: .9em 0; }
    .prose-task pre code { background: none; padding: 0; color: inherit; }
    .prose-task blockquote { border-left: 3px solid var(--line); padding-left: 1em; color: var(--muted); margin: .8em 0; font-style: italic; }
    .prose-task table { border-collapse: collapse; margin: .9em 0; width: 100%; }
    .prose-task th, .prose-task td { border: 1px solid var(--line); padding: .4em .6em; text-align: left; }
    .prose-task th { background: var(--elevated); font-weight: 600; }
    .prose-task img { max-width: 100%; border-radius: 8px; }
    .prose-task a { color: #2f81f7; text-decoration: underline; }
    #sol-code { display: block; padding: 1.1rem 1.25rem; white-space: pre; }
    #mark-done.is-done { background: rgba(46,160,67,.15); border-color: rgba(46,160,67,.55); color: #2ea043; }
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
        // Render LaTeX math ($…$, $$…$$) commonly used in problem statements.
        if (window.renderMathInElement) {
            try {
                renderMathInElement(body, {
                    delimiters: [
                        { left: '$$', right: '$$', display: true },
                        { left: '$',  right: '$',  display: false },
                        { left: '\\(', right: '\\)', display: false },
                        { left: '\\[', right: '\\]', display: true }
                    ],
                    throwOnError: false
                });
            } catch (e) {}
        }
    }

    // --- Mark as completed (shared with the catalog via localStorage) ---
    var DONE_KEY = 'bhoi_completed_v1';
    var markBtn = document.getElementById('mark-done');
    if (markBtn) {
        var taskId = markBtn.dataset.id;
        var label = document.getElementById('mark-done-label');
        var getDone = function () {
            try { return new Set(JSON.parse(localStorage.getItem(DONE_KEY) || '[]').map(String)); }
            catch (e) { return new Set(); }
        };
        var render = function () {
            var d = getDone().has(taskId);
            markBtn.classList.toggle('is-done', d);
            if (label) label.textContent = d ? 'Riješeno ✓' : 'Označi kao riješeno';
        };
        markBtn.addEventListener('click', function () {
            var s = getDone();
            if (s.has(taskId)) { s.delete(taskId); } else { s.add(taskId); }
            try { localStorage.setItem(DONE_KEY, JSON.stringify(Array.from(s))); } catch (e) {}
            render();
        });
        render();
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
