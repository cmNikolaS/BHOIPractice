<?php
/**
 * login.php — visitor sign-in (simple username/password).
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

if (is_user()) {
    redirect('index.php');
}

$error = null;
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($username === '' || $password === '') {
        $error = 'Unesite korisničko ime i lozinku.';
    } elseif (user_login($username, $password)) {
        flash('success', 'Dobrodošli, ' . $username . '!');
        redirect('index.php');
    } else {
        $error = 'Pogrešno korisničko ime ili lozinka.';
    }
}
?><!DOCTYPE html>
<html lang="bs" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prijava — <?= e(APP_NAME) ?></title>
    <?php require __DIR__ . '/includes/theme_head.php'; ?>
</head>
<body class="grid min-h-full place-items-center bg-bg px-4 font-sans text-fg antialiased">
    <div class="w-full max-w-sm">
        <div class="mb-6 text-center">
            <a href="<?= e(url('index.php')) ?>" class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-2xl bg-brand text-xl font-extrabold text-[#1a1a1a] shadow-sm">B</a>
            <h1 class="text-xl font-extrabold tracking-tight text-fg">Prijava</h1>
            <p class="text-sm text-muted">Prijavi se da sačuvaš svoj napredak</p>
        </div>

        <form method="post" action="<?= e(url('login.php')) ?>" class="rounded-2xl border border-line bg-card p-6 shadow-sm">
            <?= csrf_field() ?>
            <?php if ($error): ?>
                <div class="mb-4 rounded-xl bg-hard/15 px-4 py-3 text-sm font-medium text-hard ring-1 ring-inset ring-hard/30"><?= e($error) ?></div>
            <?php endif; ?>

            <label class="mb-1 block text-sm font-medium text-fg" for="username">Korisničko ime</label>
            <input id="username" name="username" type="text" autocomplete="username" required value="<?= e($username) ?>"
                   class="mb-4 w-full rounded-xl border border-line bg-elevated px-3 py-2.5 text-sm text-fg outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/30">

            <label class="mb-1 block text-sm font-medium text-fg" for="password">Lozinka</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required
                   class="mb-5 w-full rounded-xl border border-line bg-elevated px-3 py-2.5 text-sm text-fg outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/30">

            <button type="submit" class="w-full rounded-xl bg-accent px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-110 focus:ring-2 focus:ring-accent/40">
                Prijava
            </button>
        </form>

        <p class="mt-4 text-center text-sm text-muted">
            Nemaš nalog? <a href="<?= e(url('register.php')) ?>" class="font-semibold text-accent transition hover:brightness-110">Registruj se</a>
        </p>
        <p class="mt-2 text-center text-sm text-muted">
            <a href="<?= e(url('index.php')) ?>" class="transition hover:text-accent">← Nazad na sajt</a>
        </p>
    </div>
</body>
</html>
