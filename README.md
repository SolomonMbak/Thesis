# Automated Class Timetabling System

A web application that builds clash-free class timetables. Any school or department can sign up and get its
own private workspace; a Setup Guide and in-app help take a first-time user from an empty school to a
published timetable. Timetabling rules are modelled as a constraint satisfaction problem and solved by a
genetic algorithm with a targeted repair operator.

Software artefact for an M.Sc. dissertation (Information Systems and Technology, Faculty of Computing,
National Open University of Nigeria).

| Document | For |
| --- | --- |
| [BUILD_SPEC.md](BUILD_SPEC.md) | The specification: data model, constraints, GA design, experiments |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | Installing on a Linux VPS or cPanel shared hosting |
| [docs/USER_GUIDE.md](docs/USER_GUIDE.md) | School owners, administrators and officers: the Setup Guide and each part of the system |
| Home page (`/`) and About page (`?r=about`) in the app | Anyone: what the app does, at first glance |
| Help Centre (in the app, `/help`) | Everyone, including visitors before they sign up |

## Build status

| Increment | Scope | Status |
| --- | --- | --- |
| 0 | Foundation: core classes, sign-in, migrations, tests | Done |
| 1 | Installer and settings | Done |
| 2 | Master data, sessions, feasibility, CSV import, demo seed | Done |
| 3 | GA engine with targeted repair | Done |
| 4 | Background runs and live progress | Done |
| 5 | Timetables, reports, publishing, public view, manual scoring | Done |
| 6 | Administration, backups, health | Done |
| 7 | Experiment tooling | Done |
| 8 | Hardening, documentation, release packaging | Done |
| Retrofit | Multi-school workspaces, sign-up and invitations, Setup Guide and in-app help, dark mode, `/s/{slug}` public pages | Done |
| 10.7 | Monthly calendar view: semester dates, holidays, Week / Month switch, export with dates | Done |

## Requirements

- PHP 8.2 or newer (8.3 recommended) with `pdo_mysql`, `mbstring`, `json`, `fileinfo` (and `zip` to build releases)
- MySQL 8 or MariaDB 10.6 or newer
- Apache 2.4 (with `mod_rewrite`) or Nginx with PHP-FPM; the PHP built-in server for development

No Composer packages, no build step. Chart.js (from cdnjs, with a Subresource Integrity hash) is the only external script.

## Quick start (development)

1. Create an empty database (utf8mb4), for example `timetable`, and a second empty one for the tests,
   for example `timetable_test`.
2. Either open `install.php` in the browser (it writes the settings, creates the platform administrator and
   optionally a first school, then locks itself), or create `config/config.local.php` by hand:

   ```php
   <?php
   return [
       'db_name' => 'timetable',
       'db_user' => 'root',
       'db_pass' => '',
       'app_env' => 'development',     // emails go to storage/logs/mail.log instead of being sent
       'test_db_name' => 'timetable_test',
   ];
   ```

   and run:

   ```
   php bin/migrate.php
   php bin/create_admin.php --email=you@example.edu --name="Your Name"
   echo installed > storage/installed.lock
   ```

3. Load the two demo schools and start the server:

   ```
   php bin/seed.php
   php -S localhost:8000 -t public
   ```

4. Open http://localhost:8000 and sign in. The demo school (`/s/demo`) is owned by the first platform
   administrator; a second school, Test College (`/s/test-college`, owner `owner@testcollege.example.edu`,
   password `testcollege123`), has overlapping codes so you can see that schools never see each other's data.
   Or press **Create a school account** to try the Setup Guide from an empty school.

## How the system is organised

- **Platform** (one installation): platform administrators manage the list of schools, platform settings
  (sign-up open or invite-only, email, how runs start, how many run at once), health and backups. They do not
  see inside a school unless they are invited to it.
- **Schools** (tenants): every school has its own rooms, levels, lecturers, courses, periods, rules, runs,
  members and public link. Roles inside a school: owner, administrator, timetable officer, viewer.
- **Isolation**: every tenant table has a `school_id`. All tenant data goes through repositories and
  services that take a `SchoolContext` (resolved from the session and checked against the membership on
  every request) and add `school_id = ?` to every query. A record of another school answers 404.
  `tests/TenantIsolationTest.php` proves it for every repository, page, API endpoint, export and public page.

## Commands

