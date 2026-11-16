<?php
/**
 * Run one timetable job in this process.
 *
 *   php bin/run_ga.php <job_id>
 *
 * Started in the background by the web app (exec mode) or by hand. Claims
 * the job atomically, so running it twice cannot run the job twice. The job
 * waits if max_concurrent_jobs runs are already going; when this run ends it
 * starts the next queued one (exec mode), so waiting runs always move on.
 */
declare(strict_types=1);

use App\Core\Config;
use App\Core\Db;
use App\Services\JobLauncher;
use App\Services\JobRunner;

require dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line.\n");
}
set_time_limit(0);
ini_set('memory_limit', (string) Config::get('memory_limit_cli', '512M'));

$jobId = (int) ($argv[1] ?? 0);
if ($jobId <= 0) {
    fwrite(STDERR, "Usage: php bin/run_ga.php <job_id>\n");
    exit(2);
}

// At a terminal: print lines and also write storage/logs/job_<id>.log.
// In exec mode the output is already redirected into that log, so just print.
$atTerminal = stream_isatty(STDOUT);
$runner = new JobRunner(Db::pdo(), fn (string $line) => print($line . PHP_EOL), $atTerminal);
$status = $runner->claimAndRun($jobId);
if ($status === null) {
    echo "Job {$jobId} was not started: it is not queued, or the maximum number of runs is already going (it then stays in the queue).\n";
    exit(0);
}
// Exec mode: start the next waiting run of any school, now that a slot is free.
JobLauncher::kick(Db::pdo());
exit($status === 'failed' ? 1 : 0);
