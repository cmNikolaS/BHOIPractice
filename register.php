<?php
/**
 * register.php — create a visitor account (simple username/password).
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
    $confirm  = (string) ($_POST['confirm'] ?? '');
    if ($password !== $confirm) {
        $error = 'Lozinke se ne podudaraju.';
    } elseif (user_register($username, $password, $error)) {
        flash('success', 'Nalog kreiran. Dobrodošao, ' . $username . '!');
        redirect('index.php');
    }
}
?><!DOCTYPE html>
<html lang="bs" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registracija — <?= e(APP_NAME) ?></title>
    <?php require __DIR__ . '/includes/theme_head.php'; ?>
</head>
<body class="grid min-h-full place-items-center bg-bg px-4 font-sans text-fg antialiased">
    <div class="w-full max-w-sm">
        <div class="mb-6 text-center">
            <a href="<?= e(url('index.php')) ?>" class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-2xl bg-brand text-xl font-extrabold text-[#1a1a1a] shadow-sm">B</a>
            <h1 class="text-xl font-extrabold tracking-tight text-fg">Registracija</h1>
            <p class="text-sm text-muted">Napravi nalog da pratiš napredak na svim uređajima</p>
        </div>

        <form method="post" action="<?= e(url('register.php')) ?>" class="rounded-2xl border border-line bg-card p-6 shadow-sm">
            <?= csrf_field() ?>
            <?php if ($error): ?>
                <div class="mb-4 rounded-xl bg-hard/15 px-4 py-3 text-sm font-medium text-hard ring-1 ring-inset ring-hard/30"><?= e($error) ?></div>
            <?php endif; ?>

            <label class="mb-1 block text-sm font-medium text-fg" for="username">Korisničko ime</label>
            <input id="username" name="username" type="text" autocomplete="username" required value="<?= e($username) ?>"
                   class="mb-1 w-full rounded-xl border border-line bg-elevated px-3 py-2.5 text-sm text-fg outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/30">
            <p class="mb-4 text-xs text-muted">3–30 znakova: slova, brojevi, _ . -</p>

            <label class="mb-1 block text-sm font-medium text-fg" for="password">Lozinka</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required minlength="6"
                   class="mb-4 w-full rounded-xl border border-line bg-elevated px-3 py-2.5 text-sm text-fg outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/30">

            <label class="mb-1 block text-sm font-medium text-fg" for="confirm">Ponovi lozinku</label>
            <input id="confirm" name="confirm" type="password" autocomplete="new-password" required minlength="6"
                   class="mb-5 w-full rounded-xl border border-line bg-elevated px-3 py-2.5 text-sm text-fg outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/30">

            <button type="submit" class="w-full rounded-xl bg-accent px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-110 focus:ring-2 focus:ring-accent/40">
                Registruj se
            </button>
        </form>

        <p class="mt-4 text-center text-sm text-muted">
            Već imaš nalog? <a href="<?= e(url('login.php')) ?>" class="font-semibold text-accent transition hover:brightness-110">Prijavi se</a>
        </p>
    </div>
</body>
</html>
