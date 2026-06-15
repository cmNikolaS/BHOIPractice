<?php
/**
 * pdf_to_markdown.php — Fill empty statements from the task PDFs.
 * ----------------------------------------------------------------------
 * Runs pdftotext over each task's PDF and stores a lightly-formatted
 * Markdown version in tasks.statement, so the text shows on the site
 * instead of only a PDF link. Only fills tasks whose statement is empty
 * (the 90 modern tasks already have Markdown — left untouched). Scanned
 * PDFs with no text layer yield nothing and keep the "no text" fallback.
 *
 * Requires `pdftotext` (poppler-utils) on PATH.
 *
 *   $env:DB_PORT="3307"; C:\xampp\php\php.exe pdf_to_markdown.php
 *   DB_DRIVER=sqlite DB_SQLITE_PATH=/data/app.sqlite php pdf_to_markdown.php
 */

declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit('Run this from the command line.'); }
require __DIR__ . '/config.php';

function out(string $m): void { fwrite(STDOUT, $m . PHP_EOL); }

/** Turn raw pdftotext output into lightly-structured Markdown. */
function to_markdown(string $txt): string {
    // pdftotext can emit stray non-UTF-8 bytes, which make /u regexes return null.
    if (!mb_check_encoding($txt, 'UTF-8')) {
        $txt = function_exists('mb_scrub') ? mb_scrub($txt, 'UTF-8')
             : (string) @iconv('UTF-8', 'UTF-8//IGNORE', $txt);
    }
    $txt = str_replace("\r\n", "\n", $txt);
    $txt = preg_replace('/[ \t]+/u', ' ', $txt) ?? $txt;  // collapse runs of spaces
    $txt = preg_replace('/\n{3,}/u', "\n\n", $txt) ?? $txt; // collapse blank lines
    $lines = explode("\n", $txt);
    // Common BHOI section labels -> markdown headings.
    $sections = '/^(Ulazni podaci|Izlazni podaci|Ulaz i [Ii]zlaz|Format ulaza i izlaza|Format ulaza|Format izlaza|Ulaz|Izlaz|Zadatak|Ograničenja(?: na resurse)?|Podzadaci|Podzadatak\b.*|Primjeri|Primjer\b.*|Testni primjeri|Test primjeri|Objašnjenj\w*|Napomena\w*|Bodovanje\w*|Evaluacija)\s*$/u';
    $outLines = [];
    foreach ($lines as $ln) {
        $t = trim($ln);
        if ($t !== '' && preg_match($sections, $t)) {
            $outLines[] = '';
            $outLines[] = '## ' . $t;
            $outLines[] = '';
        } else {
            $outLines[] = $t;
        }
    }
    $md = implode("\n", $outLines);
    $md = preg_replace('/\n{3,}/u', "\n\n", $md);
    return trim($md);
}

$pdftotext = trim((string) (getenv('PDFTOTEXT') ?: 'pdftotext'));

$pdo = db();
$rows = $pdo->query(
    "SELECT id, slug, pdf_path FROM tasks
     WHERE pdf_path IS NOT NULL AND pdf_path <> ''
       AND (statement IS NULL OR statement = '')"
)->fetchAll();

$upd = $pdo->prepare('UPDATE tasks SET statement = ? WHERE id = ?');
$filled = 0; $empty = 0; $missing = 0;

foreach ($rows as $r) {
    $file = UPLOAD_DIR . DIRECTORY_SEPARATOR . $r['pdf_path'];
    if (!is_file($file)) { $missing++; continue; }
    $cmd = escapeshellarg($pdftotext) . ' -q -enc UTF-8 -nopgbrk ' . escapeshellarg($file) . ' -';
    $raw = (string) shell_exec($cmd);
    $md = to_markdown($raw);
    // strip control chars (form feeds etc.) and require some real text
    $md = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $md);
    if (mb_strlen(trim($md)) < 20) { $empty++; continue; }   // scanned / no text layer
    $upd->execute([$md, (int) $r['id']]);
    $filled++;
}

out('=== DONE ===');
out("Statements filled from PDF: $filled | no usable text (scanned): $empty | PDF missing on disk: $missing");
