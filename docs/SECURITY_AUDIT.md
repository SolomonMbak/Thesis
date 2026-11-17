# Security audit before going live

Date: 17 November 2026. Scope: the whole application (vanilla PHP 8.2+, MySQL/MariaDB, no Composer packages),
checked item by item against a 41-section PHP web application checklist. Every category was searched for
explicitly (grep for the risky patterns), and every fix was tested: 154 automated tests and live checks in
Chrome (`php tests/run.php`; browser scripts listed in section "How it was tested").

**Result: no Critical findings and nothing open in the code. 21 issues were found and fixed (1 High,
8 Medium, 12 Low).** Findings 18 to 21 (database credentials) were added after review on the same day. What remains are settings of the server and hosting account, which the app cannot set
for itself; they are listed in "Before going live" below and in docs/DEPLOYMENT.md section 5.1.

Status values in the tables: **Pass** (checked, nothing to fix), **Fixed** (found in this audit and fixed),
**Server** (a server or hosting setting, see "Before going live"), **N/A** (the app has no such feature).

## Findings and fixes

| # | Severity | Finding | File:Line | Fix (done) |
| --- | --- | --- | --- | --- |
| 1 | High | `bin/seed.php` could be run on a live server, creating demo accounts whose passwords are printed in the README | bin/seed.php:29 | Refuses unless `app_env` is development; Health warns if the demo accounts exist (src/Services/HealthService.php:97) |
| 2 | Medium | A password change or reset did not end sessions already open elsewhere (for example a stolen one) | src/Core/Auth.php:289 | Each session keeps a fingerprint of the password hash; any change signs out every other session |
| 3 | Medium | Invitation emails had no limit: anyone could sign up and use "Send again" to send mail to any address (spam relay) | src/Controllers/MemberController.php:62 | At most 30 invitations or additions per school and 3 invitations per address per hour |
| 4 | Medium | Password-reset emails were limited per network address only, so one inbox could be flooded from many addresses | src/Controllers/AuthController.php:276 | Also limited to 5 per hour per account |
| 5 | Medium | The SMTP password was stored in plain text, so it was inside every database backup | src/Controllers/PlatformController.php:113 | Stored encrypted with the server key; values saved earlier still work (src/Core/Mailer.php `smtpPassword`) |
| 6 | Medium | Deleting a school or handing it over needed only a click (and typing the link name), with no password | src/Controllers/SchoolController.php:317 | The password is asked again; a wrong one changes nothing and is logged |
| 7 | Medium | Behind a reverse proxy or CDN every visitor would have the proxy's address, so sign-in throttling could lock everyone out together | src/Core/Request.php:121 | New `trusted_proxies` setting; X-Forwarded-For is used only from those addresses, and only its last entry |
| 8 | Low | No `Permissions-Policy` header; PHP announced its version in `X-Powered-By` | src/Core/Response.php:31 | Both headers fixed for PHP pages and static files (public/.htaccess) |
| 9 | Low | Plain-HTTP requests were not sent to HTTPS by the app | src/Core/Response.php:46 | Redirect in production when `app_url` is https, built from `app_url`, never from the Host header |
| 10 | Low | Any unknown address (such as /backup.zip or /phpinfo.php) answered 200 with the home page | src/Core/Request.php:82 | Now 404 with the "Page not found" page (nothing leaked before; this stops scanners being misled) |
| 11 | Low | PUT, DELETE and other unused HTTP methods reached PHP | public/.htaccess:16 | Only GET, POST and HEAD are accepted (TRACE needs a server setting, see below) |
| 12 | Low | Uploads were opened without `is_uploaded_file()` | src/Services/CsvImporter.php:57, src/Controllers/SchoolController.php:332 | Checked for every CSV and logo upload |
| 13 | Low | bcrypt cost was PHP 8.2/8.3's default of 10 | src/Core/Auth.php:410 | Cost 12; older hashes are upgraded at the next sign-in |
| 14 | Low | Session and "Keep me signed in" cookies used path `/`, so other apps on the same domain received them | src/Core/Session.php:61 | Limited to the app's own folder |
| 15 | Low | No protection against sign-up bots beyond the hourly limit (outside CAPTCHA scripts are not allowed by the project rules) | views/auth/signup.php:24, src/Controllers/AuthController.php:191 | Hidden honeypot field; anything typed in it is refused |
| 16 | Low | PHP 8.2 stops getting security fixes on 31 December 2026 | src/Services/HealthService.php | Health now warns below 8.3; README and DEPLOYMENT say to use 8.3 or newer |
| 17 | Low | `session.use_trans_sid` was not explicitly off | src/Core/Session.php:29 | Set to 0 (session ids are never put in URLs) |
| 18 | Medium | The deployment guide told hosts to give the app's database user ALL PRIVILEGES, and said little about the password, the settings file or changing the password | docs/DEPLOYMENT.md sections 3.2, 3.6, 4.1, 4.5, 5.1, 7.1 | Exact least-privilege grant (11 rights on this database only, from localhost), generated random password, file owner and permissions, a copy kept safely, never reuse development credentials, how to change the password |
| 19 | Medium | With such a least-privilege user, MySQL 8's mysqldump stops with "you need the PROCESS privilege", so backups would have silently fallen back to the slower PHP export | src/Services/BackupService.php | `--no-tablespaces` added; the narrow grant was tested end to end (migrations, queries, mysqldump backup, restore) |
| 20 | Low | mysqldump received the database password in the MYSQL_PWD environment variable, which other processes of the same user can read and MySQL calls insecure | src/Services/BackupService.php | A temporary option file readable only by the app's user (umask 077, chmod 600), deleted as soon as the dump ends |
| 21 | Low | Nothing warned when the app connected as root, with an empty password, with server-wide rights, or when config.local.php was readable by everyone | src/Services/HealthService.php | Health checks "Database account" (root, empty password, any GRANT on *.*) and "Settings file" (permissions); the installer's form says to use the app's own user and a random password |

