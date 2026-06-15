-- =====================================================================
--  BHOI Arhiva — Database schema
--  Platform for Bosnian High School Informatics Competitions
--
--  Engine : MySQL 8 / MariaDB 10.4+  (InnoDB, utf8mb4)
--  Usage  : mysql -u root < schema.sql
--
--  Tables:
--    levels      — competition levels (Kantonalno … Državno/BHOI)
--    tags        — algorithm / category labels (DP, Greedy, Graphs …)
--    tasks       — the problems themselves
--    task_tags   — M:N join between tasks and tags
--    solutions   — uploaded official solutions (multiple per task)
--    admins      — back-office accounts
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `bhoi_platform`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `bhoi_platform`;

-- Drop in dependency order so the script is re-runnable -----------------
DROP TABLE IF EXISTS `solutions`;
DROP TABLE IF EXISTS `task_tags`;
DROP TABLE IF EXISTS `tasks`;
DROP TABLE IF EXISTS `tags`;
DROP TABLE IF EXISTS `levels`;
DROP TABLE IF EXISTS `admins`;

-- ---------------------------------------------------------------------
--  levels — fixed list of competition tiers, ordered by prestige.
-- ---------------------------------------------------------------------
CREATE TABLE `levels` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(100) NOT NULL,
    `slug`       VARCHAR(100) NOT NULL,
    `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 0, -- higher = more prestigious
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_levels_slug` (`slug`),
    UNIQUE KEY `uq_levels_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  tags — algorithm topics / categories.
-- ---------------------------------------------------------------------
CREATE TABLE `tags` (
    `id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(80) NOT NULL,
    `slug` VARCHAR(80) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tags_slug` (`slug`),
    UNIQUE KEY `uq_tags_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  tasks — the core entity.
-- ---------------------------------------------------------------------
CREATE TABLE `tasks` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`          VARCHAR(200) NOT NULL,
    `slug`           VARCHAR(220) NOT NULL,
    `statement`      MEDIUMTEXT NULL,                 -- problem text (plain text, line breaks preserved)
    `year`           SMALLINT UNSIGNED NOT NULL,
    `level_id`       INT UNSIGNED NOT NULL,
    `difficulty`     ENUM('Lako','Srednje','Teško') NOT NULL DEFAULT 'Srednje',
                                                      -- band label, derived from difficulty_rating
    `difficulty_rating` TINYINT UNSIGNED NOT NULL DEFAULT 5, -- custom 1–10 estimate (source of truth)
    `problem_index`  VARCHAR(10) NULL,                -- e.g. "1", "A"
    `time_limit_ms`  INT UNSIGNED NULL,
    `memory_limit_mb`INT UNSIGNED NULL,
    `pdf_path`       VARCHAR(255) NULL,               -- relative path inside /uploads
    `tests_path`     VARCHAR(255) NULL,               -- relative path inside /uploads (zip)
    `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tasks_slug` (`slug`),
    KEY `idx_tasks_year` (`year`),
    KEY `idx_tasks_level` (`level_id`),
    KEY `idx_tasks_difficulty` (`difficulty`),
    KEY `idx_tasks_difficulty_rating` (`difficulty_rating`),
    CONSTRAINT `fk_tasks_level`
        FOREIGN KEY (`level_id`) REFERENCES `levels` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  task_tags — many-to-many between tasks and tags.
-- ---------------------------------------------------------------------
CREATE TABLE `task_tags` (
    `task_id` INT UNSIGNED NOT NULL,
    `tag_id`  INT UNSIGNED NOT NULL,
    PRIMARY KEY (`task_id`, `tag_id`),
    KEY `idx_tt_tag` (`tag_id`),
    CONSTRAINT `fk_tt_task`
        FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_tt_tag`
        FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  solutions — uploaded official solutions; a task may have several.
-- ---------------------------------------------------------------------
CREATE TABLE `solutions` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `task_id`       INT UNSIGNED NOT NULL,
    `language`      VARCHAR(40) NULL,                 -- "C++", "Python", "Pascal" …
    `original_name` VARCHAR(255) NOT NULL,            -- the file name the admin uploaded
    `file_path`     VARCHAR(255) NOT NULL,            -- relative path inside /uploads
    `file_size`     INT UNSIGNED NULL,                -- bytes
    `author`        VARCHAR(120) NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sol_task` (`task_id`),
    CONSTRAINT `fk_sol_task`
        FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  submissions — code runs via the online judge (Judge0). MVP: ad-hoc
--  "Run code" against custom stdin; later, judging vs official tests.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `submissions` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `task_id`    INT UNSIGNED NULL,
    `language`   VARCHAR(20) NOT NULL,
    `source`     MEDIUMTEXT NOT NULL,
    `status`     VARCHAR(40) NULL,
    `time_ms`    INT UNSIGNED NULL,
    `memory_kb`  INT UNSIGNED NULL,
    `ip`         VARCHAR(45) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sub_task` (`task_id`),
    KEY `idx_sub_ip` (`ip`, `created_at`),
    CONSTRAINT `fk_sub_task`
        FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  admins — back-office accounts. Passwords stored as bcrypt hashes.
-- ---------------------------------------------------------------------
CREATE TABLE `admins` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username`      VARCHAR(60) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `display_name`  VARCHAR(120) NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_login_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_admins_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
--  REFERENCE DATA
-- =====================================================================

-- Competition levels (sort_order: Državno highest) -------------------
INSERT INTO `levels` (`name`, `slug`, `sort_order`) VALUES
    ('Kantonalno',       'kantonalno',  10),
    ('Regionalno',       'regionalno',  20),
    ('Federalno',        'federalno',   30),
    ('Republičko',       'republicko',  40),
    ('Državno (BHOI)',   'drzavno-bhoi',50);

-- Algorithm / category tags ------------------------------------------
INSERT INTO `tags` (`name`, `slug`) VALUES
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

-- Default administrator ----------------------------------------------
--   username: admin
--   password: admin123   <-- CHANGE THIS AFTER FIRST LOGIN
INSERT INTO `admins` (`username`, `password_hash`, `display_name`) VALUES
    ('admin',
     '$2y$10$SCAf5IWVO4pzUCVkEPHRhe6bi5CczZC58zvjY0Ba0S0uEGXE1WSCq',
     'Glavni administrator');
