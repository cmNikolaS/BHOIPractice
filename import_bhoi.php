<?php
/**
 * import_bhoi.php  —  One-off importer for the official BHOI archive.
 * ----------------------------------------------------------------------
 * Pulls every problem from
 *   https://github.com/BHOI/BHOI-takmicenja-iz-informatike
 * and loads it into the platform:
 *   - statement Markdown  -> tasks.statement
 *   - statement.pdf       -> uploads/pdf  (downloadable)
 *   - sol/*.cpp etc.      -> uploads/solutions (preview + download)
 *   - task.yaml           -> title, time/memory limits
 *
 * The repo's "round" (bhoi / jbhoi / bhgoi / *kvalifikaciono) becomes the
 * competition level; the "category" (osnovne / srednje / kombinovano)
 * becomes a school-level tag.
 *
 * Run (PowerShell, against the preview DB on 3307):
 *     $env:DB_PORT="3307"; C:\xampp\php\php.exe import_bhoi.php
 *
 * It wipes the existing tasks first so the catalog reflects the archive.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('Run this from the command line.');
}

require __DIR__ . '/config.php';

const REPO_RAW = 'https://raw.githubusercontent.com/BHOI/BHOI-takmicenja-iz-informatike/master/';
const TREE_JSON = __DIR__ . '/_import/tree.json';

$CODE_EXT = ['cpp', 'cc', 'cxx', 'c', 'h', 'hpp', 'py', 'java', 'pas', 'kt'];

/* ---------------------------------------------------------------- helpers */

function out(string $msg): void { fwrite(STDOUT, $msg . PHP_EOL); }

function slugify(string $text): string {
    $map = ['č'=>'c','ć'=>'c','đ'=>'d','š'=>'s','ž'=>'z','Č'=>'c','Ć'=>'c','Đ'=>'d','Š'=>'s','Ž'=>'z'];
    $text = strtr($text, $map);
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^a-z0-9]+/u', '-', $text) ?? '';
    return trim($text, '-');
}

/** Fetch a URL (binary-safe). Returns null on any non-200. */
function fetch(string $url): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false, // pragmatic for a local one-off import
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT        => 90,
        CURLOPT_USERAGENT      => 'bhoi-importer/1.0',
    ]);
    for ($try = 1; $try <= 3; $try++) {
        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($data !== false && $code === 200) { curl_close($ch); return $data; }
        usleep(400000); // 0.4s backoff
    }
    curl_close($ch);
    return null;
}

function raw_url(string $path): string {
    $segs = array_map('rawurlencode', explode('/', $path));
    return REPO_RAW . implode('/', $segs);
}

function save_to_uploads(string $bytes, string $subdir, string $ext): string {
    $dir = UPLOAD_DIR . DIRECTORY_SEPARATOR . $subdir;
    if (!is_dir($dir)) { mkdir($dir, 0775, true); }
    $stored = bin2hex(random_bytes(16)) . '.' . strtolower($ext);
    file_put_contents($dir . DIRECTORY_SEPARATOR . $stored, $bytes);
    return $subdir . '/' . $stored;
}

/** Minimal "key: value" YAML reader (enough for task.yaml). */
function parse_yaml_simple(string $y): array {
    $out = [];
    foreach (preg_split('/\r?\n/', $y) as $line) {
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*):\s*(.*)$/', $line, $m)) {
            $out[$m[1]] = trim(trim($m[2]), "\"'");
        }
    }
    return $out;
}

function language_from_ext(string $ext): string {
    return match (strtolower($ext)) {
        'cpp','cc','cxx','c','h','hpp' => 'C++',
        'py'   => 'Python',
        'java' => 'Java',
        'pas'  => 'Pascal',
        'kt'   => 'Kotlin',
        default => 'Izvorni kod',
    };
}

function prettify_name(string $name): string {
    return ucfirst(str_replace(['_', '-'], ' ', $name));
}

/**
 * Rewrite repo-relative image references in a statement to absolute GitHub
 * raw URLs (so figures render in the browser). Handles ![alt](src) and
 * <img src="...">; absolute http(s) sources are left untouched.
 */
