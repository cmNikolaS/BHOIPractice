<?php
/**
 * includes/header.php — public site chrome (opening markup + top nav).
 * Set $page_title before including for a custom <title>.
 */
$page_title = $page_title ?? APP_NAME;
?><!DOCTYPE html>
<html lang="bs" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> — <?= e(APP_NAME) ?></title>
    <meta name="description" content="Arhiva zadataka sa takmičenja iz informatike u Bosni i Hercegovini.">
    <?php require __DIR__ . '/theme_head.php'; ?>
</head>
<body class="h-full bg-bg text-fg font-sans antialiased">

<!-- ===== Top navigation ===== -->
<header class="sticky top-0 z-30 border-b border-line bg-card/80 backdrop-blur">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6">
        <a href="<?= e(url('index.php')) ?>" class="flex items-center gap-2.5 group">
            <span class="grid h-9 w-9 place-items-center rounded-xl bg-brand text-[#1a1a1a] font-extrabold shadow-sm transition group-hover:scale-105">B</span>
            <span class="text-lg font-extrabold tracking-tight text-fg"><?= e(APP_NAME) ?></span>
        </a>
        <nav class="flex flex-wrap items-center justify-end gap-x-1 gap-y-1 text-sm font-medium">
            <a href="<?= e(url('index.php')) ?>" class="rounded-lg px-3 py-2 text-muted transition hover:bg-elevated hover:text-fg">Zadaci</a>
            <?php if (function_exists('is_user') && is_user()): ?>
                <span class="rounded-lg px-3 py-2 font-semibold text-fg">👤 <?= e(current_user()['username']) ?></span>
                <a href="<?= e(url('logout.php')) ?>" class="rounded-lg px-3 py-2 text-muted transition hover:bg-elevated hover:text-fg">Odjava</a>
            <?php else: ?>
                <a href="<?= e(url('login.php')) ?>" class="rounded-lg px-3 py-2 text-muted transition hover:bg-elevated hover:text-fg">Prijava</a>
            <?php endif; ?>
            <?php if (function_exists('is_admin') && is_admin()): ?>
                <a href="<?= e(url('admin_dashboard.php')) ?>" class="rounded-lg px-3 py-2 text-muted transition hover:bg-elevated hover:text-fg">Admin panel</a>
            <?php else: ?>
                <a href="<?= e(url('admin_login.php')) ?>" class="rounded-lg px-3 py-2 text-muted transition hover:bg-elevated hover:text-fg">Admin</a>
            <?php endif; ?>
            <button type="button" onclick="toggleTheme()" title="Promijeni temu"
                    class="ml-1 grid h-9 w-9 place-items-center rounded-lg text-muted transition hover:bg-elevated hover:text-fg">
                <!-- sun (shown in dark mode) -->
                <svg class="hidden h-5 w-5 dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4l1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4m11.4-11.4l1.4-1.4"/></svg>
                <!-- moon (shown in light mode) -->
                <svg class="block h-5 w-5 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/></svg>
            </button>
        </nav>
    </div>
</header>

<script>
window.BHOI_AUTH = { user: <?= is_user() ? 'true' : 'false' ?>, progressUrl: <?= json_encode(url('progress.php')) ?>, csrf: <?= json_encode(csrf_token()) ?> };
(function () {
    var A = window.BHOI_AUTH, t = null;
    window.bhoiLoadProgress = function () {
        if (!A.user) return Promise.resolve(null);
        return fetch(A.progressUrl, { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (j) { return (j && j.solved) ? j.solved : null; })
            .catch(function () { return null; });
    };
    window.bhoiSaveProgress = function (arr) {
        if (!A.user) return;
        clearTimeout(t);
        t = setTimeout(function () {
            var b = new URLSearchParams();
            b.set('csrf_token', A.csrf); b.set('solved', JSON.stringify(arr));
            fetch(A.progressUrl, { method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: b.toString() }).catch(function () {});
        }, 600);
    };
})();
</script>

<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
<?php
// Render any queued flash messages.
foreach (take_flashes() as $f):
    $palette = [
        'success' => 'bg-done/15 text-done ring-done/30',
        'error'   => 'bg-hard/15 text-hard ring-hard/30',
        'info'    => 'bg-accent/15 text-accent ring-accent/30',
    ][$f['type']] ?? 'bg-elevated text-fg ring-line';
?>
    <div class="mb-5 rounded-xl px-4 py-3 text-sm font-medium ring-1 ring-inset <?= $palette ?>">
        <?= e($f['message']) ?>
    </div>
<?php endforeach; ?>
