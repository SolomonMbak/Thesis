<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Data\JobRepository;
use App\Engine\GaParams;
use App\Engine\GeneticAlgorithm;
use App\Engine\RunResult;
use PDO;

/**
 * Runs one claimed GA job: the bridge between the database and the engine
 * (used by bin/run_ga.php, bin/worker.php and bin/experiment.php).
 *
 * Steps: snapshot the weights, load the problem, run the GA writing progress
 * rows and checking for a stop request, then save the timetable and summary
 * in one transaction. Any error marks the job failed with the message in
 * notes and the full trace in storage/logs/job_<id>.log.
 */
final class JobRunner
{
    private JobRepository $jobs;
    /** @var callable(string):void */
    private $echo;

    public function __construct(private PDO $pdo, ?callable $echo = null)
    {
        $this->jobs = new JobRepository($pdo);
        $this->echo = $echo ?? static function (string $line): void {
        };
    }

    public static function logFile(int $jobId): string
    {
        return APP_ROOT . '/storage/logs/job_' . $jobId . '.log';
    }

    /** Claim and run. Returns the final status, or null if another process claimed it first. */
    public function claimAndRun(int $jobId): ?string
    {
        if (!$this->jobs->claim($jobId, getmypid() ?: 0)) {
            return null;
        }
        return $this->run($jobId);
    }

    /** Run a job already claimed by this process. Returns finished, stopped or failed. */
    public function run(int $jobId): string
    {
        $this->log($jobId, 'Job started by process ' . getmypid() . ' on PHP ' . PHP_VERSION);
        try {
            $job = $this->jobs->find($jobId) ?? throw new \RuntimeException("Job {$jobId} does not exist.");
            $loader = new ProblemLoader($this->pdo);
            $weights = $loader->currentWeights();
            $this->pdo->prepare('UPDATE ga_jobs SET weights_json = ? WHERE id = ?')->execute([json_encode($weights), $jobId]);

            $ctx = $loader->load((int) $job['semester_id'], $weights);
            $params = GaParams::fromArray($job);
            $this->log($jobId, sprintf('%d sessions, seed %d, repair %s, population %d, up to %d generations',
                $ctx->data->S, $params->seed, $params->repairEnabled ? 'on' : 'off', $params->populationSize, $params->maxGenerations));

            $result = (new GeneticAlgorithm($ctx->data, $params))->run(
                $this->progressWriter($jobId),
                fn () => $this->jobs->stopRequested($jobId)
            );
            $status = $result->stopped ? 'stopped' : 'finished';
            $this->save($jobId, $ctx, $result, $status);
            $this->log($jobId, sprintf('Job %s after %d generations: V_H %d, penalty %d, %.1f s',
                $status, $result->generations, $result->evaluation->hard, $result->evaluation->penalty, $result->runtimeMs / 1000));
            return $status;
        } catch (\Throwable $ex) {
            $this->log($jobId, 'FAILED: ' . $ex->getMessage() . "\n" . $ex->getTraceAsString());
            Logger::exception($ex);
            $this->jobs->markFailed($jobId, 'The run failed: ' . $ex->getMessage());
            return 'failed';
        }
    }

    /** Callback that records a progress row and keeps the job's live summary current. */
    private function progressWriter(int $jobId): callable
    {
        $insert = $this->pdo->prepare(
            'INSERT INTO ga_progress (job_id, generation, best_fitness, best_penalty, avg_penalty, hard_violations, soft_penalty, elapsed_ms)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $update = $this->pdo->prepare(
            'UPDATE ga_jobs SET generations_run = ?, best_penalty = ?, best_fitness = ?, hard_violations = ?, soft_penalty = ? WHERE id = ?'
        );
        return function (int $generation, array $s) use ($insert, $update, $jobId): void {
            $insert->execute([$jobId, $generation, $s['best_fitness'], $s['best_penalty'], round($s['avg_penalty'], 2),
                $s['hard'], $s['soft'], $s['elapsed_ms']]);
            $update->execute([$generation, $s['best_penalty'], $s['best_fitness'], $s['hard'], $s['soft'], $jobId]);
            if ($generation % 100 === 0) {
                ($this->echo)(sprintf('Job %d generation %d: penalty %d, V_H %d', $jobId, $generation, $s['best_penalty'], $s['hard']));
            }
        };
    }

    /** Save the best timetable and the summary together, or not at all. */
    private function save(int $jobId, ProblemContext $ctx, RunResult $result, string $status): void
    {
        $e = $result->evaluation;
        $wH = (int) ($ctx->weights['H'] ?? 1000);
        $notes = $e->soft >= $wH
            ? "Warning: the soft penalty ({$e->soft}) is not smaller than the hard weight ({$wH}), so the GA may trade a clash for comfort. Raise the H weight."
            : null;

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM timetable_entries WHERE job_id = ?')->execute([$jobId]);
            $insert = $this->pdo->prepare('INSERT INTO timetable_entries (job_id, session_id, timeslot_id, room_id) VALUES (?, ?, ?, ?)');
            foreach ($ctx->sessionIds as $s => $sessionId) {
                $insert->execute([$jobId, $sessionId, $ctx->periodIds[$result->best->start[$s]], $ctx->roomIds[$result->best->room[$s]]]);
            }
            $this->pdo->prepare(
                'UPDATE ga_jobs SET status = ?, best_fitness = ?, best_penalty = ?, hard_violations = ?, soft_penalty = ?,
                        breakdown_json = ?, generations_run = ?, first_feasible_generation = ?, runtime_ms = ?,
                        peak_memory_mb = ?, finished_at = NOW(), notes = COALESCE(?, notes)
                 WHERE id = ?'
            )->execute([
                $status, $e->fitness, $e->penalty, $e->hard, $e->soft, json_encode($e->breakdown),
                $result->generations, $result->firstFeasibleGeneration, $result->runtimeMs, $result->peakMemoryMb,
                $notes, $jobId,
            ]);
            $this->pdo->commit();
        } catch (\Throwable $ex) {
            $this->pdo->rollBack();
            throw $ex;
        }
    }

    private function log(int $jobId, string $message): void
    {
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
        @file_put_contents(self::logFile($jobId), $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        ($this->echo)($line);
    }
}
