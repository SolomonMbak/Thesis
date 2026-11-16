<?php
declare(strict_types=1);

namespace App\Data;

use PDO;

/**
 * The run queue shared by every school (BUILD_SPEC section 9). Used only by
 * background processes and by pages that need queue positions; it never
 * returns another school's data to a page, only counts and ids.
 *
 * Fairness: one queued or running job per school (enforced when a job is
 * created), the oldest queued job is claimed first, and the platform setting
 * max_concurrent_jobs caps how many run at the same time.
 */
final class JobQueue
{
    public const STALE_MINUTES = 10;
    private const CLAIM_LOCK = 'timetable_job_claim';

    public function __construct(private PDO $pdo)
    {
    }

    public function maxConcurrent(): int
    {
        return max(1, (int) ((new SettingsRepository($this->pdo))->get('max_concurrent_jobs', '1')));
    }

    public function runningCount(): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM ga_jobs WHERE status = 'running'")->fetchColumn();
    }

    public function hasFreeSlot(): bool
    {
        return $this->runningCount() < $this->maxConcurrent();
    }

    /**
     * Atomically take a queued job. Only one process can win (the others see
     * 0 rows changed). With $respectCap the claim also fails while
     * max_concurrent_jobs runs are already going; a named lock makes the
     * count and the claim one step.
     */
    public function claim(int $id, int $pid, bool $respectCap = true): bool
    {
        $locked = (int) $this->pdo->query("SELECT GET_LOCK('" . self::CLAIM_LOCK . "', 10)")->fetchColumn() === 1;
        try {
            if ($respectCap && !$this->hasFreeSlot()) {
                return false;
            }
            $stmt = $this->pdo->prepare(
                "UPDATE ga_jobs SET status = 'running', started_at = NOW(), worker_pid = ? WHERE id = ? AND status = 'queued'"
            );
            $stmt->execute([$pid, $id]);
            return $stmt->rowCount() === 1;
        } finally {
            if ($locked) {
                $this->pdo->query("SELECT RELEASE_LOCK('" . self::CLAIM_LOCK . "')")->fetchColumn();
            }
        }
    }

    public function oldestQueuedId(): ?int
    {
        $id = $this->pdo->query("SELECT id FROM ga_jobs WHERE status = 'queued' AND job_type = 'ga' ORDER BY id LIMIT 1")->fetchColumn();
        return $id ? (int) $id : null;
    }

    /** The school a job belongs to (background processes build their context from it). */
    public function schoolOf(int $jobId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT s.* FROM schools s JOIN ga_jobs j ON j.school_id = s.id WHERE j.id = ?');
        $stmt->execute([$jobId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Place in the queue: 1 = next to start. Counts queued jobs of every
     * school created before this one. Null if the job is not queued.
     */
    public function position(int $jobId): ?int
    {
        $stmt = $this->pdo->prepare(
            "SELECT (SELECT COUNT(*) FROM ga_jobs q WHERE q.status = 'queued' AND q.job_type = 'ga' AND q.id < j.id) + 1
             FROM ga_jobs j WHERE j.id = ? AND j.status = 'queued'"
        );
        $stmt->execute([$jobId]);
        $position = $stmt->fetchColumn();
        return $position === false ? null : (int) $position;
    }

    /**
     * Running jobs with no sign of life for 10 minutes become failed.
     * Last sign of life = started_at plus the newest progress row's elapsed time.
     * Returns the number of jobs marked failed.
     */
    public function recoverStale(): int
    {
        $stmt = $this->pdo->prepare(
            "UPDATE ga_jobs j
             SET j.status = 'failed', j.finished_at = NOW(),
                 j.notes = CONCAT_WS(' ', j.notes, 'Worker stopped unexpectedly.')
             WHERE j.status = 'running'
               AND COALESCE(
                     DATE_ADD(j.started_at, INTERVAL (SELECT MAX(p.elapsed_ms) FROM ga_progress p WHERE p.job_id = j.id) / 1000 SECOND),
                     j.started_at, j.created_at
                   ) < NOW() - INTERVAL " . self::STALE_MINUTES . ' MINUTE'
        );
        $stmt->execute();
        return $stmt->rowCount();
    }
}
