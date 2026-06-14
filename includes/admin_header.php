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
    <?php require __DIR__ . '/theme_head.php'; ?>
</head>
<body class="h-full bg-bg text-fg font-sans antialiased">

<header class="border-b border-line bg-[#16181d] text-white">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6">
        <div class="flex items-center gap-2.5">
            <span class="grid h-8 w-8 place-items-center rounded-lg bg-brand text-[#1a1a1a] font-extrabold">B</span>
            <span class="font-bold tracking-tight"><?= e(APP_NAME) ?> · Admin</span>
        </div>
        <div class="flex items-center gap-2 text-sm">
            <a href="<?= e(url('index.php')) ?>" target="_blank" class="rounded-lg px-3 py-1.5 text-slate-300 transition hover:bg-white/10 hover:text-white">Pogledaj sajt ↗</a>
            <span class="hidden text-slate-500 sm:inline">·</span>
            <span class="hidden text-slate-300 sm:inline"><?= e($admin['display_name'] ?? $admin['username'] ?? '') ?></span>
            <a href="<?= e(url('admin_logout.php')) ?>" class="rounded-lg bg-white/10 px-3 py-1.5 font-medium text-white transition hover:bg-white/20">Odjava</a>
        </div>
    </div>
</header>

<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
<?php
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
