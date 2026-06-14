<?php
/**
 * config.php
 * ----------------------------------------------------------------------
 * Central configuration and the single PDO database connection.
 *
 * Credentials default to a standard XAMPP setup (root / no password) but
 * can be overridden with environment variables so the same code runs
 * unchanged in production.
 *
 * Never echo anything from this file — it is included, not requested.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------
//  Database credentials  (override via environment variables)
// ---------------------------------------------------------------------
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'bhoi_platform');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// ---------------------------------------------------------------------
//  Application settings
// ---------------------------------------------------------------------
define('APP_NAME', 'BHOI Arhiva');

/**
 * BASE_URL — set this if the app lives in a sub-folder of the web root.
 * Example: app at  http://localhost/bhoi/  ->  BASE_URL = '/bhoi'
 * For a vhost / domain root, leave it as ''.
 */
define('BASE_URL', getenv('BASE_URL') ?: '');

// Absolute path to the uploads directory (outside the DB, inside the project).
define('UPLOAD_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'uploads');

// Maximum size accepted for any single uploaded file (15 MB).
define('MAX_UPLOAD_BYTES', 15 * 1024 * 1024);

// ---------------------------------------------------------------------
//  PDO connection (lazy singleton)
// ---------------------------------------------------------------------
/**
 * Return the shared PDO instance, creating it on first use.
 *
 * Configured for safe, modern usage:
 *   - exceptions on error
 *   - associative fetches by default
 *   - real (non-emulated) prepared statements
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Don't leak credentials/stack traces to the browser.
            http_response_code(500);
            exit(
                'Povezivanje s bazom nije uspjelo. Provjerite da MySQL radi '
                . 'i da su podaci u config.php tačni.'
            );
        }
    }

    return $pdo;
}
