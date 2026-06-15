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
    var sort   = document.getElementById('f-sort');
    var clear  = document.getElementById('clear-filters');
    var random = document.getElementById('pick-random');
    var tbody  = document.getElementById('task-rows');
    var rows   = Array.prototype.slice.call(document.querySelectorAll('.task-row'));
    var originalOrder = rows.slice(); // server-side default order ("Zadano")
    var count  = document.getElementById('result-count');
    var empty  = document.getElementById('empty-state');
    var solvedCountEl = document.getElementById('solved-count');

    /* ---- difficulty progress ring (LeetCode-style) ---- */
    var RING_C = 2 * Math.PI * 52;          // circle r=52 in the SVG
    var ringEl = { 'Lako': document.getElementById('ring-easy'),
                   'Srednje': document.getElementById('ring-medium'),
                   'Teško': document.getElementById('ring-hard') };
    var cntEl  = { 'Lako': document.getElementById('cnt-easy'),
                   'Srednje': document.getElementById('cnt-medium'),
                   'Teško': document.getElementById('cnt-hard') };

    if (!search) return;

    var total = rows.length;

    /* ---- completed set (localStorage) ---- */
    function getDone() {
        try { return new Set(JSON.parse(localStorage.getItem(KEY) || '[]').map(String)); }
        catch (e) { return new Set(); }
    }
    function saveDone(set) {
        var arr = Array.from(set);
        try { localStorage.setItem(KEY, JSON.stringify(arr)); } catch (e) {}
        if (window.bhoiSaveProgress) window.bhoiSaveProgress(arr);   // sync to account if logged in
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
        var order = ['Lako', 'Srednje', 'Teško'];
        var tot = { 'Lako': 0, 'Srednje': 0, 'Teško': 0 };
        var sol = { 'Lako': 0, 'Srednje': 0, 'Teško': 0 };
        rows.forEach(function (r) {
            var b = r.dataset.difficulty;
            if (tot[b] === undefined) return;
            tot[b]++;
            if (done.has(r.dataset.id)) sol[b]++;
        });
        var solved = sol['Lako'] + sol['Srednje'] + sol['Teško'];
        if (solvedCountEl) solvedCountEl.textContent = String(solved);

        var T = total || 1, gap = 3, start = 0;
        order.forEach(function (b) {
            var len = (sol[b] / T) * RING_C;          // arc length ∝ solved share of all tasks
            var el = ringEl[b];
            if (el) {
                // small gap between segments, but only when the arc is big enough to spare it
                var draw = len >= 7.5 ? len - gap : len;
                el.setAttribute('stroke-dasharray', draw.toFixed(2) + ' ' + (RING_C - draw).toFixed(2));
                el.setAttribute('stroke-dashoffset', (-start).toFixed(2));
            }
            start += len;
            if (cntEl[b]) cntEl[b].textContent = sol[b] + '/' + tot[b];
        });
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
            sort:   sort ? sort.value : '',
        };
    }

    /* ---- sorting (reorders the rows in the DOM) ---- */
    function applySort() {
        if (!tbody) return;
        var mode = sort ? sort.value : '';
        var ordered;
        if (!mode) {
            ordered = originalOrder;
        } else {
            ordered = originalOrder.slice().sort(function (a, b) {
                switch (mode) {
                    case 'diff-asc':  return num(a, 'rating') - num(b, 'rating');
                    case 'diff-desc': return num(b, 'rating') - num(a, 'rating');
                    case 'year-desc': return num(b, 'year') - num(a, 'year');
                    case 'year-asc':  return num(a, 'year') - num(b, 'year');
                    case 'title-asc': return (a.dataset.title || '').localeCompare(b.dataset.title || '', 'bs');
                    default:          return 0;
                }
            });
        }
        ordered.forEach(function (row) { tbody.appendChild(row); });
    }

    function num(row, key) { return parseInt(row.dataset[key], 10) || 0; }

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
        if (f.sort)   p.set('sort', f.sort);
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
        if (p.has('sort') && sort) sort.value = p.get('sort');
    }

    /* ---- events ---- */
    [search, status, diff, level, tag, year].forEach(function (el) {
        if (!el) return;
        el.addEventListener('input', apply);
        el.addEventListener('change', apply);
    });

    if (sort) {
        sort.addEventListener('change', function () { applySort(); apply(); });
    }

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
            [search, status, diff, level, tag, year, sort].forEach(function (el) { if (el) el.value = ''; });
            applySort();
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
    applySort();
    applyDoneState();
    apply();

    // Logged-in users: pull progress from the account (source of truth) and re-render.
    if (window.bhoiLoadProgress) {
        window.bhoiLoadProgress().then(function (arr) {
            if (arr) {
                done = new Set(arr.map(String));
                try { localStorage.setItem(KEY, JSON.stringify(arr.map(String))); } catch (e) {}
                applyDoneState();
                apply();
            }
        });
    }
})();
