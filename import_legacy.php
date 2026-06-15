<?php
/**
 * import_legacy.php — Importer for the legacy BHOI years (2003–2021).
 * ----------------------------------------------------------------------
 * The modern importer (import_bhoi.php) only handles the task.yaml-based
 * layout used since 2022. The older years are a pile of loose files
 * (statement PDFs, scanned images, stray .cpp/.pas solutions) with no
 * consistent structure. This script groups them into tasks heuristically:
 *
 *   - a problem ≈ a statement PDF (preferred), or, when a folder has no
 *     PDF, the scanned statement images (embedded as Markdown);
 *   - matching .cpp/.c/.pas/… in the same folder become solutions;
 *   - language-duplicate PDFs ("(bs)"/"(it)") are merged (Bosnian wins);
 *   - editorial/result/scoreboard files are skipped.
 *
 * It is ADDITIVE and idempotent: it never wipes existing tasks and skips
 * any slug that already exists, so it is safe alongside import_bhoi.php and
 * safe to re-run. Difficulty is a heuristic rating by round (no algorithm
 * tags — those would require reading every PDF); refine in the admin panel.
 *
 * Test cases (input/output archives) are intentionally NOT imported.
 *
 * Dry run (prints the plan, downloads nothing):
 *     $env:LEGACY_DRY="1"; C:\xampp\php\php.exe import_legacy.php
 * Real run against the preview DB:
 *     $env:DB_PORT="3307"; C:\xampp\php\php.exe import_legacy.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit('Run this from the command line.'); }

require __DIR__ . '/config.php';

const REPO_RAW  = 'https://raw.githubusercontent.com/BHOI/BHOI-takmicenja-iz-informatike/master/';
const TREE_JSON = __DIR__ . '/_import/tree.json';

$DRY = (bool) (getenv('LEGACY_DRY') ?: 0);

$STMT_EXT = ['pdf'];                 // docx/md don't render inline; pdf siblings exist where needed
$CODE_EXT = ['cpp','cc','cxx','c','h','hpp','pas','py','java','kt'];
$IMG_EXT  = ['jpg','jpeg','png'];
// statement files we never treat as problems (editorials, results, scoreboards…)
$EXCLUDE  = '/(rezultat|zbirni|scoreboard|results|napomena|^readme|find_executables|rjesenj|rješenj|solution|tekstovi\s+zadataka)/iu';
// images that are figures inside a statement, not statements themselves
$FIGURE   = '/(slika|slike|^fig|^image|tastatura|primjer|graf\b|crtez|izgled|thumbnail|pimgpsh|naslovna)/iu';

function out(string $m): void { fwrite(STDOUT, $m . PHP_EOL); }

function slugify(string $text): string {
    $map = ['č'=>'c','ć'=>'c','đ'=>'d','š'=>'s','ž'=>'z','Č'=>'c','Ć'=>'c','Đ'=>'d','Š'=>'s','Ž'=>'z'];
    $text = strtr($text, $map);
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^a-z0-9]+/u', '-', $text) ?? '';
    return trim($text, '-');
}
function raw_url(string $path): string {
    return REPO_RAW . implode('/', array_map('rawurlencode', explode('/', $path)));
}
function fetch(string $url): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false, CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => 120, CURLOPT_USERAGENT => 'bhoi-legacy-importer/1.0',
    ]);
    for ($try = 1; $try <= 3; $try++) {
        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($data !== false && $code === 200) { curl_close($ch); return $data; }
        usleep(400000);
    }
    curl_close($ch);
    return null;
}
function save_to_uploads(string $bytes, string $subdir, string $ext): string {
    $dir = UPLOAD_DIR . DIRECTORY_SEPARATOR . $subdir;
    if (!is_dir($dir)) { mkdir($dir, 0775, true); }
    $stored = bin2hex(random_bytes(16)) . '.' . strtolower($ext);
    file_put_contents($dir . DIRECTORY_SEPARATOR . $stored, $bytes);
    return $subdir . '/' . $stored;
}
function language_from_ext(string $ext): string {
    return match (strtolower($ext)) {
        'cpp','cc','cxx','c','h','hpp' => 'C++', 'py'=>'Python',
        'java'=>'Java', 'pas'=>'Pascal', 'kt'=>'Kotlin', default=>'Izvorni kod',
    };
}
function level_for(string $p): array {
    $l = strtolower($p);
    if (strpos($l,'jbhoi')!==false || strpos($l,'jboi')!==false) return ['Juniorsko (jBHOI)','jbhoi',30];
    if (strpos($l,'bhgoi')!==false) return ['BHGOI','bhgoi',20];
    if (preg_match('#/kvalifik|online|runda|probno|kvalifikaciono#i',$l)) return ['Kvalifikacije','kvalifikacije',10];
    return ['Državno (BHOI)','drzavno-bhoi',40]; // finals / early years (main national comp)
}
function rating_for(string $slug): int {
    return match ($slug) { 'drzavno-bhoi'=>7, 'jbhoi','bhgoi'=>5, default=>3 };
}
function band_for(int $r): string { return $r<=3 ? 'Lako' : ($r<=6 ? 'Srednje' : 'Teško'); }
function category_tag(string $cat): ?array {
    return match ($cat) {
        'osnovne'=>['Osnovna škola','osnovna-skola'],
        'srednje'=>['Srednja škola','srednja-skola'],
        'kombinovano'=>['Kombinovano','kombinovano'], default=>null,
    };
}
function cat_of(string $p): string {
    $l = strtolower($p);
    if (strpos($l,'osnovne')!==false||strpos($l,'osnovci')!==false) return 'osnovne';
    if (strpos($l,'srednje')!==false||strpos($l,'srednjos')!==false) return 'srednje';
    if (strpos($l,'kombinovano')!==false) return 'kombinovano';
    return '';
}
function norm_base(string $name): string {
    $b = preg_replace('/\.[A-Za-z0-9]+$/','',$name);
    $b = preg_replace('/\s*\((it|bs|en|hr|eng)\)\s*/i','',$b);
    $b = preg_replace('/_testPrimjeri$/i','',$b);
    $b = preg_replace('/\s+tekst$/iu','',$b);
    return trim($b);
}
function title_from(string $base): string {
    $b = preg_replace('/[_\-]+/',' ',$base);
    $b = preg_replace('/\s+/',' ',trim($b));
    return $b === '' ? 'Bez naziva' : mb_convert_case($b, MB_CASE_TITLE, 'UTF-8');
}
/** "word-like" = has a real letter run, not a hash/numeric photo filename. */
function wordlike(string $base): bool {
    if (!preg_match('/[A-Za-zČĆĐŠŽčćđšž]{3,}/u', $base)) return false;
    $letters = preg_match_all('/[A-Za-zČĆĐŠŽčćđšž]/u', $base);
    $digits  = preg_match_all('/[0-9]/', $base);
    return $letters >= $digits;
}

