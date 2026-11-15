<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Db;
use App\Core\Migrator;
use App\Core\Validator;
use App\Data\SettingsRepository;
use PDO;

/**
 * First-run installation: check the server, save the database settings,
 * create the tables, the first administrator and the period grid, then lock
 * itself so it can never run again.
 */
final class Installer
{
    public static function lockFile(): string
    {
        return APP_ROOT . '/storage/installed.lock';
    }

    public static function isInstalled(): bool
    {
        return is_file(self::lockFile());
    }

    /** @return array<int, array{label:string, ok:bool, detail:string}> */
    public static function requirements(): array
    {
        $checks = [[
            'label' => 'PHP 8.2 or newer',
            'ok' => version_compare(PHP_VERSION, '8.2.0', '>='),
            'detail' => 'This server has PHP ' . PHP_VERSION . '.',
        ]];
        foreach (Environment::requiredExtensions() as $ext) {
            $checks[] = [
                'label' => "PHP extension {$ext}",
                'ok' => extension_loaded($ext),
                'detail' => extension_loaded($ext) ? 'Installed.' : 'Missing. Ask your host to enable it.',
            ];
        }
        foreach (['storage', 'storage/logs', 'storage/cache', 'storage/exports', 'storage/backups', 'config'] as $dir) {
            $path = APP_ROOT . '/' . $dir;
            $ok = is_dir($path) && is_writable($path);
            $checks[] = [
                'label' => "Folder {$dir}/ is writable",
                'ok' => $ok,
                'detail' => $ok ? 'Writable.' : 'Not writable. Give the web server user write permission on this folder.',
            ];
        }
        return $checks;
    }

    public static function requirementsMet(): bool
    {
        foreach (self::requirements() as $check) {
            if (!$check['ok']) {
                return false;
            }
        }
        return true;
    }

    /** @return array<string,string> field => message */
    public static function validate(array $in): array
    {
        $errors = Validator::validate($in, [
            'db_host' => 'required|maxlen:255',
            'db_port' => 'required|int|min:1|max:65535',
            'db_name' => 'required|maxlen:64',
            'db_user' => 'required|maxlen:80',
            'institution_name' => 'required|maxlen:150',
            'department_name' => 'required|maxlen:150',
            'admin_name' => 'required|maxlen:120',
            'admin_email' => 'required|email|maxlen:120',
            'admin_password' => 'required|minlen:' . Auth::MIN_PASSWORD_LENGTH . '|maxlen:200',
        ], [
            'db_host' => 'Database host', 'db_port' => 'Database port', 'db_name' => 'Database name',
            'db_user' => 'Database user', 'institution_name' => 'Institution name',
            'department_name' => 'Department name', 'admin_name' => 'Your full name',
            'admin_email' => 'Your email address', 'admin_password' => 'Password',
        ]);
        if (!isset($errors['admin_password']) && ($in['admin_password'] ?? '') !== ($in['admin_password_confirm'] ?? '')) {
            $errors['admin_password_confirm'] = 'The two passwords do not match.';
        }
        if (!isset($errors['db_name']) && !preg_match('/^[A-Za-z0-9_$-]+$/', (string) ($in['db_name'] ?? ''))) {
            $errors['db_name'] = 'Database name may contain only letters, numbers, - _ and $.';
        }
        return $errors;
    }

    /**
     * Run every installation step. Throws \RuntimeException with a plain
     * English message when a step fails.
     */
    public function install(array $in): void
    {
        $db = [
            'host' => trim((string) $in['db_host']),
            'port' => (int) $in['db_port'],
            'name' => trim((string) $in['db_name']),
            'user' => trim((string) $in['db_user']),
            'pass' => (string) ($in['db_pass'] ?? ''),
        ];
        try {
            $pdo = Db::connect($db);
        } catch (\PDOException $ex) {
            throw new \RuntimeException(
                'Could not connect to the database: ' . $ex->getMessage()
                . ' Check the host, name, user and password. On cPanel the name and user usually start with your account name.'
            );
        }

        $this->writeConfig($db, trim((string) ($in['app_url'] ?? '')));
        Db::setPdo($pdo);

        (new Migrator($pdo, APP_ROOT . '/database/migrations'))->migrate();
        $adminId = $this->saveAdmin($pdo, $in);
        $this->saveSettings($pdo, $in);
        $this->createPeriodsIfMissing($pdo);

        if (file_put_contents(self::lockFile(), 'Installed ' . date('c') . PHP_EOL) === false) {
            throw new \RuntimeException('Could not write storage/installed.lock. Make storage/ writable and try again.');
        }
        (new Audit($pdo))->log($adminId, 'system.install', null, null, ['php' => PHP_VERSION]);
    }

    private function writeConfig(array $db, string $appUrl): void
    {
        $values = [
            'db_host' => $db['host'],
            'db_port' => $db['port'],
            'db_name' => $db['name'],
            'db_user' => $db['user'],
            'db_pass' => $db['pass'],
            'app_url' => rtrim($appUrl, '/'),
            'app_env' => 'production',
            'timezone' => (string) Config::get('timezone', 'Africa/Lagos'),
            'php_cli_path' => Environment::guessPhpCli(),
        ];
        $php = "<?php\n// Written by the installer on " . date('Y-m-d H:i') . ". Keep this file private.\nreturn "
            . var_export($values, true) . ";\n";
        $file = APP_ROOT . '/config/config.local.php';
        if (file_put_contents($file, $php, LOCK_EX) === false) {
            throw new \RuntimeException('Could not write config/config.local.php. Make config/ writable and try again.');
        }
        @chmod($file, 0640);
        foreach ($values as $key => $value) {
            Config::set($key, $value);
        }
    }

    private function saveAdmin(PDO $pdo, array $in): int
    {
        $email = strtolower(trim((string) $in['admin_email']));
        $hash = Auth::hashPassword((string) $in['admin_password']);
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $id = $stmt->fetchColumn();
        if ($id) {
            $pdo->prepare("UPDATE users SET full_name = ?, password_hash = ?, role = 'admin', is_active = 1 WHERE id = ?")
                ->execute([trim((string) $in['admin_name']), $hash, $id]);
            return (int) $id;
        }
        $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, 'admin')")
            ->execute([trim((string) $in['admin_name']), $email, $hash]);
        return (int) $pdo->lastInsertId();
    }

    private function saveSettings(PDO $pdo, array $in): void
    {
        $settings = new SettingsRepository($pdo);
        $settings->set('institution_name', trim((string) $in['institution_name']));
        $settings->set('department_name', trim((string) $in['department_name']));
        $settings->set('launch_mode', Environment::recommendedLaunchMode());
        $settings->set('php_cli_path', Environment::guessPhpCli());
    }

    private function createPeriodsIfMissing(PDO $pdo): void
    {
        if ((int) $pdo->query('SELECT COUNT(*) FROM timeslots')->fetchColumn() > 0) {
            return;
        }
        $settings = new SettingsRepository($pdo);
        (new PeriodGenerator($pdo))->generate(
            (string) $settings->get('day_start', '08:00'),
            (string) $settings->get('day_end', '18:00'),
            (int) $settings->get('period_minutes', '60')
        );
    }
}
