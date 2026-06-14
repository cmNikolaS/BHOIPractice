<?php
/**
 * includes/uploads.php
 * ----------------------------------------------------------------------
 * Validated, hardened file-upload handling.
 *
 * Strategy:
 *   - Whitelist by extension (the authoritative check).
 *   - Verify MIME via finfo when a MIME whitelist is supplied.
 *   - Enforce a max size.
 *   - Store under /uploads/<subdir>/ with a random, unguessable name so
 *     the original filename can never be used for path traversal or to
 *     overwrite anything. The original name is preserved separately in
 *     the DB and reused only as the download filename.
 */

declare(strict_types=1);

/** Allowed upload profiles, keyed by logical type. */
function upload_profiles(): array
{
    return [
        'pdf' => [
            'subdir' => 'pdf',
            'ext'    => ['pdf'],
            'mime'   => ['application/pdf'],
        ],
        'tests' => [
            'subdir' => 'tests',
            'ext'    => ['zip'],
            // Browsers/OSes report zips inconsistently, so accept the common set.
            'mime'   => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream', 'multipart/x-zip'],
        ],
        'solution' => [
            'subdir' => 'solutions',
            'ext'    => ['cpp', 'cc', 'cxx', 'c', 'h', 'hpp', 'py', 'java', 'pas', 'txt', 'kt', 'js', 'rs', 'go'],
            // Source files are detected as plain text / octet-stream; rely on the
            // extension whitelist (these are only ever served as downloads).
            'mime'   => null,
        ],
    ];
}

/**
 * Validate and move one uploaded file.
 *
 * @param array  $file   A single entry from $_FILES (with name/tmp_name/error/size).
 * @param string $type   One of the keys returned by upload_profiles().
 * @return array{path:string, original_name:string, size:int}  on success.
 * @throws RuntimeException with a human message on any validation failure.
 */
function save_upload(array $file, string $type): array
{
    $profiles = upload_profiles();
    if (!isset($profiles[$type])) {
        throw new RuntimeException('Nepoznat tip datoteke.');
    }
    $profile = $profiles[$type];

    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('Neispravan upload.');
    }

    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Nije odabrana datoteka.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Greška pri uploadu (kod ' . $file['error'] . ').');
    }
    if ($file['size'] <= 0 || $file['size'] > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('Datoteka je prevelika (maksimalno ' . human_size(MAX_UPLOAD_BYTES) . ').');
    }
    // Confirm the file really came through HTTP upload, not a forged path.
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Neispravan izvor datoteke.');
    }

    $original = (string) $file['name'];
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));

    if (!in_array($ext, $profile['ext'], true)) {
        throw new RuntimeException(
            'Nedozvoljena ekstenzija ".' . $ext . '". Dozvoljeno: ' . implode(', ', $profile['ext']) . '.'
        );
    }

    if ($profile['mime'] !== null) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: '';
        if (!in_array($mime, $profile['mime'], true)) {
            throw new RuntimeException('Tip sadržaja datoteke (' . $mime . ') ne odgovara očekivanom.');
        }
    }

    // Ensure the destination directory exists.
    $destDir = UPLOAD_DIR . DIRECTORY_SEPARATOR . $profile['subdir'];
    if (!is_dir($destDir) && !mkdir($destDir, 0775, true) && !is_dir($destDir)) {
        throw new RuntimeException('Ne mogu kreirati direktorij za upload.');
    }

    // Random, collision-free, unguessable stored name.
    $stored = bin2hex(random_bytes(16)) . '.' . $ext;
    $absPath = $destDir . DIRECTORY_SEPARATOR . $stored;

    if (!move_uploaded_file($file['tmp_name'], $absPath)) {
        throw new RuntimeException('Spremanje datoteke nije uspjelo.');
    }

    // Relative path (forward slashes) is what we persist in the DB.
    return [
        'path'          => $profile['subdir'] . '/' . $stored,
        'original_name' => $original,
        'size'          => (int) $file['size'],
    ];
}

/** Delete a stored upload by its relative path (no-op if missing). */
function delete_upload(?string $relativePath): void
{
    if (!$relativePath) {
        return;
    }
    $abs = upload_abspath($relativePath);
    if ($abs !== null && is_file($abs)) {
        @unlink($abs);
    }
}

/**
 * Resolve a stored relative path to a safe absolute path, guaranteeing it
 * stays inside UPLOAD_DIR. Returns null if it escapes or does not exist.
 */
function upload_abspath(string $relativePath): ?string
{
    $base = realpath(UPLOAD_DIR);
    $candidate = realpath(UPLOAD_DIR . DIRECTORY_SEPARATOR . $relativePath);

    if ($base === false || $candidate === false) {
        return null;
    }
    // Must be within the uploads directory (defeats ../ traversal).
    if (!str_starts_with($candidate, $base . DIRECTORY_SEPARATOR)) {
        return null;
    }
    return $candidate;
}
