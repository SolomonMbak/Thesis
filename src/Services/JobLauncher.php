<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Core\SchoolContext;
use App\Data\JobQueue;
use App\Data\JobRepository;
use App\Data\SettingsRepository;
use App\Engine\GaParams;
use PDO;

/**
 * Creates a GA job from the Generate form and starts it in the background
 * (BUILD_SPEC section 9). The GA never runs inside the web request.
 *
 * One queued or running job per school. In exec mode the run starts at once
 * if fewer than max_concurrent_jobs are running; otherwise it waits in the
 * queue and the run that finishes next starts it (see kick()).
 */
final class JobLauncher
{
    public function __construct(private PDO $pdo, private SchoolContext $school)
    {
    }

    /**
     * @return array{ok:bool, job_id?:int, errors?:array<string,string>, message?:string, mode?:string, rebuilt?:?array}
     */
    public function start(int $semesterId, array $input, ?int $userId): array
    {
        $params = array_intersect_key($input, GaParams::FIELDS + ['random_seed' => 1, 'repair_enabled' => 1]);
        $params['repair_enabled'] = ($input['repair_enabled'] ?? '0') === '1' ? 1 : 0;
        $errors = GaParams::validate($params);
        $label = trim((string) ($input['label'] ?? ''));
        if (mb_strlen($label) > 100) {
            $errors['label'] = 'Label must be at most 100 characters.';
        }
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        (new JobQueue($this->pdo))->recoverStale();
        $jobs = new JobRepository($this->pdo, $this->school);
        $active = $jobs->active();
        if ($active) {
            return ['ok' => false, 'message' => "Run #{$active['id']} is already " . ($active['status'] === 'queued' ? 'waiting to start' : 'running')
                . '. A school can have one run at a time. Wait for it to finish, or stop it first.', 'job_id' => (int) $active['id']];
        }

        $feasibility = (new FeasibilityService($this->pdo, $this->school))->run($semesterId, $userId);
        if (!$feasibility['ok']) {
            return ['ok' => false, 'message' => 'Check readiness found ' . count($feasibility['failures'])
                . ' problem(s), so the run was not started. Open Check readiness to see each problem and how to fix it.'];
        }

        // A blank seed gets a random one, stored before the run so it can be repeated.
        $seed = trim((string) ($params['random_seed'] ?? ''));
        $params['random_seed'] = $seed === '' ? random_int(1, 999999999) : (int) $seed;
        $jobId = $jobs->createGaJob($semesterId, $params, $label === '' ? null : $label, $userId);

        $mode = (new SettingsRepository($this->pdo))->get('launch_mode', 'queue');
        $result = ['ok' => true, 'job_id' => $jobId, 'mode' => $mode, 'rebuilt' => $feasibility['rebuilt']];
        if ($mode === 'exec') {
            $queue = new JobQueue($this->pdo);
            if ($queue->hasFreeSlot() && !self::startProcess($this->pdo, $jobId)) {
                $result['message'] = 'The background process could not be started, so the run is waiting for the queue worker.';
            }
        }
        return $result;
    }

    /**
     * Exec mode: start the oldest queued job if a slot is free. Called when a
     * run ends and when the Generate pages load, so a waiting run never
     * depends on one particular process. Returns the job id started, or null.
     */
    public static function kick(PDO $pdo): ?int
    {
        if ((new SettingsRepository($pdo))->get('launch_mode', 'queue') !== 'exec') {
            return null;
        }
        $queue = new JobQueue($pdo);
        $next = $queue->oldestQueuedId();
        if ($next === null || !$queue->hasFreeSlot()) {
            return null;
        }
        return self::startProcess($pdo, $next) ? $next : null;
    }

    /** Start bin/run_ga.php as a detached process. Every argument is escaped. */
    public static function startProcess(PDO $pdo, int $jobId): bool
    {
        if (!Environment::canExec()) {
            return false;
        }
        $php = Environment::phpCliPath(new SettingsRepository($pdo));
        $script = APP_ROOT . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'run_ga.php';
        $log = JobRunner::logFile($jobId);
        $args = escapeshellarg($php) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg((string) (int) $jobId);
        try {
            if (Environment::isWindows()) {
                $handle = popen('start "" /B ' . $args . ' >> ' . escapeshellarg($log) . ' 2>&1', 'r');
                if ($handle === false) {
                    return false;
                }
                pclose($handle);
                return true;
            }
            exec('nohup ' . $args . ' >> ' . escapeshellarg($log) . ' 2>&1 &', $output, $code);
            return $code === 0;
        } catch (\Throwable $ex) {
            Logger::exception($ex);
            return false;
        }
    }
}
