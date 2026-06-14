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
    <link rel="icon" href="data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2032%2032'%3E%3Crect%20width='32'%20height='32'%20rx='7'%20fill='%234f46e5'/%3E%3Ctext%20x='16'%20y='23'%20font-family='Arial'%20font-size='19'%20font-weight='bold'%20fill='white'%20text-anchor='middle'%3EB%3C/text%3E%3C/svg%3E">

    <!-- Tailwind CSS (Play CDN — for production, install via the Tailwind CLI) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
                },
            },
        };
    </script>
    <style>
        body { -webkit-font-smoothing: antialiased; }
        /* Subtle custom scrollbar to match the premium feel */
        ::-webkit-scrollbar { width: 10px; height: 10px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="h-full bg-slate-50 text-slate-800 font-sans antialiased">

<!-- ===== Top navigation ===== -->
<header class="sticky top-0 z-30 border-b border-slate-200 bg-white/80 backdrop-blur">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6">
        <a href="<?= e(url('index.php')) ?>" class="flex items-center gap-2.5 group">
            <span class="grid h-9 w-9 place-items-center rounded-xl bg-indigo-600 text-white font-extrabold shadow-sm transition group-hover:scale-105">B</span>
            <span class="text-lg font-extrabold tracking-tight text-slate-900"><?= e(APP_NAME) ?></span>
        </a>
        <nav class="flex items-center gap-1 text-sm font-medium">
            <a href="<?= e(url('index.php')) ?>" class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">Zadaci</a>
            <?php if (function_exists('is_admin') && is_admin()): ?>
                <a href="<?= e(url('admin_dashboard.php')) ?>" class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">Admin panel</a>
            <?php else: ?>
                <a href="<?= e(url('admin_login.php')) ?>" class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">Admin</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
<?php
// Render any queued flash messages.
foreach (take_flashes() as $f):
    $palette = [
        'success' => 'bg-emerald-50 text-emerald-800 ring-emerald-600/20',
        'error'   => 'bg-red-50 text-red-800 ring-red-600/20',
        'info'    => 'bg-sky-50 text-sky-800 ring-sky-600/20',
    ][$f['type']] ?? 'bg-slate-50 text-slate-800 ring-slate-600/20';
?>
    <div class="mb-5 rounded-xl px-4 py-3 text-sm font-medium ring-1 ring-inset <?= $palette ?>">
        <?= e($f['message']) ?>
    </div>
<?php endforeach; ?>
