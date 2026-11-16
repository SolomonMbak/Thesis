# Deployment guide

This guide takes you from a release zip to a working timetabling system. Follow
**Path A** for a Linux VPS (recommended) or **Path B** for cPanel shared hosting.
No programming knowledge is needed, but you need to be able to upload files and,
for Path A, type commands on the server.

Contents

1. What you need
2. Build the release zip
3. Path A: Linux VPS
4. Path B: cPanel shared hosting
5. After installing (both paths)
6. Updating to a new version
7. Backups and restoring
8. Configuration reference
9. Troubleshooting

---

## 1. What you need

| Item | Minimum |
| --- | --- |
| PHP | 8.2 or newer (8.3 recommended) with `pdo_mysql`, `mbstring`, `json` and `fileinfo` |
| Database | MySQL 8 or MariaDB 10.6 or newer, an empty database with the `utf8mb4` character set |
| Web server | Apache 2.4 with `mod_rewrite`, or Nginx with PHP-FPM |
| Server size | 1 vCPU and 1 GB RAM is enough for one department |
| Background runs | Either the PHP `exec` function (VPS) or a cron job every minute (shared hosting) |

The most important rule: **only the `public/` folder may be reachable from the web.**
Everything else (settings with the database password, logs, backups, source code)
must stay outside the web root.

## 2. Build the release zip

On the development computer, in the project folder:

```
php bin/package.php
```

This writes `release/timetable-YYYYMMDD-HHMMSS.zip`. The zip contains a single
folder named `timetable/` and leaves out tests, your local settings file and any
logs or backups.

---

## 3. Path A: Linux VPS (recommended)

These steps assume Ubuntu 22.04 or newer. Run them as a user with `sudo`.

### 3.1 Install the software

```
sudo apt update
sudo apt install apache2 mysql-server php8.3 php8.3-cli php8.3-mysql php8.3-mbstring libapache2-mod-php8.3 unzip
sudo a2enmod rewrite headers
```

(If your Ubuntu has a different PHP version, use that number instead of 8.3. PHP 8.2 or newer is fine.)

### 3.2 Create the database

```
sudo mysql
```

Then, inside MySQL (choose your own strong password):

```
CREATE DATABASE timetable CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'timetable'@'localhost' IDENTIFIED BY 'choose-a-strong-password';
GRANT ALL PRIVILEGES ON timetable.* TO 'timetable'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 3.3 Upload and unpack the code

Copy the zip to the server (for example with `scp` or an SFTP program such as
FileZilla), then:

```
sudo mkdir -p /var/www
sudo unzip timetable-*.zip -d /var/www/
sudo chown -R $USER:www-data /var/www/timetable
sudo chmod -R 750 /var/www/timetable
sudo chmod -R 770 /var/www/timetable/storage /var/www/timetable/config
```

The code now lives in `/var/www/timetable`. It is owned by you (the deploy user),
and the web server (`www-data`) can read it and write only to `storage/` and
`config/`.

### 3.4 Point the web server at `public/`

**Apache:** create `/etc/apache2/sites-available/timetable.conf`:

```
<VirtualHost *:80>
    ServerName timetable.example.edu
    DocumentRoot /var/www/timetable/public
    <Directory /var/www/timetable/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Then:

```
sudo a2ensite timetable
sudo a2dissite 000-default
sudo systemctl reload apache2
```

**Nginx (alternative):** use a server block like this, with PHP-FPM installed (`php8.3-fpm`):

```
server {
    listen 80;
    server_name timetable.example.edu;
    root /var/www/timetable/public;
    index index.php;

    location / {
        try_files $uri /index.php?$query_string;
    }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
    location ~ /\. {
        deny all;
    }
}
```

### 3.5 Turn on HTTPS

```
sudo apt install certbot python3-certbot-apache   # or python3-certbot-nginx
sudo certbot --apache -d timetable.example.edu     # or --nginx
```

### 3.6 Run the installer

Open `https://timetable.example.edu/install.php` in a browser. It checks the server and asks for:

- the database details from step 3.2;
- the **site address** (filled in for you; check it says `https://` and your real domain, because every emailed
  link such as "confirm your email" or "join a school" uses it);
- the platform name, and whether anyone may create a school account (you can make it invite-only);
- your **platform administrator** account (it manages the installation: schools, settings, health, backups);
- optionally a **first school** with you as its owner.

When it finishes it locks itself.

Then protect the settings file:

```
sudo chmod 640 /var/www/timetable/config/config.local.php
```

### 3.7 Background runs: the worker service (preferred)

Timetable runs never happen inside a web page. On a VPS the most robust way is to
keep a worker running as a system service. Create `/etc/systemd/system/timetable-worker.service`:

