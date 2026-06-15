-- =====================================================================
--  BHOI Arhiva — SQLite schema (used for the Fly.io / single-file deploy)
--
--  Mirrors schema.sql but in SQLite dialect. Load with:
--      sqlite3 app.sqlite < schema.sqlite.sql
--  Foreign keys are enforced at runtime via "PRAGMA foreign_keys = ON"
--  (set by config.php on every connection).
-- =====================================================================

PRAGMA foreign_keys = ON;

DROP TABLE IF EXISTS solutions;
DROP TABLE IF EXISTS task_tags;
DROP TABLE IF EXISTS tasks;
DROP TABLE IF EXISTS tags;
DROP TABLE IF EXISTS levels;
DROP TABLE IF EXISTS admins;

CREATE TABLE levels (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT NOT NULL UNIQUE,
    slug       TEXT NOT NULL UNIQUE,
    sort_order INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE tags (
    id   INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    slug TEXT NOT NULL UNIQUE
);

CREATE TABLE tasks (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    title           TEXT NOT NULL,
    slug            TEXT NOT NULL UNIQUE,
    statement       TEXT,
    year            INTEGER NOT NULL,
    level_id        INTEGER NOT NULL,
    difficulty      TEXT NOT NULL DEFAULT 'Srednje'
                    CHECK (difficulty IN ('Lako','Srednje','Teško')),
    difficulty_rating INTEGER NOT NULL DEFAULT 5
                    CHECK (difficulty_rating BETWEEN 1 AND 10),
    problem_index   TEXT,
    time_limit_ms   INTEGER,
    memory_limit_mb INTEGER,
    pdf_path        TEXT,
    tests_path      TEXT,
    created_at      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (level_id) REFERENCES levels (id) ON UPDATE CASCADE ON DELETE RESTRICT
);
CREATE INDEX idx_tasks_year ON tasks (year);
CREATE INDEX idx_tasks_level ON tasks (level_id);
CREATE INDEX idx_tasks_difficulty ON tasks (difficulty);
CREATE INDEX idx_tasks_difficulty_rating ON tasks (difficulty_rating);

CREATE TABLE task_tags (
    task_id INTEGER NOT NULL,
    tag_id  INTEGER NOT NULL,
    PRIMARY KEY (task_id, tag_id),
    FOREIGN KEY (task_id) REFERENCES tasks (id) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (tag_id)  REFERENCES tags  (id) ON UPDATE CASCADE ON DELETE CASCADE
);
CREATE INDEX idx_tt_tag ON task_tags (tag_id);

CREATE TABLE solutions (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id       INTEGER NOT NULL,
    language      TEXT,
    original_name TEXT NOT NULL,
    file_path     TEXT NOT NULL,
    file_size     INTEGER,
    author        TEXT,
    created_at    TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks (id) ON UPDATE CASCADE ON DELETE CASCADE
);
CREATE INDEX idx_sol_task ON solutions (task_id);

CREATE TABLE admins (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    username      TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    display_name  TEXT,
    created_at    TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at TEXT
);

-- ----- reference data (levels are replaced by import_bhoi.php) --------
INSERT INTO levels (name, slug, sort_order) VALUES
    ('Kvalifikacije',     'kvalifikacije', 10),
    ('BHGOI',             'bhgoi',         20),
    ('Juniorsko (jBHOI)', 'jbhoi',         30),
    ('Državno (BHOI)',    'drzavno-bhoi',  40);

INSERT INTO tags (name, slug) VALUES
    ('Dinamičko programiranje', 'dp'),
    ('Pohlepni algoritmi',      'greedy'),
    ('Grafovi',                 'grafovi'),
    ('Matematika',              'matematika'),
    ('Sortiranje',              'sortiranje'),
    ('Pretraga',                'pretraga'),
    ('Stringovi',               'stringovi'),
    ('Strukture podataka',      'strukture-podataka'),
    ('Geometrija',              'geometrija'),
    ('Implementacija',          'implementacija'),
    ('Teorija brojeva',         'teorija-brojeva'),
    ('Rekurzija',               'rekurzija');

-- Default admin: admin / admin123  (CHANGE AFTER FIRST LOGIN)
INSERT INTO admins (username, password_hash, display_name) VALUES
    ('admin',
     '$2y$10$SCAf5IWVO4pzUCVkEPHRhe6bi5CczZC58zvjY0Ba0S0uEGXE1WSCq',
     'Glavni administrator');
