<?php
/**
 * Run one timetable job in this process.
 *
 *   php bin/run_ga.php <job_id>
 *
 * Started in the background by the web app (exec mode) or by hand. Claims
 * the job atomically, so running it twice cannot run the job twice.
 */
declare(strict_types=1);

use App\Core\Config;
use App\Core\Db;
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

// JobRunner writes the job log itself; echo only when someone is watching a terminal,
// otherwise (exec mode redirects output into the same log) every line would appear twice.
$echo = stream_isatty(STDOUT) ? fn (string $line) => print($line . PHP_EOL) : null;
$runner = new JobRunner(Db::pdo(), $echo);
$status = $runner->claimAndRun($jobId);
if ($status === null) {
    echo "Job {$jobId} is not queued (already claimed, finished or missing). Nothing to do.\n";
    exit(0);
}
exit($status === 'failed' ? 1 : 0);
