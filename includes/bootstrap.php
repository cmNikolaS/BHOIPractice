<?php
/**
 * includes/bootstrap.php
 * ----------------------------------------------------------------------
 * Loaded by every entry point. Boots the database config, starts a
 * hardened session, and defines the small set of helpers used across the
 * app (escaping, redirects, CSRF, flash messages, UI badge helpers).
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

// ---------------------------------------------------------------------
//  Session (started once, with secure cookie flags)
// ---------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    // Detect HTTPS directly, or via a reverse proxy (e.g. Fly.io / load balancers).
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $isHttps,
    ]);
    session_start();
}

// ---------------------------------------------------------------------
//  Output escaping
// ---------------------------------------------------------------------
/** HTML-escape a value for safe output. Use on EVERYTHING dynamic. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ---------------------------------------------------------------------
//  URLs & redirects
// ---------------------------------------------------------------------
/** Prefix an app-relative path with BASE_URL. */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Send a Location redirect and stop. */
function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

// ---------------------------------------------------------------------
//  CSRF protection
// ---------------------------------------------------------------------
/** Return the per-session CSRF token, generating it on first use. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden <input> carrying the CSRF token, for embedding in forms. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Validate a submitted token; aborts the request on mismatch. */
function require_csrf(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Sesija je istekla ili je zahtjev neispravan (CSRF). Osvježite stranicu i pokušajte ponovo.');
    }
}

// ---------------------------------------------------------------------
//  Flash messages (one-shot notifications across a redirect)
// ---------------------------------------------------------------------
/** Queue a flash message. $type is one of: success | error | info. */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Pull and clear all queued flash messages. */
function take_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

// ---------------------------------------------------------------------
//  UI helpers — Tailwind colour classes for badges
// ---------------------------------------------------------------------
/** Tailwind classes for a competition-level badge, keyed by level slug. */
function level_badge(string $slug): string
{
    return match ($slug) {
        'drzavno-bhoi' => 'bg-rose-500/10 text-rose-600 dark:text-rose-400 ring-rose-500/25',
        'jbhoi'        => 'bg-violet-500/10 text-violet-600 dark:text-violet-400 ring-violet-500/25',
        'bhgoi'        => 'bg-fuchsia-500/10 text-fuchsia-600 dark:text-fuchsia-400 ring-fuchsia-500/25',
        'kvalifikacije'=> 'bg-sky-500/10 text-sky-600 dark:text-sky-400 ring-sky-500/25',
        // legacy cantonal levels (still supported)
        'kantonalno'   => 'bg-sky-500/10 text-sky-600 dark:text-sky-400 ring-sky-500/25',
        'regionalno'   => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 ring-emerald-500/25',
        'federalno'    => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 ring-amber-500/25',
        'republicko'   => 'bg-violet-500/10 text-violet-600 dark:text-violet-400 ring-violet-500/25',
        default        => 'bg-elevated text-muted ring-line',
    };
}

/** Tailwind classes for a difficulty badge (LeetCode-style colors). */
function difficulty_badge(string $difficulty): string
{
    return match ($difficulty) {
        'Lako'    => 'bg-easy/15 text-easy ring-easy/30',
        'Srednje' => 'bg-medium/15 text-medium ring-medium/30',
        'Teško'   => 'bg-hard/15 text-hard ring-hard/30',
        default   => 'bg-elevated text-muted ring-line',
    };
}

/** Human-readable file size. */
function human_size(?int $bytes): string
{
    if ($bytes === null) {
        return '';
    }
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $size = (float) $bytes;
    while ($size >= 1024 && $i < count($units) - 1) {
        $size /= 1024;
        $i++;
    }
    return ($i === 0 ? (int) $size : number_format($size, 1)) . ' ' . $units[$i];
}

/**
 * Build a URL-safe slug from arbitrary text (handles Bosnian diacritics).
 */
function slugify(string $text): string
{
    $map = [
        'č' => 'c', 'ć' => 'c', 'đ' => 'd', 'š' => 's', 'ž' => 'z',
        'Č' => 'c', 'Ć' => 'c', 'Đ' => 'd', 'Š' => 's', 'Ž' => 'z',
    ];
    $text = strtr($text, $map);
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^a-z0-9]+/u', '-', $text) ?? '';
    return trim($text, '-');
}
