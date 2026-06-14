# BHOI Arhiva

A clean, modern competitive-programming archive tailored for **Bosnian High
School Informatics Competitions** — Kantonalno, Regionalno, Federalno,
Republičko and Državno (BHOI). Students browse, filter and download tasks;
admins manage everything from a secure back office.

**Stack:** PHP 8 (PDO) · MySQL / MariaDB · Tailwind CSS (CDN) · vanilla JS.

---

## ✨ Features

**Public**
- Task catalog with **instant client-side filtering** by year, level, algorithm tag and free-text search. Filter state is mirrored to the URL, so any view is shareable/bookmarkable.
- Task detail page with **Markdown-rendered statements** (headings, lists, tables, code, images) and one-click downloads.
- **Solution preview** — click any solution to read it in a syntax-highlighted modal (highlight.js) with copy + download; or download directly.
- Task **PDF** opens inline in the browser or downloads; test-cases **ZIP** when present.
- Colour-coded level and difficulty badges.

**Pre-loaded archive** — 90 problems from the official
[BHOI repository](https://github.com/BHOI/BHOI-takmicenja-iz-informatike)
(BHOI, jBHOI, BHGOI and qualification rounds, 2022–2026) are imported with
their PDFs, Markdown statements and 299 C++ solutions. See *Importing* below.

**Admin (secure)**
- Session-based login (bcrypt), CSRF-protected forms, generic errors that don't leak which field was wrong.
- Full **CRUD** for tasks with a comprehensive multi-file upload form (1 PDF, many solutions, 1 ZIP).
- Manage view to edit/delete tasks and remove individual uploaded files.

**Security baked in**
- Every query uses **PDO prepared statements** (no string-concatenated SQL).
- All dynamic output is HTML-escaped (`e()`).
- Uploads are validated by **extension + MIME + size**, stored under random names, and an `.htaccess` blocks script execution in `/uploads`.
- Downloads are streamed through `download.php`, which resolves paths via the DB and **confirms they stay inside `/uploads`** (defeats path traversal).
- CSRF tokens on every state-changing POST; session id regenerated on login.

---

## 📁 Project structure

```
bhoiProblemsPractice/
├── config.php                # DB credentials + PDO connection (env-overridable)
├── schema.sql                # Tables + reference data (levels, tags, default admin)
├── seed.sql                  # Optional sample tasks for a populated demo
│
├── index.php                 # Task catalog + filtering UI
├── task.php                  # Single task view, Markdown statement, solution preview
├── download.php              # Secure file delivery (PDF inline/attach, ZIP, solutions)
├── solution_raw.php          # Plain-text source for the preview modal
│
├── preview.bat / preview.ps1 # One-click local preview (self-contained DB + web server)
├── stop-preview.bat / .ps1   # Stop the preview
├── import_bhoi.php           # One-off importer for the official BHOI archive
│
├── admin_login.php           # Admin sign-in
├── admin_logout.php          # Sign-out
├── admin_dashboard.php       # List + Add/Edit task form (CRUD hub)
├── admin_task_save.php       # Create/update handler (+ uploads, tags)
├── admin_task_delete.php     # Delete task or a single solution file
│
├── includes/
│   ├── bootstrap.php         # Session, helpers (escape, CSRF, flash, badges, slugify)
│   ├── auth.php              # Login/logout/guard helpers
│   ├── uploads.php           # Validated upload + safe path resolution
│   ├── header.php / footer.php              # Public layout
│   └── admin_header.php / admin_footer.php  # Admin layout
│
├── assets/
│   └── app.js                # Instant catalog filtering
│
└── uploads/                  # Stored files (git-ignored contents)
    ├── .htaccess             # Blocks script execution here
    ├── pdf/  tests/  solutions/
```

> The existing `RPZ/` folder (your real competition solutions) and the old
> static portfolio files are left untouched. The `RPZ/*.cpp` files make great
> first uploads through the admin panel.

---

## 🗄️ Database tables

| Table        | Purpose                                                        |
|--------------|----------------------------------------------------------------|
| `levels`     | Competition tiers, ordered by prestige                         |
| `tags`       | Algorithm / category labels (DP, Greedy, Graphs, …)            |
| `tasks`      | The problems (title, statement, year, level FK, difficulty, file paths) |
| `task_tags`  | M:N join between tasks and tags                                |
| `solutions`  | Uploaded official solutions (many per task)                    |
| `admins`     | Back-office accounts (bcrypt password hashes)                  |

Foreign keys cascade on delete (deleting a task removes its tags & solution rows; its files are unlinked in code).

---

## ▶️ Quick preview (no setup, no XAMPP MySQL needed)

Because your main XAMPP MySQL is currently corrupt, there's a **self-contained
launcher** that runs the whole site on its own private database — without
touching your existing data:

1. Double-click **`preview.bat`**.
   - First run creates a private DB (sibling folder `..\bhoi-preview-db`),
     loads the schema, and imports stays intact between runs.
   - It starts MySQL on port **3307** and the site on **http://localhost:8000**,
     then opens your browser.
2. Browse the catalog; admin at `/admin_login.php` (`admin` / `admin123`).
3. To stop: close the server window, then double-click **`stop-preview.bat`**.

> The preview database already contains all 90 imported BHOI problems.

---

## 🚀 Setup (XAMPP on Windows)

1. **Place the project** in your web root, e.g. `C:\xampp\htdocs\bhoi\`
   (or point an Apache vhost at this folder).

2. **Start Apache + MySQL** from the XAMPP Control Panel.

3. **Create the database** — open a terminal:
   ```bash
   C:\xampp\mysql\bin\mysql.exe -u root < schema.sql
   C:\xampp\mysql\bin\mysql.exe -u root bhoi_platform < seed.sql   # optional demo data
   ```
   …or import `schema.sql` (then `seed.sql`) via **phpMyAdmin**.

4. **Check `config.php`.** Defaults match XAMPP (`root`, no password). Override
   with environment variables in production: `DB_HOST`, `DB_PORT`, `DB_NAME`,
   `DB_USER`, `DB_PASS`. If the app lives in a sub-folder, set `BASE_URL`
   (e.g. `/bhoi`).

5. **Visit** `http://localhost/bhoi/` (adjust to your path).

6. **Admin login** → `http://localhost/bhoi/admin_login.php`
   - **Username:** `admin`
   - **Password:** `admin123`
   - **⚠️ Change this immediately** (see below).

### Change the admin password

Generate a fresh bcrypt hash and update the row:
```bash
C:\xampp\php\php.exe -r "echo password_hash('your-new-password', PASSWORD_BCRYPT), PHP_EOL;"
```
```sql
UPDATE admins SET password_hash = '<paste-hash-here>' WHERE username = 'admin';
```

---

## ✅ Verified

The full stack was tested end-to-end against a clean MariaDB instance:
schema + seed load, catalog rendering, task detail, login/logout, the admin
guard, CSRF rejection, multi-file upload (PDF + ZIP + `.cpp`), byte-exact
downloads, path-traversal protection, and delete-with-file-cleanup all pass.
The full 90-problem import (90 PDFs, 299 solutions) was run and verified:
catalog lists all 90, statements render as Markdown, the solution-preview
modal streams source, and PDFs open inline.

> **Heads-up about your local MySQL:** your existing XAMPP MariaDB **data
> directory is corrupt** (`InnoDB: ... log sequence number is in the future …
> database may be corrupt`, dating to April). It would not start during
> testing, so verification used a separate clean data dir. Before running the
> app you'll likely need to repair it — the usual fix is to set
> `innodb_force_recovery=1` in `my.ini`, start MySQL, dump your data, then
> rebuild the data directory. Happy to walk you through it.

---

## 📥 Importing the BHOI archive

`import_bhoi.php` pulls every problem from the official repo and loads it
(statements, PDFs, C++ solutions, time/memory limits). It maps the repo's
rounds to levels (BHOI / jBHOI / BHGOI / Kvalifikacije), tags problems by
school category, and rewrites statement images to absolute URLs so figures
render. **It wipes existing tasks first**, then re-imports.

```powershell
# Fetch the repo file tree once (the importer reads it):
#   _import/tree.json  <-  https://api.github.com/repos/BHOI/BHOI-takmicenja-iz-informatike/git/trees/master?recursive=1
$env:DB_PORT="3307"; C:\xampp\php\php.exe import_bhoi.php   # 3307 = preview DB
# (omit DB_PORT to import into your normal XAMPP MySQL on 3306)
```

Test cases (the repo's bulky `input/output` sets, ~1 GB) are intentionally
**not** imported; upload a ZIP per task via the admin panel if you want them.

---

## 🛠️ Production notes

- Swap the Tailwind **Play CDN** for a compiled stylesheet (Tailwind CLI) before going live — the CDN prints a console warning and is not meant for production.
- Serve over **HTTPS** so the session cookie's `secure` flag activates automatically.
- Consider raising `upload_max_filesize` / `post_max_size` in `php.ini` if your uploads exceed PHP's defaults (the app caps each file at 15 MB via `MAX_UPLOAD_BYTES`).
