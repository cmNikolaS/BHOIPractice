<?php
/**
 * includes/auth.php
 * ----------------------------------------------------------------------
 * Admin authentication helpers. Relies on bootstrap.php being loaded
 * first (for the session and the db() connection).
 */

declare(strict_types=1);

/**
 * Attempt to log in. Returns true on success.
 * Uses bcrypt verification and is resistant to user-enumeration timing
 * by always running password_verify against a dummy hash when the user
 * does not exist.
 */
function admin_login(string $username, string $password): bool
{
    $stmt = db()->prepare('SELECT id, username, password_hash, display_name FROM admins WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    // Dummy hash keeps response time roughly constant for unknown users.
    $hash = $admin['password_hash'] ?? '$2y$10$usesomesillystringfooizzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzu';

    if (!password_verify($password, $hash) || !$admin) {
        return false;
    }

    // Prevent session fixation: new id after privilege change.
    session_regenerate_id(true);

    $_SESSION['admin'] = [
        'id'           => (int) $admin['id'],
        'username'     => $admin['username'],
        'display_name' => $admin['display_name'],
    ];

    $upd = db()->prepare('UPDATE admins SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?');
    $upd->execute([$admin['id']]);

    return true;
}

/** Log out the current admin and clear the session. */
function admin_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

/** Currently logged-in admin array, or null. */
function current_admin(): ?array
{
    return $_SESSION['admin'] ?? null;
}

/** Is an admin logged in? */
function is_admin(): bool
{
    return isset($_SESSION['admin']);
}

/** Guard: redirect to the login page if not authenticated. */
function require_admin(): void
{
    if (!is_admin()) {
        flash('error', 'Morate biti prijavljeni za pristup toj stranici.');
        redirect('admin_login.php');
    }
}
