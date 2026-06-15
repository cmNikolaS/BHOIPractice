<?php
/**
 * classify_legacy.php — Curated difficulty + algorithm categories for the
 * legacy years (2003–2021), based on reading each problem's statement text
 * (extracted from the PDFs) and, for scanned statements, the solution code.
 *
 * Like classify_tasks.php but for the import_legacy.php tasks. Sets a custom
 * 1–10 difficulty_rating (my per-task estimate) + derived band, and the
 * algorithm tags. Idempotent & additive: matches by slug, preserves the
 * school-category tags, replaces only algorithm tags. Tasks not listed here
 * (unreadable scanned statements) keep their heuristic difficulty and stay
 * uncategorised. Also removes a stray scoreboard wrongly imported as a task.
 *
 *   $env:DB_PORT="3307"; C:\xampp\php\php.exe classify_legacy.php
 *   DB_DRIVER=sqlite DB_SQLITE_PATH=/data/app.sqlite php classify_legacy.php
 */

declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit('Run this from the command line.'); }
require __DIR__ . '/config.php';

function out(string $m): void { fwrite(STDOUT, $m . PHP_EOL); }
function band_for(int $r): string { return $r <= 3 ? 'Lako' : ($r <= 6 ? 'Srednje' : 'Teško'); }

$ALG_TAGS = [
    'dp'=>'Dinamičko programiranje','greedy'=>'Pohlepni algoritmi','grafovi'=>'Grafovi',
    'matematika'=>'Matematika','sortiranje'=>'Sortiranje','pretraga'=>'Pretraga',
    'stringovi'=>'Stringovi','strukture-podataka'=>'Strukture podataka','geometrija'=>'Geometrija',
    'implementacija'=>'Implementacija','teorija-brojeva'=>'Teorija brojeva','rekurzija'=>'Rekurzija',
];

// Non-problems imported by mistake (scoreboards) -> delete.
$REMOVE = ['ranking-drzavno-bhoi-2017'];

