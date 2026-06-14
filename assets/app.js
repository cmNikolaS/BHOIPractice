/* ---------------------------------------------------------------------
 * app.js — instant client-side filtering for the task catalog.
 *
 * Rows are rendered server-side with data-year / data-level / data-tags /
 * data-search attributes. We toggle visibility instead of re-fetching,
 * so filtering feels instant. The active filters are mirrored to the URL
 * query string so a filtered view can be bookmarked or shared.
 * ------------------------------------------------------------------- */
(function () {
    'use strict';

    const search = document.getElementById('f-search');
    const year   = document.getElementById('f-year');
    const level  = document.getElementById('f-level');
    const tag    = document.getElementById('f-tag');
    const clear  = document.getElementById('clear-filters');
    const rows   = Array.from(document.querySelectorAll('.task-row'));
    const count  = document.getElementById('result-count');
    const empty  = document.getElementById('empty-state');

    if (!rows.length && !search) return;

    /** Read the current filter state. */
    function getFilters() {
        return {
            q:     (search.value || '').trim().toLowerCase(),
            year:  year.value,
            level: level.value,
            tag:   tag.value,
        };
    }

    /** Apply filters to every row and update the count + empty state. */
    function apply() {
        const f = getFilters();
        let visible = 0;

        rows.forEach((row) => {
            const matchQ     = !f.q     || row.dataset.search.includes(f.q);
            const matchYear  = !f.year  || row.dataset.year === f.year;
            const matchLevel = !f.level || row.dataset.level === f.level;
            const matchTag   = !f.tag   || row.dataset.tags.split(' ').includes(f.tag);

            const show = matchQ && matchYear && matchLevel && matchTag;
            row.classList.toggle('hidden', !show);
            if (show) visible++;
        });

        if (count) count.textContent = String(visible);
        if (empty) empty.classList.toggle('hidden', visible !== 0);

        syncUrl(f);
    }

    /** Mirror filters into the URL without reloading. */
    function syncUrl(f) {
        const params = new URLSearchParams();
        if (f.q)     params.set('q', f.q);
        if (f.year)  params.set('year', f.year);
        if (f.level) params.set('level', f.level);
        if (f.tag)   params.set('tag', f.tag);
        const qs = params.toString();
        history.replaceState(null, '', qs ? '?' + qs : location.pathname);
    }

    /** Pre-fill controls from the URL on first load (shareable links). */
    function hydrateFromUrl() {
        const params = new URLSearchParams(location.search);
        if (params.has('q'))     search.value = params.get('q');
        if (params.has('year'))  year.value   = params.get('year');
        if (params.has('level')) level.value  = params.get('level');
        if (params.has('tag'))   tag.value    = params.get('tag');
    }

    // Wire up events.
    [search, year, level, tag].forEach((el) => {
        if (!el) return;
        el.addEventListener('input', apply);
        el.addEventListener('change', apply);
    });

    if (clear) {
        clear.addEventListener('click', () => {
            search.value = '';
            year.value = '';
            level.value = '';
            tag.value = '';
            apply();
        });
    }

    hydrateFromUrl();
    apply();
})();