function rewrite_md_images(string $md, string $mdRepoPath): string {
    $absBase = raw_url(dirname($mdRepoPath)) . '/';
    $absolutize = function (string $src) use ($absBase): string {
        if (preg_match('#^https?://#i', $src)) return $src;
        $src = ltrim($src, './');
        return $absBase . implode('/', array_map('rawurlencode', explode('/', $src)));
    };
    // Markdown:  ![alt](src "title")
    $md = preg_replace_callback('/(!\[[^\]]*\]\()\s*([^)\s]+)([^)]*\))/', function ($m) use ($absolutize) {
        return $m[1] . $absolutize($m[2]) . $m[3];
    }, $md) ?? $md;
    // Raw HTML:  <img ... src="...">
    $md = preg_replace_callback('/(<img[^>]*\ssrc=")([^"]+)(")/i', function ($m) use ($absolutize) {
        return $m[1] . $absolutize($m[2]) . $m[3];
    }, $md) ?? $md;
    return $md;
}

/** Round -> [level name, slug, sort_order]. */
function level_for_round(string $round): array {
    if (str_starts_with($round, 'bhoi')) return ['Državno (BHOI)', 'drzavno-bhoi', 40];
    if ($round === 'jbhoi')              return ['Juniorsko (jBHOI)', 'jbhoi', 30];
    if ($round === 'bhgoi')              return ['BHGOI', 'bhgoi', 20];
    return ['Kvalifikacije', 'kvalifikacije', 10]; // all *kvalifikaciono / kvalifikacije
}

/** Round -> initial 1–10 rating (heuristic: finals hardest, qualifiers easiest).
 *  Just a starting point; classify_tasks.php sets a curated per-task rating. */
function rating_for_round(string $round): int {
    if (str_starts_with($round, 'bhoi')) return 7;        // Državno (BHOI) finals
    if ($round === 'jbhoi' || $round === 'bhgoi') return 5;
    return 3;                                             // qualifiers
}

/** 1–10 rating -> band label (mirrors difficulty_band() in bootstrap.php). */
function band_for_rating(int $rating): string {
    if ($rating <= 3) return 'Lako';
    if ($rating <= 6) return 'Srednje';
    return 'Teško';
}

/** Category -> [tag name, slug]. */
function category_tag(string $cat): ?array {
    return match ($cat) {
        'osnovne'     => ['Osnovna škola', 'osnovna-skola'],
        'srednje'     => ['Srednja škola', 'srednja-skola'],
        'kombinovano' => ['Kombinovano', 'kombinovano'],
        default       => null,
    };
}

/* ------------------------------------------------------------------- main */

$pdo = db();

// The git tree is git-ignored, so fetch it from the GitHub API on demand.
// This keeps the importer self-contained on a fresh clone.
if (!is_file(TREE_JSON)) {
    out('Fetching repo file tree from the GitHub API ...');
    @mkdir(dirname(TREE_JSON), 0775, true);
    $api = fetch('https://api.github.com/repos/BHOI/BHOI-takmicenja-iz-informatike/git/trees/master?recursive=1');
    if ($api === null) {
        exit("Could not fetch the repo tree (network/GitHub API issue). Try again.\n");
    }
    file_put_contents(TREE_JSON, $api);
}
$tree = json_decode((string) file_get_contents(TREE_JSON), true);
if (!isset($tree['tree'])) {
    exit("tree.json has no 'tree' key.\n");
}

// Index every blob path for fast lookups.
$paths = [];
foreach ($tree['tree'] as $node) {
    if (($node['type'] ?? '') === 'blob') {
        $paths[$node['path']] = true;
    }
}

// Problem dirs = the parent dir of each task.yaml.
$problemDirs = [];
foreach ($paths as $p => $_) {
    if (str_ends_with($p, '/task.yaml')) {
        $problemDirs[] = substr($p, 0, -strlen('/task.yaml'));
    }
}
sort($problemDirs);
out('Found ' . count($problemDirs) . ' problems.');

