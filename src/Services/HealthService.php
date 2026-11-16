<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Migrator;
use App\Data\JobQueue;
use App\Data\SettingsRepository;
use PDO;

/**
 * Server health checks for the admin health page. Each check returns
 * ['label', 'status' => ok|warning|problem, 'detail', 'advice'].
 */
final class HealthService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function checks(): array
    {
        $settings = new SettingsRepository($this->pdo);
        $checks = [];

        $checks[] = $this->check('PHP version', version_compare(PHP_VERSION, '8.2.0', '>=') ? 'ok' : 'problem',
            PHP_VERSION, 'PHP 8.2 or newer is required; 8.3 is recommended.');
        foreach (Environment::requiredExtensions() as $ext) {
            $checks[] = $this->check("Extension {$ext}", extension_loaded($ext) ? 'ok' : 'problem',
                extension_loaded($ext) ? 'Loaded' : 'Missing', 'Ask the host to enable it.');
        }
        $checks[] = $this->check('Database server', 'ok', (string) $this->pdo->query('SELECT VERSION()')->fetchColumn(),
            'MySQL 8 or MariaDB 10.6+ is recommended.');

        $pending = (new Migrator($this->pdo, APP_ROOT . '/database/migrations'))->pending();
        $checks[] = $this->check('Database updates', $pending ? 'warning' : 'ok',
            $pending ? count($pending) . ' pending: ' . implode(', ', $pending) : 'Up to date',
            'Use "Apply updates" below, or run php bin/migrate.php.');

        $canExec = Environment::canExec();
        $mode = (string) $settings->get('launch_mode', 'queue');
        $checks[] = $this->check('Background processes (exec)', $canExec ? 'ok' : 'warning',
            $canExec ? 'Available' : 'Disabled by the host',
            $canExec ? 'Exec mode can start runs immediately.' : 'Use queue mode with the cron job below.');
        $modeOk = $mode === 'queue' || $canExec;
        $checks[] = $this->check('Launch mode', $modeOk ? 'ok' : 'problem', $mode,
            $modeOk ? 'Recommended for this server: ' . Environment::recommendedLaunchMode() . '.'
                : 'Exec mode is set but exec is disabled, so runs would never start. Switch to queue mode in Settings.');

        $heartbeat = (string) $settings->get('worker_heartbeat', '');
        $age = $heartbeat !== '' ? time() - strtotime($heartbeat) : null;
        $workerStatus = $mode !== 'queue' ? 'ok' : ($age !== null && $age <= 300 ? 'ok' : 'problem');
        $checks[] = $this->check('Worker last seen', $workerStatus,
            $age === null ? 'Never' : self::ago($age),
            $mode === 'queue' ? 'In queue mode the worker must check in at least every few minutes (cron every minute).'
                : 'Only needed in queue mode.');

        $cli = Environment::phpCliPath($settings);
        $checks[] = $this->check('PHP command line path', $cli !== '' ? 'ok' : 'warning', $cli,
            'Used for background runs and cron jobs. Change it in Settings if it is wrong.');
        $checks[] = $this->cliTimeLimitCheck($cli, $canExec);

        $free = @disk_free_space(APP_ROOT);
        $checks[] = $this->check('Disk free space', $free === false ? 'warning' : ($free < 200 * 1048576 ? 'warning' : 'ok'),
            $free === false ? 'Unknown' : number_format($free / 1073741824, 2) . ' GB', 'Keep at least 200 MB free for logs and backups.');
        foreach (['storage/logs', 'storage/cache', 'storage/backups', 'storage/exports'] as $dir) {
            $ok = is_writable(APP_ROOT . '/' . $dir);
            $checks[] = $this->check("Folder {$dir}/ writable", $ok ? 'ok' : 'problem', $ok ? 'Yes' : 'No',
                'The web server user and the cron user must be able to write here.');
        }

        // Platform view: which run and school only, never the school's data.
        $job = $this->pdo->query('SELECT j.id, j.status, s.slug FROM ga_jobs j JOIN schools s ON s.id = j.school_id ORDER BY j.id DESC LIMIT 1')->fetch();
        $checks[] = $this->check('Last run (any school)', !$job || $job['status'] !== 'failed' ? 'ok' : 'warning',
            $job ? "#{$job['id']} {$job['status']} ({$job['slug']})" : 'None yet',
            'A failed run has its full error in storage/logs/job_<id>.log.');
        $queue = new JobQueue($this->pdo);
        $queued = (int) $this->pdo->query("SELECT COUNT(*) FROM ga_jobs WHERE status = 'queued'")->fetchColumn();
        $checks[] = $this->check('Run queue', $queued > 5 ? 'warning' : 'ok',
            "{$queue->runningCount()} running, {$queued} waiting, at most {$queue->maxConcurrent()} at once",
            'Raise Maximum runs at the same time in Platform settings on a server with more processor cores.');

        $production = Config::get('app_env') === 'production';
        $checks[] = $this->check('Environment', $production ? 'ok' : 'warning', (string) Config::get('app_env'),
            'Set app_env to production on a live server so error details are never shown.');
        $https = \App\Core\Request::isHttps();
        $checks[] = $this->check('HTTPS', $https ? 'ok' : 'warning', $https ? 'Yes' : 'No', 'Use HTTPS on a live server (for example Let\'s Encrypt).');
        return $checks;
    }

    /** Cron lines to show the administrator, with the real paths filled in. */
    public function cronLines(): array
    {
        $php = Environment::phpCliPath(new SettingsRepository($this->pdo));
        $root = APP_ROOT;
        return [
            'worker' => "* * * * * {$php} {$root}/bin/worker.php --once --max-jobs=1",
            'backup' => "0 2 * * * {$php} {$root}/bin/backup.php",
        ];
    }

    /** CLI max_execution_time: most hosts do not limit it, some kill long processes. */
    private function cliTimeLimitCheck(string $cli, bool $canExec): array
    {
        if (!$canExec || !function_exists('exec')) {
            return $this->check('CLI time limit', 'warning', 'Cannot be checked here',
                'If long runs are killed, use log_every = 5 and lower maximum generations.');
        }
        $output = [];
        // Single quotes inside: escapeshellarg() on Windows replaces double quotes with spaces.
        @exec(escapeshellarg($cli) . ' -r ' . escapeshellarg("echo ini_get('max_execution_time');") . ' 2>&1', $output, $code);
        $limit = $code === 0 ? trim((string) ($output[0] ?? '')) : '';
        if ($limit === '' || !ctype_digit($limit)) {
            return $this->check('CLI time limit', 'warning', 'Could not run the PHP command line program',
                'Check the PHP command line path in Settings.');
        }
        return $this->check('CLI time limit', $limit === '0' ? 'ok' : 'warning', $limit === '0' ? 'No limit' : "{$limit} seconds",
            $limit === '0' ? 'Runs set their own limit.' : 'Runs call set_time_limit(0), but some hosts still kill long processes. If runs fail, use log_every = 5 and fewer generations.');
    }

    private function check(string $label, string $status, string $detail, string $advice): array
    {
        return compact('label', 'status', 'detail', 'advice');
    }

    private static function ago(int $seconds): string
    {
        if ($seconds < 120) {
            return "{$seconds} seconds ago";
        }
        if ($seconds < 7200) {
            return intdiv($seconds, 60) . ' minutes ago';
        }
        return intdiv($seconds, 3600) . ' hours ago';
    }
}
