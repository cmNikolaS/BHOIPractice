# BHOI Arhiva

**Arhiva i platforma za vježbu** zadataka sa takmičenja iz informatike u Bosni i
Hercegovini (jBHOI, BHGOI, državno BHOI i kvalifikaciona takmičenja).
Sadrži **301 zadatak** iz perioda **2003–2026**, sa tekstovima, službenim
rješenjima i mogućnošću pokretanja i ocjenjivanja vlastitog koda u pregledniku.

🌐 **Live:** https://bhoipractice.fly.dev

---

## Sadržaj

- [Funkcije](#funkcije)
- [Tehnologije](#tehnologije)
- [Struktura projekta](#struktura-projekta)
- [Lokalno pokretanje](#lokalno-pokretanje)
- [Baza podataka i uvoz zadataka](#baza-podataka-i-uvoz-zadataka)
- [Online judge (Judge0)](#online-judge-judge0)
- [Korisnički nalozi](#korisnički-nalozi)
- [Admin panel](#admin-panel)
- [Deploy na Fly.io](#deploy-na-flyio)
- [Sigurnost](#sigurnost)
- [Izvor podataka](#izvor-podataka)

---

## Funkcije

- **Katalog zadataka** sa trenutnim (client-side) filtriranjem: pretraga po
  nazivu, status (riješeni/neriješeni), težina, nivo takmičenja, godina i
  **algoritamska kategorija** (DP, matematika, grafovi, greedy, stringovi…).
- **Sortiranje** po težini, godini ili nazivu.
- **Numerička težina 1–10** (vlastita procjena po zadatku, ne po rundi) iz koje
  se izvodi bedž **Lako / Srednje / Teško**. Kategorije i težine postavljene za
  **275/301 zadatka (91%)** čitanjem teksta svakog zadatka.
- **Prsten napretka** (LeetCode stil): broj riješenih u sredini + lukovi po
  težini sa bojama i brojem riješenih po svakoj težini.
- **Zadaci dana**: po jedan zadatak iz svake težine, biraju se deterministički
  po danu (svima isti, mijenjaju se u ponoć), neovisno o tome jesu li riješeni.
- **Tekst zadatka** u Markdownu (LaTeX/KaTeX matematika, slike); za starije
  zadatke tekst je izvučen iz PDF-a (`pdftotext`). PDF se može otvoriti/skinuti.
- **Službena rješenja** (C++, Pascal…) sa pregledom koda i syntax highlightingom.
- **Pokreni kod** (Judge0): kompajliranje i pokretanje C++ koda na vlastitom
  ulazu, direktno u pregledniku.
- **Ocijeni** (Judge0): pokretanje protiv službenih test primjera i prikaz
  **koliko je test primjera prošlo** (verdikt + rezultat po testu).
- **Korisnički nalozi**: jednostavna prijava (username + lozinka); napredak se
  sinhronizuje na nalog i prati korisnika na svim uređajima (gosti koriste
  localStorage).
- **Tamna/svijetla tema** (LeetCode-style), zadana je tamna.

## Tehnologije

- **PHP 8** + PDO (bez framework-a)
- **Baza:** MySQL/MariaDB (lokalno) ili **SQLite** (produkcija) — isti kod,
  bira se preko `DB_DRIVER`
- **Frontend:** Tailwind (Play CDN) + vanilla JS, KaTeX, marked.js, DOMPurify,
  highlight.js
- **Online judge:** hostani [Judge0](https://judge0.com) (Extra CE preko RapidAPI)
- **Deploy:** Docker (php:8.2-apache) na [Fly.io](https://fly.io)

## Struktura projekta

```
index.php            Katalog + filteri + prsten napretka + zadaci dana
task.php             Stranica zadatka: tekst, rješenja, "Riješi zadatak" panel
download.php         Sigurno serviranje PDF/test/rješenja (bez path-traversal)
solution_raw.php     Sirovi izvor rješenja (za modal pregled)
submit.php           Judge0 endpoint: mode=run (vlastiti ulaz) / mode=judge (testovi)
progress.php         Sinhronizacija napretka po korisniku (JSON)
login.php register.php logout.php   Korisnički nalozi
admin_*.php          Admin: prijava, dashboard (CRUD), snimanje/brisanje zadatka

includes/
  bootstrap.php      Sesija + pomoćne funkcije (escape, CSRF, flash, bedževi)
  auth.php           Admin autentikacija
  userauth.php       Korisnička autentikacija
  judge.php          Judge0 klijent + dohvat test primjera s GitHuba
  uploads.php        Validacija i snimanje uploadovanih fajlova
  header.php footer.php theme_head.php   Zajednički layout/tema
  admin_header.php admin_footer.php

assets/app.js        Filtriranje, sortiranje, prsten, napredak

schema.sql           MySQL/MariaDB shema (+ seed.sql)
schema.sqlite.sql    SQLite shema (produkcija)

import_bhoi.php      Uvoz modernih zadataka (2022–2026, task.yaml format)
import_legacy.php    Uvoz starih zadataka (2003–2021, razni formati)
classify_tasks.php   Težina + kategorije za moderne (čitanjem teksta)
classify_legacy.php  Težina + kategorije za stare (čitanjem teksta/koda)
import_tests.php     Mapiranje zadatak → in/out test primjeri (putanje)
pdf_to_markdown.php  Izvlačenje teksta iz PDF-a u tasks.statement

Dockerfile docker-entrypoint.sh fly.toml   Produkcijski build/deploy
preview.bat preview.ps1 stop-preview.*     Lokalni preview (Windows)
JUDGE.md             Detalji o online judge-u i planu faze 2
```

## Lokalno pokretanje

**Najlakše (Windows):** dvoklik na `preview.bat`. Skripta:
1. napravi privatnu MariaDB bazu na portu **3307** (ne dira tvoj XAMPP 3306),
2. učita `schema.sql` + `seed.sql` prvi put,
3. pokrene PHP server na **http://localhost:8000** i otvori preglednik.

Zaustavljanje: zatvori prozor pa pokreni `stop-preview.bat`.

> Preduslov: XAMPP (PHP 8 + MariaDB) instaliran na `C:\xampp` (putanju mijenjaš
> na vrhu `preview.ps1`). Za izvlačenje teksta iz PDF-a treba i `pdftotext`
> (poppler-utils).

Da napuniš puni katalog lokalno (umjesto seed-a), pokreni uvoznike (vidi dolje).

## Baza podataka i uvoz zadataka

Dvojni drajver preko `config.php` / env varijable `DB_DRIVER`:
- `mysql` (zadano) — lokalno/XAMPP/preview,
- `sqlite` — produkcija (`DB_SQLITE_PATH`).

Lanac uvoza (pokreće se pri Docker build-u, a na postojeću bazu ručno):

```bash
php import_bhoi.php       # moderni zadaci 2022-2026 (+ PDF, rjesenja, tekst)
php classify_tasks.php    # tezina 1-10 + algoritamske kategorije (moderni)
php import_legacy.php     # stari zadaci 2003-2021 (dodaje, ne brise)
php classify_legacy.php   # tezina + kategorije (stari)
php import_tests.php      # mapiranje in/out test primjera
php pdf_to_markdown.php    # tekst zadatka iz PDF-a
```

Uvoznici su **idempotentni** i čuvaju ručne izmjene gdje je moguće. Test
primjeri (~1GB) se **ne skladište** — čuvaju se samo putanje, a fajlovi se
dohvataju s GitHuba na zahtjev pri ocjenjivanju.

## Online judge (Judge0)

Pokretanje i ocjenjivanje koda ide preko **hostanog Judge0** servisa.
Funkcija je **ugašena dok se ne postavi `JUDGE0_URL`** (tada se ništa o njoj ne
prikazuje). Konfiguracija preko env varijabli:

```
JUDGE0_URL   = https://judge0-extra-ce.p.rapidapi.com
JUDGE0_KEY   = <rapidapi-key>
JUDGE0_HOST  = judge0-extra-ce.p.rapidapi.com
JUDGE0_MAX_TESTS = 20   # limit testova po ocjenjivanju
```

- **Jezik:** C++ (Extra CE = Clang).
- **Rate-limit:** 5 pokretanja po korisniku (po IP-u, rolling 24h); **admini su
  izuzeti**.
- **CPU limit:** vremensko ograničenje zadatka, inače 1s.

Detalji i plan za fazu 2 (ocjenjivanje protiv svih testova, self-host Judge0) su
u [`JUDGE.md`](JUDGE.md).

## Korisnički nalozi

Prijava nije obavezna. Bez prijave napredak se čuva po pregledniku
(`localStorage`). Sa nalogom (`register.php` / `login.php`) napredak se
sinhronizuje na server (`user_progress`) i prati korisnika na svim uređajima.

## Admin panel

`admin_login.php` → `admin_dashboard.php` (CRUD zadataka, upload PDF/rješenja,
težina, kategorije). Zadani nalog: **`admin` / `admin123`** — **obavezno
promijeni nakon prve prijave.**

## Deploy na Fly.io

Build (php:8.2-apache + SQLite + poppler) "zapeče" cijeli katalog u sliku; na
prvom boot-u `docker-entrypoint.sh` napuni trajni `/data` volumen i radi
idempotentne migracije. Deploy:

```bash
flyctl deploy --remote-only
flyctl secrets set JUDGE0_URL=... JUDGE0_KEY=... JUDGE0_HOST=...
```

Pošto se volumen seeduje samo prvi put, nakon izmjena podataka pokreni
odgovarajući uvoznik na živom volumenu, npr.:

```bash
flyctl ssh console -C "php /var/www/html/import_tests.php"
```

## Sigurnost

- PDO **prepared statements** svuda,
- **CSRF** tokeni na svim formama/POST endpointima,
- **bcrypt** lozinke (admin i korisnici),
- validacija upload-a (ekstenzija + MIME + veličina), nasumična imena fajlova,
  `/uploads/.htaccess` blokira izvršavanje,
- judge kredencijali samo u env/Fly secrets, nikad u repou.

## Izvor podataka

Zadaci su preuzeti iz zvaničnog repozitorija
[github.com/BHOI/BHOI-takmicenja-iz-informatike](https://github.com/BHOI/BHOI-takmicenja-iz-informatike).
Sva autorska prava na zadatke i test primjere pripadaju njihovim autorima i
organizatorima BHOI takmičenja. Ovaj projekat je nekomercijalna arhiva za
vježbu.