All 21 are covered by tests: tests/SecurityAuditTest.php (8 tests), the existing suites, and an end-to-end run as a
least-privilege MariaDB user (scratch script; the grant is the one in docs/DEPLOYMENT.md section 3.2).

## Before going live (server and hosting settings)

These cannot be fixed in the code. The full list with commands is in docs/DEPLOYMENT.md section 5.1.

1. Run `install.php` straight after uploading (until then anyone who finds it could install).
2. HTTPS with a certificate, and `app_url` set to the `https://` address.
3. `app_env = production`, PHP 8.3 or newer, `expose_php = Off`, `display_errors = Off`.
4. Apache: `ServerTokens Prod`, `ServerSignature Off`, `TraceEnable Off` (TRACE still answers 200 on the
   development server; Apache answers it before `.htaccess` rules run).
5. Database credentials: the app's own database user (never root) with only the 11 rights listed in
   DEPLOYMENT section 3.2 on this database, from localhost; a long random password used nowhere else and
   different from development; `config/config.local.php` readable only by the web server (640, or 600 on
   cPanel) with a copy kept in a password manager; MySQL listening on 127.0.0.1 only; phpMyAdmin or Adminer
   not open to the internet; `test_db_name` not set; the password changed if it may have leaked (section 7.1).
6. Never run `bin/seed.php` on the live server; delete any demo schools. Health must show no warnings.
7. Back up `storage/keys/app.key` (or set `app_key`) together with the daily backups, off the server.
8. Behind Cloudflare or a load balancer: set `trusted_proxies`.
9. SPF, DKIM and DMARC for the email domain.
10. Platform administrators turn on two-step sign-in (they can download every school's backup).

## Checklist, section by section

### 1. Injection

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| SQL injection | Pass | src/Data/TableRepository.php:24; src/Core/Db.php:41 | PDO with real prepared statements (emulation off). Every query that builds SQL text uses only code constants: table names from fixed lists, columns from page definitions, `LIMIT` cast to int (src/Data/AuditRepository.php:37). No request value reaches SQL text |
| Command injection | Pass | src/Services/JobLauncher.php:101; src/Controllers/PlatformController.php:97 | Every argument through `escapeshellarg`; job id cast to int; the PHP path setting (platform admins only) rejects shell characters; mysqldump uses an argument array and passes the password in an environment variable |
| Path traversal | Pass | src/Services/BackupService.php:17; src/Controllers/SchoolController.php:206; src/Core/View.php:44 | Backup names must match `backup_YYYYMMDD_HHMMSS.sql.gz`; logos are found from the school record with `basename`; templates and help files only from `[a-z0-9_/-]` names |
| LDAP injection | N/A | | No LDAP |
| XML / XPath injection | N/A | | No XML parsing anywhere |
| Header injection | Pass | src/Controllers/BackupController.php:49 | The only headers built from input use a pattern-checked backup name or a fixed template key |
| Email header injection | Pass | src/Core/Mailer.php:165 | `clean()` removes line breaks from every header value; recipients come from accounts, not free forms |

### 2. Output and rendering

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| Reflected XSS | Pass | all views | A scan of every `<?=` in views/ found none that print request data without `e()` (htmlspecialchars, ENT_QUOTES, UTF-8) |
| Stored XSS | Pass | all views | Database values are escaped the same way; the only raw output is code-built markup (icons, the QR code SVG, fixed tags) |
| DOM XSS | Pass | public/assets/js/app.js | No `innerHTML` with data (one `innerHTML = ''` to clear), no `document.write`, no `eval`; text is set with `textContent` |
| Content-Type and charset | Pass | | `text/html; charset=UTF-8`, `<meta charset="utf-8">`; JSON, CSV and images send their own types |
| Clickjacking | Pass | src/Core/Response.php | `X-Frame-Options: DENY` and CSP `frame-ancestors 'none'` |

### 3. Authentication

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| Password hashing | Fixed | src/Core/Auth.php:410 | `password_hash` (bcrypt), cost raised to 12 (finding 13) |
| Password length | Pass | src/Core/Auth.php | At least 10 characters, at most 200 (bcrypt reads the first 72 bytes; acceptable) |
| Brute force | Pass | src/Core/RateLimiter.php:38 | 5 failures per 15 minutes per email and per IP, for passwords and for two-step codes |
| User enumeration | Pass (accepted) | src/Core/Auth.php:38 | Sign-in and reset answer the same way, with equal timing (dummy hash). Sign-up says an email is taken (needed to be usable; limited to 5 per hour per IP) |
| Reset token | Pass | src/Data/TokenRepository.php:28 | `random_bytes(32)`, only the SHA-256 hash stored, 1 hour, single use; a reset now also ends all sessions and kept sign-ins |
| Remember me | Pass | src/Data/RememberTokenRepository.php:67 | Selector plus hashed validator, rotated on every use, fixed 30-day expiry, deleted on sign-out and password change |
| MFA / 2FA | Pass | src/Core/Auth.php:114; src/Core/Totp.php:47; src/Data/TwoFactorRepository.php:53 | RFC 6238, codes throttled, each code step used once, key encrypted, recovery codes hashed and single use |
| Timing attacks | Pass | src/Core/Csrf.php:40 | `hash_equals` for every secret comparison; tokens are looked up by hash |
| Type juggling | Pass | | One loose `==` in the whole code base, comparing two floats in the statistics module (not security) |
| Hard-coded credentials | Fixed | bin/seed.php:29 | Only the development seed (finding 1). `config.php` defaults are placeholders; `config.local.php` is never in releases |

### 4. Session management

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| HttpOnly | Pass | src/Core/Session.php | Session and remember cookies |
| Secure | Pass / Server | src/Core/Session.php | Set whenever the request is HTTPS; HTTPS itself is a server setting |
| SameSite | Pass | | Lax on both cookies |
| Regenerate on login and privilege change | Pass | src/Core/Auth.php | On sign-in, two-step step, school switch, role change and password change |
| Server-side logout | Pass | src/Core/Session.php | `session_destroy()` and the remember token row deleted |
| Timeout | Pass | src/Core/Session.php | 60 minutes idle, enforced on every request |
| Fixation / id in URL | Fixed | src/Core/Session.php:29 | `use_strict_mode`, `use_only_cookies`, and now `use_trans_sid = 0` |

### 5. Access control

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| IDOR | Pass | src/Data/TableRepository.php | Every tenant query adds `school_id = ?` from the session's SchoolContext; another school's id answers 404 (TenantIsolationTest) |
| Server-side role checks | Pass | src/Core/Router.php:58 | Each route declares its level; checked before the controller runs; JSON endpoints in Api::boot |
| Admin routes | Pass | src/routes.php | Platform pages need `is_platform_admin`; school admin pages need the admin role |
| Vertical escalation | Pass | src/Controllers/MemberController.php | The owner role cannot be given, changed or removed from Members; only the owner hands over (now with password) |
| Horizontal escalation | Pass | tests/TenantIsolationTest.php | Repositories, pages, API, exports and public pages tested across two schools |
| Mass assignment | Pass | src/Controllers/CrudController.php:199 | Only fields declared in code are read; linked levels and lecturers must belong to the same school; roles from a fixed list |

### 6. CSRF

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| Token on every POST | Pass | src/Core/Router.php:58 | Checked centrally for every POST route; no GET route changes data |
| Validated server-side | Pass | src/Core/Csrf.php:40 | 256-bit token per session, `hash_equals` |
| AJAX endpoints | Pass | src/Core/Api.php:46 | `X-CSRF-Token` header checked |
| SameSite | Pass | | Lax |

### 7. File uploads

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| Type by content | Pass | src/Controllers/SchoolController.php | `finfo` MIME check plus extension; SVG refused |
| Outside web root | Pass | src/Data/SchoolRepository.php:176 | storage/uploads/{school_id}, served by PHP with the checked MIME type and nosniff |
| Size limits | Pass | | Logo 512 KB, CSV 2 MB, checked server-side |
| Filename sanitised | Pass | | The stored name is always `logo.<ext>` from the detected type; the client's name is never used |
| Double extension | Pass | | Not possible for the same reason |
| Malware scan | N/A | | Only small images and CSV text are accepted |

### 8. Configuration and secrets

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| .env not web-accessible | Pass | public/.htaccess | No .env; dotfiles denied; tested over HTTP (403) |
| No secrets in source | Pass | | Only placeholders and the development seed; not a git repository yet, so no history to scan |
| Database credentials | Fixed / Server | src/Services/HealthService.php; docs/DEPLOYMENT.md 3.2 | Least-privilege user, random password, file permissions checked by Health, never in backups or on a command line (findings 18 to 21) |
| display_errors off | Pass | src/bootstrap.php:37 | Off unless development or CLI |
| No stack traces to users | Pass | public/index.php | Friendly error page; details only in storage/logs |
| .git not exposed | Pass | | Denied (403) |
| Backup files not in web root | Pass | | Backups in storage/backups (403 over HTTP); release zips in release/ are not served (404) |
| phpinfo() | Pass | | None in the code |
| Debug endpoints | Pass | src/Core/Config.php:27 | The test-database switch works only on PHP's built-in server in development |

### 9. Dependencies and environment

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| composer audit | N/A | | No Composer packages |
| PHP EOL | Fixed | src/Services/HealthService.php | 8.2 ends 31 December 2026; Health warns below 8.3 (finding 16) |
| Dev dependencies in build | Pass | bin/package.php | Release leaves out tests, storage contents, config.local.php |
| Typosquatting | N/A | | No packages |
| Dead code | Pass | | No unused vulnerable code found |

### 10. API and endpoints

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| Rate limiting | Pass / Server | | Sign-in, two-step, sign-up, reset, verification and invitations are limited. Public timetable pages are not; use the host's or CDN's rate limiting if needed |
| Every API route authenticated | Pass | src/Core/Api.php | All four JSON endpoints require sign-in and a role |
| CORS | Pass | | No CORS headers are sent at all |
| Old endpoints | Pass | | The old public link only shows a "Timetable moved" page; no versioned API |
| Pagination limits | Pass | src/Data/AuditRepository.php:37 | Fixed page sizes; no client-set limit |

### 11. Cryptography

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| No weak algorithms for security | Pass | | md5 only for cache-busting and change detection; SHA-256 for token hashes; HMAC-SHA1 only inside TOTP, as RFC 6238 requires |
| Keys not hard-coded | Pass | src/Core/Crypto.php:26 | `app_key` in config or storage/keys/app.key created with random_bytes |
| Keys not in the database | Pass | | The key file is outside the database and backups |
| CSPRNG | Pass | | random_bytes / random_int everywhere a secret is made |
| JWT | N/A | | Not used |

### 12. Deserialization

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| unserialize on input | Pass | | Not used anywhere |
| XXE | N/A | | No XML |
| phar:// | Pass | | No file path comes from input |

### 13. SSRF

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| Server-side fetches | Pass | src/Core/Mailer.php | The only outbound connection is to the SMTP server, set by platform administrators |
| Scheme whitelist | N/A | | No URL fetching |

### 14. Business logic

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| Client-side values | Pass | | All validation is repeated on the server |
| Workflow skipping | Pass | | Generate re-checks readiness; one active run per school; set-up steps are worked out from data |
| Race conditions | Pass | src/Data/JobQueue.php; src/Data/TwoFactorRepository.php:53 | Job claims under a database lock; a two-step code step is consumed atomically; recovery codes use a conditional update |
| Coupons | N/A | | No payments |

### 15. Third-party integrations

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| Webhooks, OAuth, API keys | N/A | | None. The only third party is Chart.js from cdnjs, with an integrity hash |

### 16. Client side

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| Logic only in JS | Pass | | JavaScript only shows and hides; every rule is on the server |
| Secrets in JS | Pass | | None |
| Tokens in localStorage | Pass | public/assets/js/app.js | Only the menu's open groups; no tokens |
| SRI on CDN scripts | Pass | views/generate/job.php | sha512 integrity hash, checked against the file |

### 17. Multi-tenancy

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| Every query filtered by school | Pass | tests/TenantIsolationTest.php | Enforced through SchoolContext repositories and tested |
| File storage per tenant | Pass | src/Data/SchoolRepository.php:176 | storage/uploads/{school_id} |
| Cache and queue keys | Pass | | No shared cache; each job carries its school and the runner builds the context from it |
| Cross-tenant ID test | Pass | tests/TenantIsolationTest.php | School A's session against school B's ids for every repository and page |

### 18. Server and transport

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| HTTPS enforced | Fixed / Server | src/Core/Response.php:46 | App redirects in production (finding 9); certificate is a server task |
| HSTS | Pass | src/Core/Response.php | max-age one year on HTTPS |
| CSP | Pass | src/Core/Response.php | Enforced (not report-only); no inline script except the theme script by hash |
| nosniff | Pass | | PHP pages and static files |
| Directory listing | Pass | public/.htaccess | `Options -Indexes` (tested: 403) |
| Host header | Pass | src/helpers.php:89 | Emailed links use only `app_url` in production; the new HTTPS redirect also uses `app_url` |
| .htaccess review | Fixed | public/.htaccess:16 | Methods limited; root fallback denies everything outside public/ (tested over HTTP) |

### 19. Denial of service

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| Upload size before processing | Pass | | PHP's limits and our size checks before reading |
| Decompression bombs | N/A | | No archives or images are processed |
| ReDoS | Pass | | Every pattern on input is short and anchored, without nested repetition |
| Expensive operations | Pass | | Timetable runs are queued with a platform-wide limit; one active run per school |

### 20. Payments

N/A: the app takes no payments.

### 21. Audit and logging

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| No secrets in logs | Pass | src/Controllers/PlatformController.php | Passwords, tokens and the SMTP password are never logged. Development mode writes emails (with links) to mail.log; production sends them |
| Admin actions logged | Pass | | Actor, time, school, target for data changes, runs, publishing, members, settings, backups, school deletion |
| Failed logins logged | Pass | src/Core/Auth.php | `auth.failed`, `auth.2fa_failed`, and now `school.reauth_failed` |
| Log tampering | Pass | | storage/ not web-accessible; the app has no feature that edits logs |
| PII in logs | Pass | | Email addresses and IPs only, in the school's own log |

### 22. Environment and deployment

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| Dev not public | Server | | Keep development copies off the internet |
| Separate secrets | Server | | Each install makes its own key and database password |
| Backups encrypted at rest | Server | | Backups are gzip SQL; keep them on encrypted storage (they hold password hashes, never the 2FA keys) |
| No production data in dev | Server | | Use bin/seed.php demo data for development |

### 23. Dangerous PHP functions

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| eval, assert, create_function | Pass | | None |
| Variable functions / variables with input | Pass | | None |
| extract() on input | Pass | src/Core/View.php:44 | Only on the template's own data array (EXTR_SKIP), never on request data |
| allow_url_include / fopen | Server | | Default off / not used for includes |
| expose_php | Fixed / Server | | Header removed by the app (finding 8); also set `expose_php = Off` |
| register_globals shims | Pass | | None |

### 24. HTTP methods and headers

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| TRACE | Server | | `TraceEnable Off` in Apache's configuration |
| Unused methods | Fixed | public/.htaccess:16 | 403 for anything but GET, POST, HEAD (tested) |
| OPTIONS | Fixed | | Refused by the same rule |
| Request smuggling | Server | | Only relevant behind a proxy; keep the proxy and Apache up to date |
| Referrer-Policy | Pass | | same-origin |
| Permissions-Policy | Fixed | src/Core/Response.php:31 | Camera, microphone, location, payment, USB off |

### 25. Redirects and responses

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| Open redirect | Pass | src/Controllers/Controller.php | Redirects take route names; the one "return" field is pattern-checked as a route |
| Post-login redirect | Pass | src/Controllers/AuthController.php | Only to invitation tokens of 64 hex characters or fixed pages |
| Content-Disposition | Pass | | File names are fixed, numeric or pattern-checked |
| Arbitrary download | Pass | src/Services/BackupService.php:17 | See section 1 |

### 26. Template and export injection

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| SSTI | N/A | | Plain PHP templates; user text is never evaluated |
| CSV formula injection | Pass | src/helpers.php:37 | `csv_text()` prefixes cells starting with = + - @ tab or CR |
| XML export | N/A | | None |

### 27. File and media processing

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| GD / Imagick | N/A | | Logos are stored as uploaded, never processed |
| EXIF | Pass (accepted) | | Logos are public school branding; GPS data in a logo is unlikely. Strip it before uploading if unsure |
| PDF generation | N/A | | Printing uses the browser |
| TOCTOU | Pass | | The type is checked on the uploaded temp file, then moved with `move_uploaded_file` |
| is_uploaded_file | Fixed | src/Services/CsvImporter.php:57 | Finding 12 |
| Temp files | Pass | | Only PHP's own upload temp files |

### 28. Enumeration and IDs

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| Sequential IDs | Pass (accepted) | | Numeric ids are always checked against the school, so enumeration finds nothing. Public pages show published timetables only, which are public by design |
| UUIDs | Not needed | | Invitation, reset and remember tokens are random 256-bit values |

### 29. Infrastructure exposure

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| Database port | Server | | Bind to 127.0.0.1 and close 3306 in the firewall; a remote database only over a private network with TLS |
| Redis / Memcached | N/A | | Not used |
| Admin panel | Pass | | Platform pages need a platform administrator account (two-step sign-in recommended) |
| Server header | Fixed / Server | | X-Powered-By removed; `ServerTokens Prod` for Apache's own header |

### 30. Client-side supply chain

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| npm audit | N/A | | No JavaScript build; app.js and progress.js are our own |
| Third-party scripts | Pass | | Only Chart.js 4.4.1 from cdnjs with SRI, on the run progress page |
| jQuery etc. | N/A | | None |

### 31. Credential lifecycle

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| Token expiry | Pass | | Reset 1 hour, verification and invitations 7 days, remember 30 days |
| bcrypt cost | Fixed | src/Core/Auth.php:410 | 12 |
| Recoverable passwords | Pass | | Never stored or shown; temporary passwords are shown once to the administrator who typed them |
| Rotation after exposure | Server | | Change `app_key` only together with turning two-step sign-in off and on again for users (it encrypts their keys) |

### 32. Automated abuse

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| CAPTCHA | Fixed (honeypot) | views/auth/signup.php:24 | Outside CAPTCHA scripts are not allowed by the project rules; honeypot plus hourly limits |
| Reset-request limit | Fixed | src/Controllers/AuthController.php:276 | Per IP and per account |
| Email-sending limits | Fixed | src/Controllers/MemberController.php:62 | Invitations, resets and verification emails all limited |

### 33. Installer and deployment artifacts

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| Installer locked | Pass / Server | public/install.php | Refuses after success (tested: 403). Run it immediately after upload |
| Default admin | Pass | | The installer asks for the administrator's own email and password; no default account |
| Cron scripts over HTTP | Pass | | bin/ is outside the web root (403 with the fallback) and every script refuses non-CLI use |
| DB admin tools | Server | | See "Before going live" |
| Sample files | Pass | | None shipped |
| robots.txt | Pass | | None; nothing sensitive is listed anywhere |

### 34. Type juggling

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| strcmp with arrays | Pass | | Only in sorting backup names |
| Token comparisons | Pass | | `hash_equals` and strict `===` |
| PHP_SELF | Pass | | Never used |
| Language file inclusion | N/A | | No language files |

### 35. Caching and CDN

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| Cache deception | Pass | public/index.php | Every page sends `Cache-Control: no-store`; unknown paths are now 404 |
| Personalised cache keys | Pass | | No server cache of pages |
| CDN caching cookies | Pass | | no-store on every PHP page; only static files and public logos are cacheable |

### 36. Network trust

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| X-Forwarded-For | Fixed | src/Core/Request.php:121 | Ignored unless the sender is in `trusted_proxies` |
| Spoofing to bypass limits | Pass | | Same |
| Internal endpoints | Pass | | None; CLI scripts are not reachable over HTTP |

### 37. Real-time features

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| WebSockets / SSE | N/A | | Progress uses ordinary polling of an authenticated, school-checked JSON endpoint |

### 38. Workflow and state

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| Step order | Pass | | Server decides readiness and job states |
| Deleted records | Pass | | Deletes are real deletes; archived sessions stay readable only through their own school's runs |
| Impersonation | N/A | | No impersonation feature; platform administrators cannot open a school unless invited |

### 39. DNS and domain

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| Dangling DNS | Server | | Check when the domain is chosen |
| Cookie Domain | Pass | | No Domain attribute (host-only), path limited to the app (finding 14) |
| SPF / DKIM / DMARC | Server | | See "Before going live" |

### 40. Package integrity

N/A: no Composer or npm packages are used by the application.

### 41. Miscellaneous PHP

| Item | Status | File:Line | Notes |
| --- | --- | --- | --- |
| mail() additional headers | Pass | src/Core/Mailer.php:165 | Built from cleaned values only |
| GraphQL | N/A | | Not used |

## How it was tested

- `php tests/run.php`: 154 tests pass, including tests/SecurityAuditTest.php (trusted proxies, encrypted SMTP
  password, headers and 404s, password change signing out other sessions, honeypot and invitation limits,
  password needed to delete a school, the Health warning for a weak database account, and no database password
  on a command line or in the environment), TenantIsolationTest, LoginSecurityTest and AccountsTest.
- As a least-privilege MariaDB user with exactly the grant in DEPLOYMENT section 3.2 and a password containing
  a quote, a backslash, # and ;: both migrations apply, the run-queue lock works, mysqldump makes the backup
  through the option file (deleted afterwards, password not in the dump), the dump restores into an empty
  database, and Health reports the account as OK.
- Over HTTP against Apache: sensitive paths (config, storage, keys, logs, backups, bin, src, tests, .git,
  .env, release zips) all answer 403 or 404; PUT is refused; headers checked with curl.
- In Chrome (playwright-core): all browser suites (home, e2e, e2e2, month_ga, length, security, nudge,
  audit) pass with no console errors. audit.js checks the honeypot is invisible and out of the tab order,
  unknown addresses show "Page not found", cookies are scoped and HttpOnly, a wrong password does not delete
  a school, and Health shows the new checks.
