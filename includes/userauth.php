<?php
/**
 * includes/userauth.php — simple username/password accounts for visitors.
 * ----------------------------------------------------------------------
 * Separate from the admin auth (auth.php): a normal user account just lets
 * someone save their solved-task progress to the server (synced across
 * devices) instead of only per-browser localStorage. Loaded by bootstrap.php
 * so the session state is available everywhere (incl. the header nav).
 */

declare(strict_types=1);

function is_user(): bool { return isset($_SESSION['user']); }

function current_user(): ?array { return $_SESSION['user'] ?? null; }

/** Register a new account and log in. Returns true, or false with $err set. */
function user_register(string $username, string $password, ?string &$err = null): bool
{
    $username = trim($username);
    if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $username)) {
        $err = 'Korisničko ime: 3–30 znakova (slova, brojevi, _ . -).';
        return false;
    }
    if (mb_strlen($password) < 6) {
        $err = 'Lozinka mora imati barem 6 znakova.';
        return false;
    }
    $pdo = db();
    $chk = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $chk->execute([$username]);
    if ($chk->fetch()) {
        $err = 'Korisničko ime je već zauzeto.';
        return false;
    }
    $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)')
        ->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
    $id = (int) $pdo->lastInsertId();
    session_regenerate_id(true);
    $_SESSION['user'] = ['id' => $id, 'username' => $username];
    return true;
}

/** Verify credentials and log in. Returns true on success. */
function user_login(string $username, string $password): bool
{
    $username = trim($username);
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    // Constant-ish time for unknown users.
    $hash = $row['password_hash'] ?? '$2y$10$usesomesillystringfooizzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzu';
    if (!password_verify($password, $hash) || !$row) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['user'] = ['id' => (int) $row['id'], 'username' => $row['username']];
    return true;
}

function user_logout(): void { unset($_SESSION['user']); }