// Optional cap (handy for testing): IMPORT_LIMIT=5
$limit = (int) (getenv('IMPORT_LIMIT') ?: 0);
if ($limit > 0) {
    $problemDirs = array_slice($problemDirs, 0, $limit);
    out('IMPORT_LIMIT active: importing only ' . $limit . ' problems.');
}

/* --- reset levels + clean tasks, ensure tags ----------------------- */
out('Resetting catalog (levels + tasks) ...');
// Delete child -> parent so foreign keys stay satisfied (no need to disable them).
$pdo->exec('DELETE FROM solutions');
$pdo->exec('DELETE FROM task_tags');
$pdo->exec('DELETE FROM tasks');
$pdo->exec('DELETE FROM levels');

$levelId = [];   // slug -> id
function ensure_level(PDO $pdo, array &$cache, string $name, string $slug, int $sort): int {
    if (isset($cache[$slug])) return $cache[$slug];
    $s = $pdo->prepare('SELECT id FROM levels WHERE slug = ?');
    $s->execute([$slug]);
    $id = (int) $s->fetchColumn();
    if ($id === 0) {
        $pdo->prepare('INSERT INTO levels (name, slug, sort_order) VALUES (?,?,?)')->execute([$name, $slug, $sort]);
        $id = (int) $pdo->lastInsertId();
    }
    return $cache[$slug] = $id;
}

$tagId = [];     // slug -> id
function ensure_tag(PDO $pdo, array &$cache, string $name, string $slug): int {
    if (isset($cache[$slug])) return $cache[$slug];
    $s = $pdo->prepare('SELECT id FROM tags WHERE slug = ?'); $s->execute([$slug]);
    $id = (int) $s->fetchColumn();
    if ($id === 0) {
        $pdo->prepare('INSERT INTO tags (name, slug) VALUES (?,?)')->execute([$name, $slug]);
        $id = (int) $pdo->lastInsertId();
    }
    return $cache[$slug] = $id;
}

/* --- import loop --------------------------------------------------- */
$insTask = $pdo->prepare(
    'INSERT INTO tasks (title, slug, statement, year, level_id, difficulty, difficulty_rating, problem_index, time_limit_ms, memory_limit_mb, pdf_path)
     VALUES (?,?,?,?,?,?,?,?,?,?,?)'
);
$ignorePrefix = DB_DRIVER === 'sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
$insTag = $pdo->prepare($ignorePrefix . ' INTO task_tags (task_id, tag_id) VALUES (?,?)');
$insSol = $pdo->prepare('INSERT INTO solutions (task_id, language, original_name, file_path, file_size) VALUES (?,?,?,?,?)');

$usedSlugs = [];
$stats = ['tasks' => 0, 'pdf' => 0, 'sol' => 0, 'md' => 0, 'skipped' => 0];
$i = 0;

