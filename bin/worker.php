<?php
/**
 * Queue worker: runs queued timetable jobs one at a time.
 *
 *   php bin/worker.php                       run forever (systemd service), sleeping 5 s when idle
 *   php bin/worker.php --once                run queued jobs, then exit (cron, every minute)
 *   php bin/worker.php --once --max-jobs=1   at most one job per cron call
 *
 * A lock file (storage/cache/worker.lock) makes sure two workers never run
 * at the same time; a second one exits straight away.
 */
declare(strict_types=1);

use App\Core\Config;
use App\Core\Db;
use App\Data\JobRepository;
use App\Data\SettingsRepository;
use App\Services\JobRunner;

require dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line.\n");
}
set_time_limit(0);
ini_set('memory_limit', (string) Config::get('memory_limit_cli', '512M'));

$options = getopt('', ['once', 'max-jobs:']);
$once = isset($options['once']);
$maxJobs = isset($options['max-jobs']) ? max(1, (int) $options['max-jobs']) : PHP_INT_MAX;

$lockPath = APP_ROOT . '/storage/cache/worker.lock';
$lock = fopen($lockPath, 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo "Another worker is already running. Exiting.\n";
    exit(0);
}

$say = fn (string $line) => print('[' . date('Y-m-d H:i:s') . "] {$line}" . PHP_EOL);
$pdo = Db::pdo();
$jobs = new JobRepository($pdo);
$settings = new SettingsRepository($pdo);
$runner = new JobRunner($pdo, $say);

$recovered = $jobs->recoverStale();
if ($recovered > 0) {
    $say("{$recovered} stale job(s) marked as failed.");
}

$done = 0;
while ($done < $maxJobs) {
    $settings->set('worker_heartbeat', date('Y-m-d H:i:s'));
    $jobId = $jobs->oldestQueuedId();
    if ($jobId === null) {
        if ($once) {
            break;
        }
        sleep(5);
        continue;
    }
    $status = $runner->claimAndRun($jobId);
    if ($status !== null) {
        $done++;
        $say("Job {$jobId} {$status}.");
    }
}

$settings->set('worker_heartbeat', date('Y-m-d H:i:s'));
flock($lock, LOCK_UN);
fclose($lock);
if ($once) {
    $say("Worker finished: {$done} job(s) run.");
}
