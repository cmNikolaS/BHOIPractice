/* ---------------------------------------------------------------------
 * app.js — catalog filtering + per-browser progress tracking.
 *
 * Filtering is instant (rows are toggled, never re-fetched). "Completed"
 * status is stored in localStorage, so the Status column, the progress
 * bar and the Solved/Unsolved filter all work without any login.
 * ------------------------------------------------------------------- */
(function () {
    'use strict';

    var KEY = 'bhoi_completed_v1';

    var search = document.getElementById('f-search');
    var status = document.getElementById('f-status');
    var diff   = document.getElementById('f-difficulty');
    var level  = document.getElementById('f-level');
    var tag    = document.getElementById('f-tag');
    var year   = document.getElementById('f-year');
    var clear  = document.getElementById('clear-filters');
    var random = document.getElementById('pick-random');
    var rows   = Array.prototype.slice.call(document.querySelectorAll('.task-row'));
    var count  = document.getElementById('result-count');
    var empty  = document.getElementById('empty-state');
    var solvedCountEl = document.getElementById('solved-count');
    var progressBar   = document.getElementById('progress-bar');

    if (!search) return;

    var total = rows.length;

    /* ---- completed set (localStorage) ---- */
    function getDone() {
        try { return new Set(JSON.parse(localStorage.getItem(KEY) || '[]').map(String)); }
        catch (e) { return new Set(); }
    }
    function saveDone(set) {
        try { localStorage.setItem(KEY, JSON.stringify(Array.from(set))); } catch (e) {}
    }
    var done = getDone();

    /* ---- reflect completed state on the rows ---- */
    function applyDoneState() {
        rows.forEach(function (row) {
            var isDone = done.has(row.dataset.id);
            row.classList.toggle('row-done', isDone);
            var btn = row.querySelector('.status-toggle');
            if (btn) {
                btn.classList.toggle('done', isDone);
                btn.title = isDone ? 'Riješeno — klikni da poništiš' : 'Označi kao riješeno';
            }
        });
        refreshProgress();
    }

    function refreshProgress() {
        var solved = rows.reduce(function (n, r) { return n + (done.has(r.dataset.id) ? 1 : 0); }, 0);
        if (solvedCountEl) solvedCountEl.textContent = String(solved);
        if (progressBar) progressBar.style.width = (total ? Math.round(solved / total * 100) : 0) + '%';
    }

    /* ---- filtering ---- */
    function getFilters() {
        return {
            q:      (search.value || '').trim().toLowerCase(),
            status: status ? status.value : '',
            diff:   diff ? diff.value : '',
            level:  level.value,
            tag:    tag.value,
            year:   year.value,
        };
    }

    function apply() {
        var f = getFilters();
        var visible = 0;
        rows.forEach(function (row) {
            var isDone = done.has(row.dataset.id);
            var ok =
                (!f.q     || row.dataset.search.indexOf(f.q) !== -1) &&
                (!f.diff  || row.dataset.difficulty === f.diff) &&
                (!f.level || row.dataset.level === f.level) &&
                (!f.year  || row.dataset.year === f.year) &&
                (!f.tag   || row.dataset.tags.split(' ').indexOf(f.tag) !== -1) &&
                (!f.status || (f.status === 'solved' ? isDone : !isDone));
            row.classList.toggle('hidden', !ok);
            if (ok) visible++;
        });
        if (count) count.textContent = String(visible);
        if (empty) empty.classList.toggle('hidden', visible !== 0);
        syncUrl(f);
    }

    function syncUrl(f) {
        var p = new URLSearchParams();
        if (f.q)      p.set('q', f.q);
        if (f.status) p.set('status', f.status);
        if (f.diff)   p.set('difficulty', f.diff);
        if (f.level)  p.set('level', f.level);
        if (f.tag)    p.set('tag', f.tag);
        if (f.year)   p.set('year', f.year);
        var qs = p.toString();
        history.replaceState(null, '', qs ? '?' + qs : location.pathname);
    }

    function hydrateFromUrl() {
        var p = new URLSearchParams(location.search);
        if (p.has('q'))          search.value = p.get('q');
        if (p.has('status') && status) status.value = p.get('status');
        if (p.has('difficulty') && diff) diff.value = p.get('difficulty');
        if (p.has('level'))      level.value = p.get('level');
        if (p.has('tag'))        tag.value = p.get('tag');
        if (p.has('year'))       year.value = p.get('year');
    }

    /* ---- events ---- */
    [search, status, diff, level, tag, year].forEach(function (el) {
        if (!el) return;
        el.addEventListener('input', apply);
        el.addEventListener('change', apply);
    });

    rows.forEach(function (row) {
        var btn = row.querySelector('.status-toggle');
        if (!btn) return;
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var id = btn.dataset.id;
            if (done.has(id)) { done.delete(id); } else { done.add(id); }
            saveDone(done);
            applyDoneState();
            apply();
        });
    });

    if (clear) {
        clear.addEventListener('click', function () {
            [search, status, diff, level, tag, year].forEach(function (el) { if (el) el.value = ''; });
            apply();
        });
    }

    if (random) {
        random.addEventListener('click', function () {
            var pool = rows.filter(function (r) { return !r.classList.contains('hidden'); });
            var unsolved = pool.filter(function (r) { return !done.has(r.dataset.id); });
            var pick = (unsolved.length ? unsolved : pool);
            if (!pick.length) return;
            var row = pick[Math.floor(Math.random() * pick.length)];
            window.location.href = row.dataset.url;
        });
    }

    // Press "/" to jump to the search box.
    document.addEventListener('keydown', function (e) {
        if (e.key === '/' && document.activeElement !== search &&
            !/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)) {
            e.preventDefault();
            search.focus();
        }
    });

    hydrateFromUrl();
    applyDoneState();
    apply();
})();