foreach ($problemDirs as $dir) {
    $i++;
    $seg   = explode('/', $dir);
    $year  = (int) $seg[0];
    $cat   = $seg[1] ?? '';
    $round = $seg[2] ?? '';
    $name  = $seg[count($seg) - 1];

    $prefix = sprintf('[%02d/%d] %s', $i, count($problemDirs), $dir);

    // task.yaml -> metadata
    $yamlRaw = fetch(raw_url($dir . '/task.yaml'));
    $meta = $yamlRaw ? parse_yaml_simple($yamlRaw) : [];
    $title = $meta['title'] ?? '';
    if ($title === '') { $title = prettify_name($name); }
    $timeMs = isset($meta['time_limit']) && is_numeric($meta['time_limit']) ? (int) round(((float) $meta['time_limit']) * 1000) : null;
    $memMb  = isset($meta['memory_limit']) && is_numeric($meta['memory_limit']) ? (int) $meta['memory_limit'] : null;

    // statement markdown (first .md under statement/, preferring the named one)
    $statement = '';
    $mdCandidates = [];
    foreach ($paths as $p => $_) {
        if (str_starts_with($p, $dir . '/statement/') && str_ends_with(strtolower($p), '.md')) {
            $mdCandidates[] = $p;
        }
    }
    usort($mdCandidates, function ($a, $b) use ($name) {
        $an = stripos(basename($a), $name) !== false ? 0 : 1;
        $bn = stripos(basename($b), $name) !== false ? 0 : 1;
        return $an <=> $bn;
    });
    if ($mdCandidates) {
        $md = fetch(raw_url($mdCandidates[0]));
        if ($md !== null) { $statement = rewrite_md_images($md, $mdCandidates[0]); $stats['md']++; }
    }

    // statement.pdf (or any pdf under statement/)
    $pdfPath = null;
    $pdfSrc = null;
    if (isset($paths[$dir . '/statement/statement.pdf'])) {
        $pdfSrc = $dir . '/statement/statement.pdf';
    } else {
        foreach ($paths as $p => $_) {
            if (str_starts_with($p, $dir . '/statement/') && str_ends_with(strtolower($p), '.pdf')) { $pdfSrc = $p; break; }
        }
    }
    if ($pdfSrc) {
        $bytes = fetch(raw_url($pdfSrc));
        if ($bytes !== null && str_starts_with($bytes, '%PDF')) {
            $pdfPath = save_to_uploads($bytes, 'pdf', 'pdf');
            $stats['pdf']++;
        }
    }

    // solutions: code files under sol/
    $solFiles = [];
    foreach ($paths as $p => $_) {
        if (str_starts_with($p, $dir . '/sol/')) {
            $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
            if (in_array($ext, $GLOBALS['CODE_EXT'], true)) { $solFiles[] = $p; }
        }
    }
    // main.cpp first, then alphabetical
    usort($solFiles, function ($a, $b) {
        $am = str_contains(strtolower(basename($a)), 'main') ? 0 : 1;
        $bm = str_contains(strtolower(basename($b)), 'main') ? 0 : 1;
        return [$am, $a] <=> [$bm, $b];
    });

    // level + tags
    [$lvlName, $lvlSlug, $lvlSort] = level_for_round($round);
    $lid = ensure_level($pdo, $levelId, $lvlName, $lvlSlug, $lvlSort);
    $rating = rating_for_round($round);
    $difficulty = band_for_rating($rating);

    // unique slug
    $base = slugify($name . '-' . $round . '-' . $year) ?: ('zadatak-' . $year . '-' . $i);
    $slug = $base; $n = 2;
    while (isset($usedSlugs[$slug])) { $slug = $base . '-' . $n++; }
    $usedSlugs[$slug] = true;

    // insert task
    $insTask->execute([$title, $slug, $statement, $year, $lid, $difficulty, $rating, null, $timeMs, $memMb, $pdfPath]);
    $taskId = (int) $pdo->lastInsertId();
    $stats['tasks']++;

    // category tag
    if ($ct = category_tag($cat)) {
        $tid = ensure_tag($pdo, $tagId, $ct[0], $ct[1]);
        $insTag->execute([$taskId, $tid]);
    }

    // solutions
    $solCount = 0;
    foreach ($solFiles as $sp) {
        $bytes = fetch(raw_url($sp));
        if ($bytes === null) { continue; }
        $ext = strtolower(pathinfo($sp, PATHINFO_EXTENSION));
        $stored = save_to_uploads($bytes, 'solutions', $ext);
        $insSol->execute([$taskId, language_from_ext($ext), basename($sp), $stored, strlen($bytes)]);
        $solCount++; $stats['sol']++;
    }

    out(sprintf('%s  -> "%s" [%s %d] pdf:%s sol:%d', $prefix, $title, $lvlSlug, $year, $pdfPath ? 'yes' : 'no', $solCount));
}

out('');
out('=== DONE ===');
out(sprintf('Tasks: %d | PDFs: %d | Solutions: %d | Statements: %d', $stats['tasks'], $stats['pdf'], $stats['sol'], $stats['md']));