/* ---------------------------------------------------------------- collect */
$tree = json_decode((string) file_get_contents(TREE_JSON), true);
$paths = [];
foreach ($tree['tree'] as $n) { if (($n['type'] ?? '') === 'blob') $paths[] = $n['path']; }
$legacy = array_values(array_filter($paths, fn($p) => (($y=(int)explode('/',$p)[0]) >= 2003 && $y <= 2021)));

$problems = []; // key => assoc
$rank = ['pdf'=>3];
foreach ($legacy as $p) {
    $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
    if (!in_array($ext, $STMT_EXT, true)) continue;
    $name = basename($p);
    if (preg_match($EXCLUDE, $name)) continue;
    $base = norm_base($name);
    if ($base === '') continue;
    $dir = dirname($p);
    $key = $dir . '|' . mb_strtolower($base, 'UTF-8');
    $isBs = (bool) preg_match('/\(bs\)/i', $name);
    if (!isset($problems[$key])) {
        [$ln,$ls,$lo] = level_for($p);
        $problems[$key] = ['year'=>(int)explode('/',$p)[0],'dir'=>$dir,'base'=>$base,'stmt'=>$p,
            'type'=>'pdf','bs'=>$isBs,'lvl'=>[$ln,$ls,$lo],'cat'=>cat_of($p),'sols'=>[],'imgs'=>[]];
    } elseif ($isBs && !$problems[$key]['bs']) {
        $problems[$key]['stmt']=$p; $problems[$key]['bs']=true;   // prefer Bosnian variant
    }
}
$dirsWithStmt = [];
foreach ($problems as $pr) { $dirsWithStmt[$pr['dir']] = true; }