```
[Unit]
Description=Timetable GA worker
After=mysql.service
[Service]
User=www-data
WorkingDirectory=/var/www/timetable
ExecStart=/usr/bin/php bin/worker.php
Restart=always
[Install]
WantedBy=multi-user.target
```

Then:

```
sudo systemctl daemon-reload
sudo systemctl enable --now timetable-worker
sudo systemctl status timetable-worker
```

In the app, open **Platform, Platform settings** and set **How timetable runs are
started** to **queue mode**. Runs now start within 5 seconds. Set **Maximum runs at the
same time** to the number of processor cores you can spare (2 on a 2-vCPU server); runs
of all schools take turns, oldest first.

(Alternative: leave **exec mode** on. The web app then starts each run as its own
background process. This needs no service, but the service is more robust.)

### 3.8 Daily backup

```
sudo crontab -u www-data -e
```

Add this line, then save:

```
0 2 * * * /usr/bin/php /var/www/timetable/bin/backup.php
```

---

## 4. Path B: cPanel shared hosting

### 4.1 Create the database

In cPanel open **MySQL Databases**:

1. Create a database, for example `timetable`. cPanel adds your account name in front, so it becomes `myacct_timetable`.
2. Create a user with a strong password, for example `myacct_tt`.
3. Add the user to the database with **All privileges**.

Write down the full database name, user name and password.

### 4.2 Upload the code outside `public_html`

1. Open **File Manager** and go to your home folder (the one that contains `public_html`, not inside it).
2. Upload the release zip there and use **Extract**. You now have `/home/myacct/timetable`.

### 4.3 Point a subdomain at `timetable/public`

1. Open **Domains** (or **Subdomains**) and create `timetable.yourdomain.edu`.
2. Set its **Document Root** to `timetable/public`.
3. Open **SSL/TLS Status** and run AutoSSL so the subdomain has HTTPS.

### 4.4 Hosts that cannot change the document root

Some hosts do not let you choose a document root. Then:

1. Extract the zip **inside** `public_html`, so you have `public_html/timetable`.
2. Keep the `.htaccess` file in `public_html/timetable`. It sends every request into
   `public/` and refuses everything else; every private folder also has its own
   "deny all" `.htaccess` as a second line of defence.
3. The site address is then `https://yourdomain.edu/timetable/`.
4. Check the protection works: opening `https://yourdomain.edu/timetable/config/config.php`
   must show "Forbidden" (403). If it shows anything else, stop and ask your host to
   enable `mod_rewrite` and `AllowOverride`.

### 4.5 Run the installer

Open `https://timetable.yourdomain.edu/install.php` (or `.../timetable/install.php`)
and fill in the database details from 4.1. On cPanel the host is usually `localhost`.

### 4.6 Background runs: cron every minute

Shared hosting usually disables `exec`, so runs are queued and a cron job starts them.

1. In the app, open **Platform, Platform settings** and choose **queue mode** (the
   installer chooses it automatically when `exec` is not available). Keep **Maximum runs
   at the same time** at 1 on shared hosting.
2. Open **Health** in the app. It shows the exact cron line with your PHP path, for example:

   ```
   * * * * * /usr/local/bin/php /home/myacct/timetable/bin/worker.php --once --max-jobs=1
   ```

3. In cPanel open **Cron Jobs**, choose **Once Per Minute**, paste the command and save.
4. Within two minutes the dashboard warning "worker has not checked in" disappears.

The PHP command line path differs between hosts. Common ones are `/usr/local/bin/php`
and `/opt/cpanel/ea-php83/root/usr/bin/php`. The health page shows the one it detected;
if runs never start, try the other and set it under **Platform settings**.

Some hosts stop long-running processes. If runs fail with "worker stopped unexpectedly",
use **Record progress every N generations = 5** and a lower **Maximum generations**.

Add a second cron job for the daily backup (also shown on the health page):

```
0 2 * * * /usr/local/bin/php /home/myacct/timetable/bin/backup.php
```

---

### 4.7 Email (both paths)

The system sends three kinds of email: "confirm your email address" after sign-up, "reset your password"
and "you are invited to join a school". Open **Platform, Platform settings**, section **Email**:

1. Ask your email provider or host for the SMTP details: host (for example `mail.yourdomain.edu`), port
   (587 with TLS or 465 with SSL), user name and password, and choose a **From address** on your domain.
2. Save, then press **Send test email**. It goes to your own address.
3. If you leave the SMTP host blank, the server's own `mail()` function is used; on many shared hosts this
   works but often lands in spam.

In production, emailed links are built only from `app_url` (the site address), never from the browser's
request, so nobody can trick the system into emailing a link to another site. If `app_url` is blank, no
link emails are sent: set it in `config/config.local.php`.

---

## 5. After installing (both paths)

