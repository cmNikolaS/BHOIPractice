<?php
/**
 * admin_login.php — Secure admin sign-in.
 * ----------------------------------------------------------------------
 * CSRF-protected form, bcrypt verification, session regeneration on
 * success. Generic error message to avoid revealing which field was wrong.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

// Already signed in? Go straight to the dashboard.
if (is_admin()) {
    redirect('admin_dashboard.php');
}

$error = null;
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Unesite korisničko ime i lozinku.';
    } elseif (admin_login($username, $password)) {
        flash('success', 'Dobrodošli nazad, ' . (current_admin()['display_name'] ?? $username) . '!');
        redirect('admin_dashboard.php');
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
    <link rel="icon" href="data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2032%2032'%3E%3Crect%20width='32'%20height='32'%20rx='7'%20fill='%234f46e5'/%3E%3Ctext%20x='16'%20y='23'%20font-family='Arial'%20font-size='19'%20font-weight='bold'%20fill='white'%20text-anchor='middle'%3EB%3C/text%3E%3C/svg%3E">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter','ui-sans-serif','system-ui','sans-serif'] } } } };</script>
</head>
<body class="grid min-h-full place-items-center bg-slate-100 px-4 font-sans text-slate-800 antialiased">

    <div class="w-full max-w-sm">
        <div class="mb-6 text-center">
            <span class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-2xl bg-indigo-600 text-xl font-extrabold text-white shadow-sm">B</span>
            <h1 class="text-xl font-extrabold tracking-tight text-slate-900"><?= e(APP_NAME) ?></h1>
            <p class="text-sm text-slate-500">Prijava u administraciju</p>
        </div>

        <form method="post" action="<?= e(url('admin_login.php')) ?>"
              class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">

            <?= csrf_field() ?>

            <?php if ($error): ?>
                <div class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-700 ring-1 ring-inset ring-red-600/20">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <label class="mb-1 block text-sm font-medium text-slate-700" for="username">Korisničko ime</label>
            <input id="username" name="username" type="text" autocomplete="username" required
                   value="<?= e($username) ?>"
                   class="mb-4 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30">

            <label class="mb-1 block text-sm font-medium text-slate-700" for="password">Lozinka</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required
                   class="mb-5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30">

            <button type="submit"
                    class="w-full rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500/40">
                Prijava
            </button>
        </form>

        <p class="mt-4 text-center text-sm text-slate-500">
            <a href="<?= e(url('index.php')) ?>" class="transition hover:text-indigo-600">← Nazad na sajt</a>
        </p>
    </div>

</body>
</html>
