<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Data\JobRepository;
use App\Data\SettingsRepository;
use App\Engine\GaParams;
use PDO;

/**
 * Creates a GA job from the Generate form and starts it in the background
 * (BUILD_SPEC section 9). The GA never runs inside the web request.
 */
final class JobLauncher
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array{ok:bool, job_id?:int, errors?:array<string,string>, message?:string, mode?:string}
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

        $jobs = new JobRepository($this->pdo);
        $jobs->recoverStale();
        $active = $jobs->activeForSemester($semesterId);
        if ($active) {
            return ['ok' => false, 'message' => "Run #{$active['id']} is already {$active['status']} for this semester. "
                . 'Wait for it to finish or stop it first.', 'job_id' => (int) $active['id']];
        }

        $feasibility = (new FeasibilityService($this->pdo))->run($semesterId, $userId);
        if (!$feasibility['ok']) {
            return ['ok' => false, 'message' => 'The feasibility check failed with ' . count($feasibility['failures'])
                . ' problem(s), so the run was not started. Open the feasibility page to see and fix them.'];
        }

        // A blank seed gets a random one, stored before the run so it can be repeated.
        $seed = trim((string) ($params['random_seed'] ?? ''));
        $params['random_seed'] = $seed === '' ? random_int(1, 999999999) : (int) $seed;
        $jobId = $jobs->createGaJob($semesterId, $params, $label === '' ? null : $label, $userId);

        $mode = (new SettingsRepository($this->pdo))->get('launch_mode', 'queue');
        if ($mode === 'exec') {
            if (!$this->launchProcess($jobId)) {
                return ['ok' => true, 'job_id' => $jobId, 'mode' => 'queue',
                    'message' => 'The background process could not be started, so the run is queued for the worker.'];
            }
        }
        return ['ok' => true, 'job_id' => $jobId, 'mode' => $mode];
    }

    /** Start bin/run_ga.php as a detached process. Every argument is escaped. */
    public function launchProcess(int $jobId): bool
    {
        if (!Environment::canExec()) {
            return false;
        }
        $php = Environment::phpCliPath(new SettingsRepository($this->pdo));
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