1. Sign in with the platform administrator account you created.
2. Open **Platform, Health**. Every line should be "OK" except perhaps HTTPS while you set it up.
3. Set up email (section 4.7) and send a test email.
4. If you created a first school, open its **Setup Guide** (top of the menu) and follow the steps. Each new
   school already has the 50 default periods, the default rules and an active semester.
5. Invite colleagues under **Manage, Members and invitations** (timetable officers for day-to-day work,
   viewers for heads of department). Other schools can sign up themselves at **Create a school account**,
   unless you made the platform invite-only.
6. Share each school's public link (`https://timetable.example.edu/s/<school>`) once it has published a timetable.

### 5.1 Semester dates and holidays

Under **Manage, Semesters, Dates and holidays**, set the first and last day of teaching and add public
holidays and breaks. The timetable itself is one week that repeats; these dates let the **Month** view and
**Export with dates** show it on real dates with holidays left out.

## 6. Updating to a new version

1. Make a backup: **Backups** then **Create a backup now** (or `php bin/backup.php`), and download it.
2. Upload the new release and extract it over the old folder. **Keep** `config/config.local.php`
   and the `storage/` folder; the release contains neither, so extracting does not overwrite them.
3. Apply database updates: open **Platform, Health** and press **Apply updates**, or run `php bin/migrate.php`.
4. On a VPS with the worker service, restart it: `sudo systemctl restart timetable-worker`.

## 7. Backups and restoring

Backups are compressed SQL files in `storage/backups` (the newest 10 are kept). They are
made with `mysqldump` when the server allows it, otherwise by a PHP export; both restore
the same way.

To restore into an **empty** database:

```
gunzip -c backup_YYYYMMDD_HHMMSS.sql.gz | mysql -u USER -p DATABASE
```

On cPanel: unzip the file on your computer and import the `.sql` file in **phpMyAdmin**
(select the empty database, then **Import**).

## 8. Configuration reference

The installer writes `config/config.local.php`. You can edit it by hand; only the keys
you want to change need to be there.

| Key | Meaning | Default |
| --- | --- | --- |
| `db_host`, `db_port`, `db_name`, `db_user`, `db_pass` | Database connection | 127.0.0.1, 3306, timetable, root, (blank) |
| `app_url` | Full public address, e.g. `https://timetable.example.edu` (or `https://yourdomain.edu/timetable/public` with the no-document-root fallback). Used for emailed links; required in production | set by the installer |
| `app_env` | `production` or `development` (development shows error details) | production |
| `timezone` | PHP and database time zone | Africa/Lagos |
| `php_cli_path` | PHP command line program (the setting on the settings page wins) | php |
| `memory_limit_cli` | Memory limit for timetable runs | 512M |
| `session_idle_minutes` | Sign out after this many idle minutes | 60 |
| `login_max_failures`, `login_window_minutes` | Sign-in throttling | 5, 15 |
| `test_db_name` | Separate empty database for the tests (development only) | (blank) |

Settings changed in the app are stored in the database, not in this file: platform settings (platform name,
sign-up, how runs start, maximum runs at once, email) by platform administrators, and per-school settings
(school profile, day times, rules and weights, default search settings, public link) by each school's
owner and administrators.

## 9. Troubleshooting

| Problem | What to do |
| --- | --- |
| The browser shows the installer again | `storage/installed.lock` is missing. If the system was installed, restore the file (any content) instead of reinstalling. |
| "Something went wrong" page | Look in `storage/logs/app-YYYY-MM-DD.log` for the details. |
| Runs stay "queued" | In queue mode, check the cron job or worker service (Health shows when the worker last checked in). In exec mode, check Health for "Background processes". |
| A run failed | Open the run; the reason is shown. The full error is in `storage/logs/job_<id>.log`. |
| "Worker stopped unexpectedly" | The process was killed (server restart, host time limit). Start the run again; on shared hosting use fewer generations and log every 5. |
| Locked out after wrong passwords | Wait 15 minutes, or use "Forgot your password?" on the sign-in page (a new password also clears the lock). |
| Forgot the only platform administrator password | On the server run `php bin/create_admin.php --email=you@example.edu --name="Your Name"` to reset it. |
| Emails do not arrive | Check spam first. Then Platform settings: SMTP host, port and encryption must match your provider; press Send test email; the error is in `storage/logs/app-YYYY-MM-DD.log`. In production `app_url` must be set. |
| Public link `/s/<school>` shows "Page not found" | Apache: `mod_rewrite` and `AllowOverride All` are needed. Nginx: the `try_files $uri /index.php?$query_string;` line in section 3.4 is needed. The address `index.php?r=public/school&slug=<school>` always works. |
| "You are not a member of any school yet" | The account belongs to no school. Create one from that page, or ask a school's administrator for an invitation. |
| "This school's workspace is suspended" | A platform administrator suspended it under Platform, Schools; only they can reactivate it. |