/* image statements (only in dirs with NO pdf) */
$wordImg = []; $blobImg = [];
foreach ($legacy as $p) {
    $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
    if (!in_array($ext, $IMG_EXT, true)) continue;
    $name = basename($p);
    if (preg_match($EXCLUDE, $name) || preg_match($FIGURE, $name)) continue;
    $dir = dirname($p);
    if (isset($dirsWithStmt[$dir])) continue;
    $base = preg_replace('/[_\-\s]*\d+$/','', norm_base($name));
    if (preg_match('/^(ranking|scoreboard|poredak|rezultati?)$/i', $base)) continue; // scoreboards
    if (wordlike($base)) { $wordImg[$dir.'|'.mb_strtolower($base,'UTF-8')][] = $p; }
    else                 { $blobImg[$dir][] = $p; }
}
foreach ($wordImg as $key => $imgs) {
    sort($imgs); $p=$imgs[0]; [$ln,$ls,$lo]=level_for($p);
    $base = preg_replace('/[_\-\s]*\d+$/','', norm_base(basename($p)));
    $problems[$key] = ['year'=>(int)explode('/',$p)[0],'dir'=>dirname($p),'base'=>$base,'stmt'=>null,
        'type'=>'img','bs'=>false,'lvl'=>[$ln,$ls,$lo],'cat'=>cat_of($p),'sols'=>[],'imgs'=>$imgs];
}
foreach ($blobImg as $dir => $imgs) {           // scanned problems: title from the folder name
    sort($imgs); $p=$imgs[0]; [$ln,$ls,$lo]=level_for($p); $y=(int)explode('/',$p)[0]; $c=cat_of($p);
    $leaf = preg_replace('/^bhoi\s*\d{4}\s*[-–]?\s*/i','', basename($dir));
    $leaf = trim(preg_replace('/\b\d{4}\b/','',$leaf));
    $stop = '/^(srednje|osnovne|kombinovano|dan\s*\d|runda\s*\d|bhoi|probno)$/i';
    $title = (wordlike($leaf) && !preg_match($stop,$leaf))
        ? title_from($leaf)
        : 'BHOI ' . $y . ($c ? " ($c)" : '') . ' – skenirani tekstovi';
    $problems[$dir.'|BLOB'] = ['year'=>$y,'dir'=>$dir,'base'=>$title,'stmt'=>null,'type'=>'img',
        'bs'=>false,'lvl'=>[$ln,$ls,$lo],'cat'=>$c,'sols'=>[],'imgs'=>$imgs,'rawtitle'=>true];
}

/* attach solutions */
$codeByDir = [];
foreach ($legacy as $p) {
    $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
    if (!in_array($ext, $CODE_EXT, true)) continue;
    if (preg_match('/grader|generator|template|^lib\.h$/i', basename($p))) continue;
    $codeByDir[dirname($p)][] = $p;
}
$probPerDir = [];
foreach ($problems as $pr) { $probPerDir[$pr['dir']] = ($probPerDir[$pr['dir']]??0)+1; }
foreach ($problems as $key => $pr) {
    if ($pr['type'] === 'img' && isset($pr['rawtitle'])) continue; // combined scans: no code matching
    $base = mb_strtolower($pr['base'],'UTF-8'); $codes = $codeByDir[$pr['dir']] ?? []; $m = [];
    foreach ($codes as $c) { if (mb_strtolower(norm_base(basename($c)),'UTF-8') === $base) $m[] = $c; }
    if (!$m && ($probPerDir[$pr['dir']] ?? 0) === 1) { $m = $codes; }
    $problems[$key]['sols'] = $m;
}

usort($problems, fn($a,$b) => [$a['year'],$a['lvl'][1],mb_strtolower($a['base'],'UTF-8')]
                            <=> [$b['year'],$b['lvl'][1],mb_strtolower($b['base'],'UTF-8')]);

/* ---------------------------------------------------------------- apply */
$pdo = $DRY ? null : db();
$levelCache = []; $tagCache = [];
function ensure_level(PDO $pdo, array &$c, string $n, string $s, int $o): int {
    if (isset($c[$s])) return $c[$s];
    $q=$pdo->prepare('SELECT id FROM levels WHERE slug=?'); $q->execute([$s]); $id=(int)$q->fetchColumn();
    if ($id===0){ $pdo->prepare('INSERT INTO levels (name,slug,sort_order) VALUES (?,?,?)')->execute([$n,$s,$o]); $id=(int)$pdo->lastInsertId(); }
    return $c[$s]=$id;
}
function ensure_tag(PDO $pdo, array &$c, string $n, string $s): int {
    if (isset($c[$s])) return $c[$s];
    $q=$pdo->prepare('SELECT id FROM tags WHERE slug=?'); $q->execute([$s]); $id=(int)$q->fetchColumn();
    if ($id===0){ $pdo->prepare('INSERT INTO tags (name,slug) VALUES (?,?)')->execute([$n,$s]); $id=(int)$pdo->lastInsertId(); }
    return $c[$s]=$id;
}

