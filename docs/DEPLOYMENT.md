# Deployment guide

This guide is completed in build increment 8. Until then it records the facts each
increment establishes, so nothing is lost.

## What must be true on every server

- Only the `public/` folder is reachable from the web. Everything else (`config/`,
  `src/`, `storage/`, `bin/`, `database/`, `tests/`) stays outside the web root.
- `storage/` and its subfolders are writable by the web server user and by the user
  that runs the command line scripts. Sessions are kept in `storage/cache/sessions`.
- `config/config.local.php` holds the server's settings. Restrict it to the owner and
  the web server group (permissions 640) where the host allows.
- Set `app_env` to `production` so error details are never shown to visitors.
  Errors are written to `storage/logs/app-YYYY-MM-DD.log`.

## Configuration keys (config/config.local.php)

| Key | Meaning | Default |
| --- | --- | --- |
| `db_host`, `db_port`, `db_name`, `db_user`, `db_pass` | Database connection | 127.0.0.1, 3306, timetable, root, (blank) |
| `app_url` | Full public URL, e.g. `https://timetable.example.edu`. Leave blank to detect | (blank) |
| `app_env` | `production` or `development` | production |
| `timezone` | PHP and database time zone | Africa/Lagos |
| `php_cli_path` | Path to the PHP command line program | php |
| `memory_limit_cli` | Memory limit for timetable runs | 512M |
| `session_idle_minutes` | Sign out after this many idle minutes | 60 |
| `login_max_failures`, `login_window_minutes` | Sign-in throttling | 5, 15 |
| `test_db_name` | Separate empty database for the tests (development only) | (blank) |

## Database

Create an empty database with the `utf8mb4` character set, then run
`php bin/migrate.php`. Run the same command after every update. Each file in
`database/migrations/` is applied once and recorded in `schema_migrations`.

Create the first administrator with `php bin/create_admin.php`.

## Hosts that cannot change the document root

If the whole project has to sit inside the web root (for example
`public_html/timetable`), keep the `.htaccess` file in the project root. It sends
every request into `public/` and refuses everything else. Each private folder also
has its own `.htaccess` that denies all access, as a second line of defence.
This needs Apache with `mod_rewrite`. Without it, the root `.htaccess` denies
everything, so the site shows an error instead of exposing files.

## Path A: Linux VPS

To be written in increment 8 (BUILD_SPEC section 14.1).

## Path B: cPanel shared hosting

To be written in increment 8 (BUILD_SPEC section 14.2).

## Updating

1. Back up the database.
2. Upload the new code, keeping `config/config.local.php` and `storage/`.
3. Run `php bin/migrate.php`.
