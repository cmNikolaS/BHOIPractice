<?php
/**
 * includes/admin_header.php — chrome for the back-office.
 * Assumes require_admin() has already run.
 */
$page_title = $page_title ?? 'Admin panel';
$admin = current_admin();
?><!DOCTYPE html>
<html lang="bs" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> — <?= e(APP_NAME) ?></title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2032%2032'%3E%3Crect%20width='32'%20height='32'%20rx='7'%20fill='%234f46e5'/%3E%3Ctext%20x='16'%20y='23'%20font-family='Arial'%20font-size='19'%20font-weight='bold'%20fill='white'%20text-anchor='middle'%3EB%3C/text%3E%3C/svg%3E">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'] } } } };
    </script>
    <style>body { -webkit-font-smoothing: antialiased; }</style>
</head>
<body class="h-full bg-slate-100 text-slate-800 font-sans antialiased">

<header class="border-b border-slate-200 bg-slate-900 text-white">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6">
        <div class="flex items-center gap-2.5">
            <span class="grid h-8 w-8 place-items-center rounded-lg bg-indigo-500 font-extrabold">B</span>
            <span class="font-bold tracking-tight"><?= e(APP_NAME) ?> · Admin</span>
        </div>
        <div class="flex items-center gap-2 text-sm">
            <a href="<?= e(url('index.php')) ?>" target="_blank" class="rounded-lg px-3 py-1.5 text-slate-300 transition hover:bg-white/10 hover:text-white">Pogledaj sajt ↗</a>
            <span class="hidden text-slate-400 sm:inline">·</span>
            <span class="hidden text-slate-300 sm:inline"><?= e($admin['display_name'] ?? $admin['username'] ?? '') ?></span>
            <a href="<?= e(url('admin_logout.php')) ?>" class="rounded-lg bg-white/10 px-3 py-1.5 font-medium text-white transition hover:bg-white/20">Odjava</a>
        </div>
    </div>
</header>

<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
<?php
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