// slug => [rating 1-10, [algorithm tag slugs]]
$CLASSIFY = [
    // ---- 2003 ----
    'bicikl-drzavno-bhoi-2003'=>[4,['dp']],
    'kablovi-drzavno-bhoi-2003'=>[5,['grafovi']],
    'obrnuti-drzavno-bhoi-2003'=>[2,['implementacija']],
    'string-drzavno-bhoi-2003'=>[6,['dp','stringovi']],
    'zion-drzavno-bhoi-2003'=>[6,['dp','greedy']],
    // ---- 2004 ----
    'bioritam-drzavno-bhoi-2004'=>[4,['matematika']],
    'hipodrom-drzavno-bhoi-2004'=>[5,['grafovi']],
    'mars-drzavno-bhoi-2004'=>[4,['greedy']],
    // ---- 2005 ----
    'sedlo-drzavno-bhoi-2005'=>[2,['implementacija']],
    'skakac-drzavno-bhoi-2005'=>[4,['dp']],
    'zamak-drzavno-bhoi-2005'=>[5,['grafovi','implementacija']],
    'zgrada-drzavno-bhoi-2005'=>[2,['implementacija']],
    // ---- 2006 ----
    'cbr-drzavno-bhoi-2006'=>[5,['greedy']],
    'nx-drzavno-bhoi-2006'=>[5,['matematika','teorija-brojeva']],
    'sudoku-drzavno-bhoi-2006'=>[6,['pretraga','implementacija']],
    // ---- 2007 ----
    'egipat-drzavno-bhoi-2007'=>[7,['dp','stringovi']],
    'koder-drzavno-bhoi-2007'=>[6,['matematika']],
    'traka-drzavno-bhoi-2007'=>[5,['grafovi']],
    // ---- 2008 ----
    'broj-drzavno-bhoi-2008'=>[2,['teorija-brojeva','matematika']],
    'formula-drzavno-bhoi-2008'=>[5,['matematika']],
    'grafika-drzavno-bhoi-2008'=>[2,['implementacija']],
    'obilazak-drzavno-bhoi-2008'=>[5,['grafovi']],
    'rijec-drzavno-bhoi-2008'=>[3,['stringovi','implementacija']],
    // ---- 2009 ----
    'dnk-drzavno-bhoi-2009'=>[6,['dp','stringovi']],
    'kantor-drzavno-bhoi-2009'=>[4,['matematika']],
    'mreze-drzavno-bhoi-2009'=>[5,['implementacija']],
    'obim-drzavno-bhoi-2009'=>[4,['matematika']],
    'palacinke-drzavno-bhoi-2009'=>[5,['greedy','sortiranje']],
    'zohari-drzavno-bhoi-2009'=>[5,['matematika','implementacija']],
    'cifre-jbhoi-2009'=>[3,['implementacija']],
    'prenos-jbhoi-2009'=>[2,['implementacija']],
    'tablica-jbhoi-2009'=>[3,['implementacija']],
    // ---- 2010 ----
    'baudot-drzavno-bhoi-2010'=>[4,['implementacija','stringovi']],
    'bih-drzavno-bhoi-2010'=>[3,['implementacija']],
    'energija-drzavno-bhoi-2010'=>[4,['greedy','sortiranje']],
    'filter-drzavno-bhoi-2010'=>[3,['implementacija','stringovi']],
    'kamen-drzavno-bhoi-2010'=>[6,['grafovi']],
    'spijunaza-drzavno-bhoi-2010'=>[5,['grafovi']],
    // ---- 2011 ----
    'carobnjak-drzavno-bhoi-2011'=>[4,['implementacija']],
    'faktori-drzavno-bhoi-2011'=>[4,['teorija-brojeva','matematika']],
    'monitor-drzavno-bhoi-2011'=>[4,['matematika','implementacija']],
    'mypaint-drzavno-bhoi-2011'=>[4,['implementacija']],
    'olimp-drzavno-bhoi-2011'=>[3,['strukture-podataka']],
    'rentacar-drzavno-bhoi-2011'=>[6,['dp','sortiranje']],
    'sajam-drzavno-bhoi-2011'=>[5,['stringovi']],
    'zbir-drzavno-bhoi-2011'=>[2,['matematika']],
    // ---- 2012 ----
    'bradonja-drzavno-bhoi-2012'=>[6,['geometrija']],
    'brojevni-drzavno-bhoi-2012'=>[4,['implementacija','matematika']],
    'cifre-drzavno-bhoi-2012'=>[5,['greedy','implementacija']],
    'decode-drzavno-bhoi-2012'=>[5,['stringovi','implementacija']],
    'dnk-drzavno-bhoi-2012'=>[4,['sortiranje']],
    'domine-drzavno-bhoi-2012'=>[5,['strukture-podataka']],
    'dosada-drzavno-bhoi-2012'=>[3,['sortiranje']],
    'fontovi-drzavno-bhoi-2012'=>[2,['implementacija']],
    'fraktali-drzavno-bhoi-2012'=>[5,['rekurzija','implementacija']],
    'igra-drzavno-bhoi-2012'=>[5,['matematika']],
    'imenik-drzavno-bhoi-2012'=>[6,['stringovi','implementacija']],
    'jedinice-drzavno-bhoi-2012'=>[5,['teorija-brojeva','matematika']],
    'komsinice-drzavno-bhoi-2012'=>[6,['grafovi','stringovi']],
    'kopiranje-drzavno-bhoi-2012'=>[6,['pretraga','greedy']],
    'lis-drzavno-bhoi-2012'=>[6,['dp']],
    'misevi-drzavno-bhoi-2012'=>[7,['grafovi','implementacija']],
    'muznja-drzavno-bhoi-2012'=>[4,['sortiranje','greedy']],
    'omotac-drzavno-bhoi-2012'=>[6,['geometrija']],
    'prava-drzavno-bhoi-2012'=>[6,['geometrija']],
    'quest-drzavno-bhoi-2012'=>[6,['strukture-podataka']],
    'regex-drzavno-bhoi-2012'=>[7,['stringovi','dp']],
    'relprost-drzavno-bhoi-2012'=>[5,['teorija-brojeva','matematika']],
    'rijeci1-drzavno-bhoi-2012'=>[5,['dp','stringovi']],
    'rode-drzavno-bhoi-2012'=>[5,['strukture-podataka']],
    'space-drzavno-bhoi-2012'=>[8,['geometrija']],
    'spirala-drzavno-bhoi-2012'=>[5,['matematika','implementacija']],
    'sretni-drzavno-bhoi-2012'=>[3,['matematika']],
    'string-drzavno-bhoi-2012'=>[4,['stringovi']],
    'swat-drzavno-bhoi-2012'=>[5,['grafovi']],
    'takmicenje-drzavno-bhoi-2012'=>[4,['implementacija']],
    'takmicenje2-drzavno-bhoi-2012'=>[5,['implementacija']],
    'veeeeliki-drzavno-bhoi-2012'=>[6,['matematika','teorija-brojeva']],
    // ---- 2013 ----
    'identicna-jaja-drzavno-bhoi-2013'=>[7,['dp']],
    'intergalakticka-pobuna-drzavno-bhoi-2013'=>[7,['stringovi']],
    'nepoznati-znakovi-drzavno-bhoi-2013'=>[6,['pretraga','dp']],
    'opasni-rudnici-drzavno-bhoi-2013'=>[5,['dp']],
    'povezivanje-gradova-drzavno-bhoi-2013'=>[7,['dp','geometrija']],
    'puni-kvadrat-drzavno-bhoi-2013'=>[2,['matematika']],
    'sifrirana-poruka-drzavno-bhoi-2013'=>[5,['implementacija','stringovi']],
    'zadatak-tri-drzavno-bhoi-2013'=>[4,['matematika']],
    'aritmeticki-izraz-kvalifikacije-2013'=>[6,['implementacija','stringovi']],
    'igra-pogadjanja-kvalifikacije-2013'=>[3,['pretraga']],
    // ---- 2014 ----
    'cirkularna-genomika-drzavno-bhoi-2014'=>[6,['stringovi']],
    'mit-drzavno-bhoi-2014'=>[6,['dp','stringovi']],
    'piramida-drzavno-bhoi-2014'=>[6,['dp']],
    'poligon-drzavno-bhoi-2014'=>[6,['geometrija','implementacija']],
    'roboti-drzavno-bhoi-2014'=>[7,['grafovi','dp']],
    'solarni-projektili-drzavno-bhoi-2014'=>[8,['geometrija','matematika']],
    'transformacija-drzavno-bhoi-2014'=>[5,['implementacija']],
    'ulice-brazilie-2-0-drzavno-bhoi-2014'=>[6,['grafovi']],
    'ciscenje-poslije-zabave-jbhoi-2014'=>[4,['greedy']],
    'igra-faktorizacije-jbhoi-2014'=>[5,['matematika','sortiranje']],
    'loto-jbhoi-2014'=>[5,['matematika']],
    'zlato-na-marsu-jbhoi-2014'=>[4,['implementacija']],
    // ---- 2015 ----
    'automobil-drzavno-bhoi-2015'=>[4,['implementacija']],
    'brodovi-drzavno-bhoi-2015'=>[6,['pretraga']],
    'cuvari-galaksije-epizoda-1-drzavno-bhoi-2015'=>[6,['grafovi']],
    'cuvari-galaksije-epizoda-2-drzavno-bhoi-2015'=>[7,['grafovi']],
    'fotograf-drzavno-bhoi-2015'=>[6,['strukture-podataka']],
    'grafiti-drzavno-bhoi-2015'=>[5,['greedy']],
    'helikopter-x3-drzavno-bhoi-2015'=>[6,['grafovi']],
    'kamioni-drzavno-bhoi-2015'=>[1,['geometrija','implementacija']],
    'kontrola-kontrolera-drzavno-bhoi-2015'=>[5,['implementacija','sortiranje']],
    'modularne-jednacine-drzavno-bhoi-2015'=>[5,['teorija-brojeva','matematika']],
    'mreza-stanica-drzavno-bhoi-2015'=>[7,['grafovi']],
    'laser-jbhoi-2015'=>[6,['implementacija','pretraga']],
    'mrav-jbhoi-2015'=>[4,['implementacija']],
    'opsesija-jbhoi-2015'=>[5,['dp','teorija-brojeva']],
    'party-jbhoi-2015'=>[5,['grafovi']],
    'telekom-jbhoi-2015'=>[6,['sortiranje','stringovi']],
    'vocnjaci-jbhoi-2015'=>[3,['matematika','sortiranje']],
    'zoidberg-jbhoi-2015'=>[4,['teorija-brojeva','matematika']],
    'baloni-kvalifikacije-2015'=>[3,['matematika','greedy']],
    'grafika-kvalifikacije-2015'=>[2,['implementacija','matematika']],
    'kodiranje-kvalifikacije-2015'=>[4,['implementacija','stringovi']],
    'kult-kvalifikacije-2015'=>[5,['stringovi','implementacija']],
    'najduzi-podstring-kvalifikacije-2015'=>[5,['strukture-podataka']],
    'palindromska-transformacija-kvalifikacije-2015'=>[6,['greedy','stringovi']],
    'pizza-kvalifikacije-2015'=>[6,['dp','greedy']],
    'povezivanje-kvalifikacije-2015'=>[6,['grafovi']],
    'skakac-kvalifikacije-2015'=>[6,['matematika']],
    // ---- 2016 ----
    'antene-drzavno-bhoi-2016'=>[5,['geometrija','implementacija']],
    'kombinacije-drzavno-bhoi-2016'=>[5,['matematika','implementacija']],
    'robot-drzavno-bhoi-2016'=>[4,['implementacija']],
    'varijansa-drzavno-bhoi-2016'=>[2,['matematika','implementacija']],
    'automobil-kvalifikacije-2016'=>[3,['implementacija']],
    'blackjack-kvalifikacije-2016'=>[4,['implementacija','pretraga']],
    'cetvorka-kvalifikacije-2016'=>[4,['stringovi']],
    'faktorijel-kvalifikacije-2016'=>[4,['matematika','implementacija']],
    'pangram-kvalifikacije-2016'=>[2,['stringovi','implementacija']],
    'pravougaonici-kvalifikacije-2016'=>[4,['implementacija','geometrija']],
    'stepenice-kvalifikacije-2016'=>[2,['matematika','implementacija']],
    'super-sume-kvalifikacije-2016'=>[3,['matematika']],
    // ---- 2017 ----
    'hepek-drzavno-bhoi-2017'=>[8,['pretraga']],
    'medijana-tekst-drzavno-bhoi-2017'=>[8,['strukture-podataka']],
    'palindrom-drzavno-bhoi-2017'=>[8,['dp','stringovi']],
    'poluprave-drzavno-bhoi-2017'=>[6,['teorija-brojeva','matematika']],
    'drugarstvo-kvalifikacije-2017'=>[5,['matematika']],
    'magija-kvalifikacije-2017'=>[4,['matematika','implementacija']],
    'pgodi-kvalifikacije-2017'=>[4,['pretraga']],
    'pljus-kvalifikacije-2017'=>[1,['implementacija']],
    'podskupovi-kvalifikacije-2017'=>[6,['dp']],
    'spamer-kvalifikacije-2017'=>[5,['stringovi']],
    'validne-kvalifikacije-2017'=>[5,['stringovi']],
    // ---- 2018 ----
    'fejsbuk-drzavno-bhoi-2018'=>[6,['stringovi','implementacija']],
    'kinder-pingvin-drzavno-bhoi-2018'=>[1,['implementacija']],
    'kung-fu-pingvin-drzavno-bhoi-2018'=>[4,['greedy','implementacija']],
    'lovci-drzavno-bhoi-2018'=>[5,['matematika','implementacija']],
    'medaljon-drzavno-bhoi-2018'=>[7,['grafovi']],
    'rijeci-drzavno-bhoi-2018'=>[3,['stringovi']],
    'scrooge-mcduck-drzavno-bhoi-2018'=>[7,['dp']],
    'superzamjenjivac-drzavno-bhoi-2018'=>[6,['sortiranje']],
    'svjecice-drzavno-bhoi-2018'=>[5,['greedy','matematika']],
    // ---- 2020 ----
    'bhoipripreme-drzavno-bhoi-2020'=>[8,['strukture-podataka']],
    'korona-drzavno-bhoi-2020'=>[8,['grafovi']],
    'topovi-drzavno-bhoi-2020'=>[6,['greedy']],
    'vitez-drzavno-bhoi-2020'=>[8,['grafovi','dp']],
    // ---- 2021 ----
    'autocomplete-drzavno-bhoi-2021'=>[7,['stringovi']],
    'ekskurzija-kvalifikacije-2021'=>[7,['grafovi']],
    'faktorijel-kutije-kvalifikacije-2021'=>[6,['matematika','greedy']],
    'kfree-kvalifikacije-2021'=>[5,['greedy']],
    'lolek-kvalifikacije-2021'=>[3,['implementacija','matematika']],
    'magneti-kvalifikacije-2021'=>[4,['greedy','implementacija']],
    'medalje-kvalifikacije-2021'=>[6,['dp']],
    'memorija-kvalifikacije-2021'=>[4,['matematika']],
    'nevrijeme2-kvalifikacije-2021'=>[4,['grafovi']],
    'novkoh-kvalifikacije-2021'=>[7,['pretraga','grafovi']],
    'oblakoderi-kvalifikacije-2021'=>[6,['strukture-podataka']],
    'planiranje-kvalifikacije-2021'=>[6,['dp','sortiranje']],
    'pujdo-kvalifikacije-2021'=>[5,['implementacija']],
    'relative-kvalifikacije-2021'=>[8,['grafovi','dp']],
    'rijeci-kvalifikacije-2021'=>[7,['dp','stringovi']],
    'samojedan-kvalifikacije-2021'=>[2,['matematika','implementacija']],
    'smanjitibroj-kvalifikacije-2021'=>[5,['greedy']],
    'taksi-kvalifikacije-2021'=>[5,['dp']],
    'topologija-kvalifikacije-2021'=>[5,['grafovi']],
    'triniza-kvalifikacije-2021'=>[5,['matematika','strukture-podataka']],
    'zagrade-kvalifikacije-2021'=>[4,['stringovi','strukture-podataka']],
    'zurka-kvalifikacije-2021'=>[6,['grafovi']],
];

