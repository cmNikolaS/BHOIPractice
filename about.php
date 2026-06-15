<?php
/**
 * about.php — "O sajtu": kratak uvod za prve posjetioce, developer i donacije.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

$page_title = 'O sajtu';
$page_desc  = 'O BHOI Arhivi — nesponzorisana arhiva i platforma za vježbu zadataka sa takmičenja iz informatike u Bosni i Hercegovini.';
require __DIR__ . '/includes/header.php';
?>

<div class="mx-auto max-w-3xl space-y-6">
    <header>
        <h1 class="text-3xl font-extrabold tracking-tight text-fg">O sajtu</h1>
        <p class="mt-2 text-muted">Šta je BHOI Arhiva i kako da je koristiš.</p>
    </header>

    <section class="rounded-2xl border border-line bg-card p-6 shadow-sm">
        <p class="leading-7 text-fg">
            <strong>BHOI Arhiva</strong> je arhiva i platforma za vježbu zadataka sa takmičenja iz
            informatike u Bosni i Hercegovini — jBHOI, BHGOI, državno BHOI i kvalifikaciona takmičenja.
            Na jednom mjestu su sakupljeni <strong>301 zadatak (2003–2026)</strong>, sa tekstovima,
            službenim rješenjima i mogućnošću da svoj kod pokreneš i provjeriš direktno u pregledniku.
        </p>
        <ul class="mt-4 space-y-2 text-sm text-muted">
            <li>• Filtriraj po težini, kategoriji (DP, matematika, grafovi…), nivou i godini.</li>
            <li>• Prati napredak — prsten po težini, „zadaci dana", označavanje riješenih.</li>
            <li>• Pokreni C++ kod na vlastitom ulazu ili ga <strong>ocijeni protiv zvaničnih test primjera</strong>.</li>
            <li>• Napravi nalog da ti se napredak pamti na svim uređajima.</li>
        </ul>
    </section>

    <section class="rounded-2xl border border-line bg-card p-6 shadow-sm">
        <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-muted">Razvoj</h2>
        <p class="leading-7 text-fg">
            Sajt razvija i održava <strong>Nikola Šarić</strong>.
            Izvorni kod i prijava grešaka:
            <a href="<?= e(DEV_GITHUB_URL) ?>" target="_blank" rel="noopener" class="font-semibold text-accent transition hover:brightness-110">GitHub</a>.
        </p>
        <p class="mt-2 text-sm text-muted">
            Zadaci i test primjeri preuzeti su iz zvaničnog
            <a href="https://github.com/BHOI/BHOI-takmicenja-iz-informatike" target="_blank" rel="noopener" class="text-accent transition hover:brightness-110">BHOI repozitorija</a>;
            sva prava pripadaju autorima zadataka i organizatorima takmičenja.
        </p>
    </section>

    <section id="podrzi" class="scroll-mt-20 rounded-2xl border border-line bg-card p-6 shadow-sm">
        <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-muted">❤️ Podrži sajt</h2>
        <p class="leading-7 text-fg">
            Ovaj sajt <strong>nije sponzorisan</strong> i besplatan je za korištenje. Donacije pomažu pri
            pokrivanju troškova hostinga i online provjere koda (judge), kako bi sajt ostao dostupan svima.
        </p>
        <?php if (DONATE_URL !== ''): ?>
            <a href="<?= e(DONATE_URL) ?>" target="_blank" rel="noopener"
               class="mt-4 inline-flex items-center gap-2 rounded-xl bg-accent px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-110">
                ❤️ Doniraj
            </a>
        <?php else: ?>
            <p class="mt-3 text-sm text-muted">Link za donacije će uskoro biti dostupan.</p>
        <?php endif; ?>
    </section>

    <p class="text-center text-sm text-muted">
        <a href="<?= e(url('index.php')) ?>" class="transition hover:text-accent">← Nazad na zadatke</a>
    </p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