| Command | Purpose |
| --- | --- |
| `php -S localhost:8000 -t public` | Development server |
| `php bin/migrate.php [--status]` | Apply (or list) database migrations |
| `php bin/create_admin.php [--school=slug --role=owner]` | Create a platform administrator, or reset one's password |
| `php bin/seed.php [--force] [--owner=email]` | Demo schools `demo` and `test-college` (fixed seed). `--force` recreates only those two |
| `php bin/engine_demo.php --school=demo [--generations=300] [--greedy=0]` | Run the GA with repair off and on and print results |
| `php bin/run_ga.php <job_id>` | Run one queued job by hand |
| `php bin/worker.php` / `--once [--max-jobs=N]` | Queue worker for every school: as a service, or from cron |
| `php bin/backup.php [--php]` | Backup of the whole database to storage/backups |
| `php bin/experiment.php --school=demo --label=RGA --repair=1 --seeds=1001-1030` | Experiment batch (Chapter Four) |
| `php bin/export_results.php --school=demo --label=RGA` | Export runs and the median convergence curve to CSV |
| `php bin/stats.php --school=demo --a=SGA --b=RGA [--man] [--csv]` | Descriptive statistics and Mann-Whitney U |
| `php bin/package.php` | Build the release zip |
| `php tests/run.php [filter]` | Run the tests |

`--school` can be left out when the installation has only one school.

## Tests

`php tests/run.php` runs 136 tests: CSRF, validation, sign-in throttling, migrations, session building,
feasibility rules, CSV import, every constraint of the fitness function on hand-built cases, the repair
operator, GA operators and determinism, the job lifecycle and the run queue across schools, grids, manual
scoring, backups, statistics, the monthly calendar, the Setup Guide rules, tenant isolation (repository level and over HTTP), and
sign-up, verification, invitations, roles and password reset through the real pages. Tests that need MySQL
use `test_db_name` (every table in it is dropped) and are skipped when it is not set. The HTTP tests start
their own `php -S` with `TT_TEST_DB=1`, which only the built-in server in development mode honours.

## Folder layout

| Folder | Contents |
| --- | --- |
| `public/` | Web root: front controller, installer, JSON endpoints, CSS and JavaScript. The only folder the web server exposes |
| `src/Core/` | Config, database, router, request and response, views, sessions, CSRF, auth, school context, mailer, theme, validation, logging, audit, migrations |
| `src/Engine/` | Genetic algorithm in pure PHP: no database, sessions or output |
| `src/Services/` | Session builder, feasibility, problem loader, job launcher and runner, Setup Guide, importers, timetable reports, account emails, backups, health, statistics |
| `src/Data/` | Repositories; every tenant repository takes a `SchoolContext` |
| `src/Controllers/` | One controller per area |
| `views/` | PHP templates; every value is escaped with `e()`. `views/help/` holds the help panel of every page |
| `bin/` | Command line scripts |
| `config/` | `config.php` defaults; `config.local.php` per server (not in releases) |
| `database/migrations/` | Numbered SQL files, each applied once. Before the first release the whole schema is in `001_init.sql`; later changes go in `002_...` and are never edited once released |
| `storage/` | Logs, exports, backups, uploads (school logos), cache and sessions; writable, never web-accessible |
| `tests/` | `run.php` test runner and `*Test.php` files |
| `docs/` | Deployment guide and user guide |

## How a run works

1. Generate checks readiness (the feasibility check, after bringing the class sessions up to date with the
   courses), refuses a second active run for the same school and inserts a `queued` job. The GA never runs
   inside a web request.
2. In **exec mode** the web app starts `bin/run_ga.php <id>` as a background process when fewer than
   `max_concurrent_jobs` runs are going; in **queue mode** `bin/worker.php` (a systemd service or a cron job
   every minute) picks it up. Waiting runs of all schools are taken oldest first; the run page shows
   "Your run is number N in the queue".
3. The runner claims the job atomically, builds the school context from the job, copies the constraint
   weights into the job, loads the semester into `ProblemData` and runs the engine, writing a progress row per
   generation and checking for a stop request every 10 generations.
4. The best timetable and its scores are saved in one transaction. In exec mode the finishing run starts the
   next waiting one. A run that shows no sign of life for 10 minutes is marked failed.

## Security summary

Tenant isolation (above), CSRF tokens on every POST (form field or `X-CSRF-Token` header), output escaping,
prepared statements only, role checks on every route (school roles and platform administrators), sign-in
throttling (5 failures per 15 minutes per email and per IP), throttled sign-up and password-reset requests,
secure session cookies with a 60 minute idle timeout and a new session id on sign-in, school switch and role
change, one-time hashed tokens for email verification, password reset and invitations, emailed links built
only from `app_url` in production, security headers (Content-Security-Policy with no inline scripts except
the theme script allowed by its hash, X-Frame-Options, nosniff, Referrer-Policy, HSTS on HTTPS), upload checks
(size, extension, MIME type; logos stored under `storage/uploads/{school_id}`), escaped shell arguments, CSV
formula neutralising in exports, an audit log per school, and an installer that locks itself.
See BUILD_SPEC section 13.