/* ------------------------------------------------------------------- apply */
$pdo = db();

$algTagIds = [];
foreach ($ALG_TAGS as $slug => $name) {
    $s = $pdo->prepare('SELECT id FROM tags WHERE slug = ?'); $s->execute([$slug]);
    $id = (int) $s->fetchColumn();
    if ($id === 0) { $pdo->prepare('INSERT INTO tags (name, slug) VALUES (?, ?)')->execute([$name, $slug]); $id = (int) $pdo->lastInsertId(); }
    $algTagIds[$slug] = $id;
}
$allAlgIds = array_values($algTagIds);

$idBySlug = [];
foreach ($pdo->query('SELECT id, slug FROM tasks')->fetchAll() as $r) { $idBySlug[$r['slug']] = (int) $r['id']; }

$updTask = $pdo->prepare('UPDATE tasks SET difficulty_rating = ?, difficulty = ? WHERE id = ?');
$insTag  = $pdo->prepare('INSERT INTO task_tags (task_id, tag_id) VALUES (?, ?)');
$ph = implode(',', array_fill(0, count($allAlgIds), '?'));
$delAlg = $pdo->prepare("DELETE FROM task_tags WHERE task_id = ? AND tag_id IN ($ph)");

$applied = 0; $missing = []; $removed = 0;
$pdo->beginTransaction();
try {
    foreach ($REMOVE as $slug) {
        if (isset($idBySlug[$slug])) {
            $pdo->prepare('DELETE FROM tasks WHERE id = ?')->execute([$idBySlug[$slug]]); // cascades to task_tags/solutions
            $removed++;
        }
    }
    foreach ($CLASSIFY as $slug => [$rating, $tags]) {
        if (!isset($idBySlug[$slug])) { $missing[] = $slug; continue; }
        $id = $idBySlug[$slug];
        $rating = max(1, min(10, (int) $rating));
        $updTask->execute([$rating, band_for($rating), $id]);
        $delAlg->execute(array_merge([$id], $allAlgIds));
        foreach (array_unique($tags) as $ts) {
            if (isset($algTagIds[$ts])) { $insTag->execute([$id, $algTagIds[$ts]]); }
        }
        $applied++;
    }
    $pdo->commit();
} catch (Throwable $ex) { $pdo->rollBack(); exit('Greška: ' . $ex->getMessage() . PHP_EOL); }

out('');
out('=== DONE ===');
out("Classified: $applied / " . count($CLASSIFY) . ' | removed non-problems: ' . $removed);
if ($missing) { out('Not found (skipped): ' . implode(', ', $missing)); }
$uncat = (int) $pdo->query(
    "SELECT COUNT(*) FROM tasks t WHERE t.year <= 2021 AND NOT EXISTS (
        SELECT 1 FROM task_tags tt JOIN tags g ON g.id = tt.tag_id
        WHERE tt.task_id = t.id AND g.slug IN ('" . implode("','", array_keys($ALG_TAGS)) . "'))"
)->fetchColumn();
out("Legacy tasks still without an algorithm tag (unreadable scans): $uncat");