if (!$DRY) {
    $insTask = $pdo->prepare('INSERT INTO tasks (title,slug,statement,year,level_id,difficulty,difficulty_rating,pdf_path) VALUES (?,?,?,?,?,?,?,?)');
    $insTag  = $pdo->prepare('INSERT INTO task_tags (task_id,tag_id) VALUES (?,?)');
    $insSol  = $pdo->prepare('INSERT INTO solutions (task_id,language,original_name,file_path,file_size) VALUES (?,?,?,?,?)');
    $hasSlug = $pdo->prepare('SELECT 1 FROM tasks WHERE slug=? LIMIT 1');
}

$stats = ['added'=>0,'skipped_existing'=>0,'pdf'=>0,'img'=>0,'sol'=>0,'nosol'=>[],'list'=>[]];
$usedSlugs = [];
$i = 0; $n = count($problems);
foreach ($problems as $pr) {
    $i++;
    [$ln,$ls,$lo] = $pr['lvl'];
    $title = isset($pr['rawtitle']) ? $pr['base'] : title_from($pr['base']);
    $rating = rating_for($ls); $band = band_for($rating);
    $base = slugify($title . '-' . $ls . '-' . $pr['year']) ?: ('zadatak-'.$pr['year'].'-'.$i);
    $slug = $base; $k=2; while (isset($usedSlugs[$slug])) { $slug = $base.'-'.$k++; }

    $line = sprintf('%d | %-13s | %-11s | %-30s | %s | sol:%d',
        $pr['year'], $ls, $pr['cat']?:'-', $title, $pr['type'], count($pr['sols']));
    $stats['list'][] = $line;
    if (!$pr['sols']) $stats['nosol'][] = sprintf('%d %-13s %s', $pr['year'], $ls, $title);

    if ($DRY) { $usedSlugs[$slug]=true; if($pr['type']==='pdf')$stats['pdf']++; else $stats['img']++; $stats['added']++; continue; }

    $hasSlug->execute([$slug]);
    if ($hasSlug->fetch()) { $stats['skipped_existing']++; $usedSlugs[$slug]=true; continue; }

    // statement / pdf
    $statement = ''; $pdfPath = null;
    if ($pr['type'] === 'pdf') {
        $bytes = fetch(raw_url($pr['stmt']));
        if ($bytes !== null && str_starts_with($bytes, '%PDF')) { $pdfPath = save_to_uploads($bytes,'pdf','pdf'); $stats['pdf']++; }
    } else { // images embedded as markdown
        $parts = ["*Tekst zadatka (skenirano):*", ''];
        $pg = 1;
        foreach ($pr['imgs'] as $img) { $parts[] = '![stranica '.$pg++.']('.raw_url($img).')'; $parts[] = ''; }
        $statement = implode("\n", $parts); $stats['img']++;
    }

    $lid = ensure_level($pdo, $levelCache, $ln, $ls, $lo);
    $usedSlugs[$slug] = true;
    $insTask->execute([$title,$slug,$statement,$pr['year'],$lid,$band,$rating,$pdfPath]);
    $taskId = (int) $pdo->lastInsertId();
    $stats['added']++;

    if ($ct = category_tag($pr['cat'])) { $tid = ensure_tag($pdo,$tagCache,$ct[0],$ct[1]); $insTag->execute([$taskId,$tid]); }

    foreach ($pr['sols'] as $sp) {
        $bytes = fetch(raw_url($sp)); if ($bytes === null) continue;
        $ext = strtolower(pathinfo($sp, PATHINFO_EXTENSION));
        $stored = save_to_uploads($bytes,'solutions',$ext);
        $insSol->execute([$taskId, language_from_ext($ext), basename($sp), $stored, strlen($bytes)]);
        $stats['sol']++;
    }
    if (($i % 20) === 0) out("  ... $i/$n");
}

/* ---------------------------------------------------------------- report */
out('');
out($DRY ? '=== DRY RUN (nothing written) ===' : '=== DONE ===');
out(sprintf('Problems: %d | added: %d | skipped (already in DB): %d | pdf: %d | img: %d | solutions: %d | without solution: %d',
    $n, $stats['added'], $stats['skipped_existing'], $stats['pdf'], $stats['img'], $stats['sol'], count($stats['nosol'])));
out('');
out('--- FULL LIST (year | level | category | title | statement | #sol) ---');
foreach ($stats['list'] as $l) out($l);
out('');
out('--- WITHOUT SOLUTION ('.count($stats['nosol']).') ---');
foreach ($stats['nosol'] as $l) out('  '.$l);
