# CLAUDE.md

Read `BUILD_SPEC.md` fully before writing any code. It is the single source of truth: product spec, database schema, GA design, build increments, deployment and experiments. If code and spec disagree, the spec wins. If the spec is unclear or cannot work on the target host, stop and ask. Do not invent behaviour.

## What we are building

A deployable, production-ready web application: an automated class timetabling system for a university department. Timetabling rules are modelled as a constraint satisfaction problem and solved by a genetic algorithm with a targeted repair operator. It is also the software artefact for an M.Sc. dissertation (Information Systems and Technology, Faculty of Computing, National Open University of Nigeria), so code clarity matters: parts of it will be printed in the appendix.

## Stack (fixed)

- PHP 8.2+ (target 8.3), vanilla. No framework, no Composer runtime packages.
- MySQL 8 or MariaDB 10.6+, InnoDB, utf8mb4, PDO prepared statements only.
- HTML, CSS, plain JavaScript. No build step. Chart.js from cdnjs is the only external script.
- Must run on both a Linux VPS and cPanel shared hosting.

## Non-negotiable rules

1. Only `public/` is web-accessible. Everything else lives outside the web root (with the documented `.htaccess` fallback for hosts that cannot change document root).
2. The GA never runs inside a web request. Web creates a job; a CLI process runs it (exec mode or queue mode, see BUILD_SPEC section 9).
3. `src/Engine/` is pure PHP: no database, sessions, superglobals or output. Only `bin/run_ga.php` connects engine and database.
4. All randomness through one seeded `App\Engine\Rng`. Same seed + data + settings = same result.
5. Security on every page: CSRF on all POSTs, `e()` for all output, prepared statements, role checks, login throttling, secure session cookies, security headers.
6. No hard-coded weights or GA parameters in engine code.
7. Schema changes only through numbered migration files in `database/migrations/` applied by `bin/migrate.php`. Never edit an applied migration.
8. Every increment ships with the tests listed in BUILD_SPEC and `php tests/run.php` must be green before moving on.
9. User-facing text in plain English. No em dashes anywhere in UI text or docs.
10. Small functions, clear names, comments that explain why.

## Commands

- Dev server: `php -S localhost:8000 -t public`
- Migrate: `php bin/migrate.php`
- Seed demo data: `php bin/seed.php`
- Tests: `php tests/run.php`
- Run a job by hand: `php bin/run_ga.php <job_id>`
- Queue worker: `php bin/worker.php` (loop) or `php bin/worker.php --once` (cron)
- Release zip: `php bin/package.php`

## How to work

- Build one increment at a time (BUILD_SPEC section 12). Finish its checks before the next.
- Before a large change, state the plan in a few lines. After it, say what changed and how you tested it.
- Keep `README.md` and `docs/DEPLOYMENT.md` up to date as you build.
