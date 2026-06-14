-- =====================================================================
--  BHOI Arhiva — Sample / demo data (optional)
--
--  Run AFTER schema.sql:
--      mysql -u root bhoi_platform < seed.sql
--
--  These rows let you see the catalog populated immediately. File paths
--  (pdf_path / tests_path / solutions) are left NULL because no physical
--  files exist yet — upload them through the admin panel. The tasks still
--  render fully (statement, tags, badges); download buttons simply hide
--  themselves when a file is absent.
-- =====================================================================

USE `bhoi_platform`;

-- Helper note: level ids follow schema.sql insert order
--   1=Kantonalno 2=Regionalno 3=Federalno 4=Republičko 5=Državno(BHOI)
-- tag ids:
--   1=dp 2=greedy 3=grafovi 4=matematika 5=sortiranje 6=pretraga
--   7=stringovi 8=strukture-podataka 9=geometrija 10=implementacija
--   11=teorija-brojeva 12=rekurzija

INSERT INTO `tasks`
    (`title`, `slug`, `statement`, `year`, `level_id`, `difficulty`,
     `problem_index`, `time_limit_ms`, `memory_limit_mb`)
VALUES
    ('Sakupljanje novčića', 'sakupljanje-novcica-2025-reg',
     'Na pravougaonoj mapi dimenzija N x M nalazi se igrač i određeni broj novčića.\nIgrač se kreće prema nizu komandi (W, A, S, D) uz toroidalno (wrap-around) ponašanje ivica.\nNapiši program koji izračunava ukupan broj sakupljenih novčića nakon izvršenja svih komandi.',
     2025, 2, 'Srednje', '1', 1000, 256),

    ('Najduži rastući podniz', 'najduzi-rastuci-podniz-2024-rep',
     'Dat je niz od N cijelih brojeva. Pronađi dužinu najdužeg strogo rastućeg podniza.\nOčekuje se rješenje u O(N log N) složenosti.',
     2024, 4, 'Teško', '3', 2000, 256),

    ('Zbir djelilaca', 'zbir-djelilaca-2023-kant',
     'Za zadati broj N ispiši zbir svih njegovih pozitivnih djelilaca, uključujući 1 i sam broj N.',
     2023, 1, 'Lako', '2', 1000, 64),

    ('Najkraći put kroz lavirint', 'najkraci-put-lavirint-2026-drzavno',
     'U lavirintu predstavljenom matricom karaktera pronađi najkraći put od ulaza (S) do izlaza (E).\nZidovi su označeni sa #, prohodna polja sa tačkom. Dozvoljeno je kretanje gore, dolje, lijevo i desno.',
     2026, 5, 'Teško', 'A', 3000, 256),

    ('Sortiranje stringova po dužini', 'sortiranje-stringova-2022-fed',
     'Dato je N riječi. Sortiraj ih po dužini rastuće; riječi iste dužine zadrži u originalnom redoslijedu (stabilno sortiranje).',
     2022, 3, 'Srednje', '4', 1000, 128);

-- Tag associations ----------------------------------------------------
INSERT INTO `task_tags` (`task_id`, `tag_id`) VALUES
    (1, 10), (1, 6),            -- implementacija, pretraga
    (2, 1),  (2, 6),            -- dp, pretraga
    (3, 4),  (3, 11),           -- matematika, teorija brojeva
    (4, 3),  (4, 6),            -- grafovi (BFS), pretraga
    (5, 5),  (5, 7);            -- sortiranje, stringovi
