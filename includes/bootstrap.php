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
        'kantonalno'   => 'bg-sky-50 text-sky-700 ring-sky-600/20',
        'regionalno'   => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'federalno'    => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'republicko'   => 'bg-violet-50 text-violet-700 ring-violet-600/20',
        'drzavno-bhoi' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        default        => 'bg-slate-100 text-slate-700 ring-slate-600/20',
    };
}

/** Tailwind classes for a difficulty badge. */
function difficulty_badge(string $difficulty): string
{
    return match ($difficulty) {
        'Lako'    => 'bg-green-50 text-green-700 ring-green-600/20',
        'Srednje' => 'bg-yellow-50 text-yellow-700 ring-yellow-600/20',
        'Teško'   => 'bg-red-50 text-red-700 ring-red-600/20',
        default   => 'bg-slate-100 text-slate-700 ring-slate-600/20',
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
