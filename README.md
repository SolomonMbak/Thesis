# Automated Class Timetabling System

A web application that builds clash-free class timetables for a university department.
Timetabling rules are modelled as a constraint satisfaction problem and solved by a
genetic algorithm with a targeted repair operator.

Software artefact for an M.Sc. dissertation (Information Systems and Technology,
Faculty of Computing, National Open University of Nigeria).

The full specification is in [BUILD_SPEC.md](BUILD_SPEC.md).

## Build status

| Increment | Scope | Status |
| --- | --- | --- |
| 0 | Foundation: core classes, sign-in, migrations, tests | Done |
| 1 | Installer and settings | Not started |
| 2 | Master data, sessions, feasibility | Not started |
| 3 | GA engine | Not started |
| 4 | Background runs and live progress | Not started |
| 5 | Timetables, reports, publishing, public view | Not started |
| 6 | Administration, backups, health | Not started |
| 7 | Experiment tooling | Not started |
| 8 | Hardening and release | Not started |

## Requirements

- PHP 8.2 or newer (8.3 recommended) with `pdo_mysql`, `mbstring`, `json`, `fileinfo`
- MySQL 8 or MariaDB 10.6 or newer
- Apache 2.4 (with `mod_rewrite`) or Nginx with PHP-FPM, or the PHP built-in server for development

No Composer packages and no build step.

## Quick start (development)

1. Create an empty database (utf8mb4), for example `timetable`.
2. Create `config/config.local.php` with your database details:

   ```php
   <?php
   return [
       'db_name' => 'timetable',
       'db_user' => 'root',
       'db_pass' => '',
       'app_env' => 'development',
       'test_db_name' => 'timetable_test', // optional, a separate empty database for tests
   ];
   ```

3. Create the tables and an administrator:

   ```
   php bin/migrate.php
   php bin/create_admin.php --email=you@example.edu --name="Your Name"
   ```

4. Start the development server and open http://localhost:8000:

   ```
   php -S localhost:8000 -t public
   ```

## Commands

| Command | Purpose |
| --- | --- |
| `php -S localhost:8000 -t public` | Development server |
| `php bin/migrate.php` | Apply pending database migrations (`--status` to list) |
| `php bin/create_admin.php` | Create an administrator, or reset one's password |
| `php tests/run.php [filter]` | Run the tests |

Commands for seeding, running jobs, the worker and packaging are added in later increments.

## Folder layout

| Folder | Contents |
| --- | --- |
| `public/` | Web root: front controller, assets, JSON endpoints. The only folder the web server exposes. |
| `src/Core/` | Framework pieces: config, database, router, sessions, CSRF, auth, validation, logging, audit, migrations |
| `src/Controllers/` | One controller per menu area |
| `src/Data/` | Database repositories |
| `src/Engine/` | Genetic algorithm (pure PHP, no database) |
| `src/Services/` | Session builder, feasibility checker, importers, job launcher and similar |
| `views/` | PHP templates; every value is escaped with `e()` |
| `bin/` | Command line scripts |
| `config/` | `config.php` defaults; `config.local.php` per server (not in releases) |
| `database/migrations/` | Numbered SQL files, applied once each, never edited after release |
| `storage/` | Logs, exports, backups, cache and sessions (must be writable, never web-accessible) |
| `tests/` | `run.php` test runner and `*Test.php` files |
| `docs/` | Deployment guide and user guide |

## Security summary

CSRF tokens on every POST, output escaping, prepared statements only, role checks on
every route, sign-in throttling (5 failures per 15 minutes per email and per IP),
secure session cookies with a 60 minute idle timeout, and security headers
(Content-Security-Policy, X-Frame-Options, nosniff, Referrer-Policy, HSTS on HTTPS).
See BUILD_SPEC section 13.
