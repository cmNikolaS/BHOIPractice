# Online judge (Judge0) — MVP & plan

Goal: turn the archive from "read-only" into a place where you can actually
**run and check** solutions, without hosting code execution ourselves
(security + resources). We use a hosted [Judge0](https://judge0.com) instance.

## What's already wired (this commit)

Everything below is **dormant** until `JUDGE0_URL` is set — no UI or endpoint
is exposed otherwise, so production is unchanged until you flip it on.

- **`config.php`** — `JUDGE0_URL`, `JUDGE0_KEY`, `JUDGE0_HOST` (env-driven).
- **`includes/judge.php`** — `judge_enabled()`, `judge_languages()` (C++, C,
  Python, Java, Pascal → Judge0 CE ids), and `judge_run()` (one synchronous
  compile+run via `POST /submissions?wait=true`, base64 I/O).
- **`submit.php`** — CSRF-protected JSON endpoint: runs source against custom
  stdin, logs to `submissions`, returns stdout/stderr/compile/time/memory.
  Returns 503 when the judge isn't configured.
- **`submissions`** table (MySQL + SQLite schema; idempotent migration in
  `docker-entrypoint.sh` so existing volumes get it on boot).
- **`task.php`** — a "Pokreni kod" panel (code + stdin → output), rendered
  only when `judge_enabled()`.

So the MVP = **run code against your own input** on any task page. This needs
no test cases and is the safe first slice.

## How to enable

Self-hosted Judge0 (recommended; no per-call limits):
```
fly secrets set JUDGE0_URL=https://your-judge0-host   # then redeploy/restart
```
RapidAPI Judge0 CE (quick, rate-limited):
```
fly secrets set JUDGE0_URL=https://judge0-ce.p.rapidapi.com \
                JUDGE0_KEY=<rapidapi-key> JUDGE0_HOST=judge0-ce.p.rapidapi.com
```

## Phase 2 — judging against the official test sets

The BHOI repo ships per-task test cases (input/output), ~1 GB total — too big
for the 1 GB Fly volume, and we deliberately did **not** import them. Options,
cheapest first:

1. **On-demand fetch + cache.** At submit time, pull that task's test archive
   from GitHub (raw), run each case through Judge0 with `expected_output`, cache
   recent archives on the volume with an LRU cap. No bulk storage; first submit
   per task is slower. Needs a `task_tests` mapping (repo path per task).
2. **External object storage** (S3/R2/B2): upload all tests once, stream per
   task at submit time. Predictable, small monthly cost.
3. **Separate larger volume** holding all tests. Simplest runtime, most storage.

Recommended: **(1)** for the MVP→v2 jump. Add a `task_tests` table
(`task_id`, `source_url`/path, case count), a verdict aggregator (AC/WA/TLE/RE
across cases using Judge0 `status_id`), and a batched-submissions call. Store
the verdict in `submissions.status`.

## Notes / TODO

- Per-IP/session rate limiting on `submit.php` before going public (Judge0
  quota + abuse). A simple `submissions`-count window is enough to start.
- Optional: persist the user's last source per task in localStorage.
- Optional: show sample I/O from the statement as a one-click "Run sample".
