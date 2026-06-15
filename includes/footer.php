<?php
/**
 * includes/footer.php — closing markup for public pages.
 */
?>
</main>

<footer class="mt-16 border-t border-line bg-card">
    <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 px-4 py-6 text-sm text-muted sm:flex-row sm:px-6">
        <p>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?> — Arhiva takmičarskih zadataka iz informatike.</p>
        <nav class="flex items-center gap-4">
            <a href="<?= e(url('about.php')) ?>" class="transition hover:text-accent">O sajtu</a>
            <a href="<?= e(DEV_GITHUB_URL) ?>" target="_blank" rel="noopener" class="transition hover:text-accent">GitHub</a>
            <a href="<?= e(url('about.php')) ?>#podrzi" class="transition hover:text-accent">Doniraj</a>
        </nav>
    </div>
</footer>

</body>
</html>
