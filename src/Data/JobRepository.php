<?php
declare(strict_types=1);

namespace App\Data;

use PDO;

/**
 * Timetable jobs (ga_jobs), their progress rows and saved entries.
 */
final class JobRepository
{
    public const ACTIVE_STATUSES = ['queued', 'running'];
    public const STALE_MINUTES = 10;

    public function __construct(private PDO $pdo)
    {
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ga_jobs WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** A queued or running job for the semester, if any. */
    public function activeForSemester(int $semesterId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM ga_jobs WHERE semester_id = ? AND status IN ('queued','running') ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$semesterId]);
        return $stmt->fetch() ?: null;
    }

    public function latest(?int $semesterId = null): ?array
    {
        $sql = 'SELECT * FROM ga_jobs' . ($semesterId ? ' WHERE semester_id = ?' : '') . ' ORDER BY id DESC LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($semesterId ? [$semesterId] : []);
        return $stmt->fetch() ?: null;
    }

    /** Insert a queued GA job; $params uses ga_jobs column names. */
    public function createGaJob(int $semesterId, array $params, ?string $label, ?int $userId): int
    {
        $columns = ['semester_id', 'job_type', 'label', 'status', 'created_by', 'population_size', 'max_generations',
            'stall_generations', 'crossover_rate', 'mutation_rate', 'tournament_size', 'elite_count', 'greedy_share',
            'max_repair_moves', 'repair_enabled', 'random_seed', 'log_every'];
        $values = [$semesterId, 'ga', $label, 'queued', $userId];
        foreach (array_slice($columns, 5) as $column) {
            $values[] = $params[$column];
        }
        $this->pdo->prepare('INSERT INTO ga_jobs (' . implode(', ', $columns) . ') VALUES ('
            . implode(', ', array_fill(0, count($columns), '?')) . ')')->execute($values);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Atomically take a queued job (BUILD_SPEC section 9). Only one process
     * can win: the others see 0 rows changed.
     */
    public function claim(int $id, int $pid): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE ga_jobs SET status = 'running', started_at = NOW(), worker_pid = ? WHERE id = ? AND status = 'queued'"
        );
        $stmt->execute([$pid, $id]);
        return $stmt->rowCount() === 1;
    }

    public function oldestQueuedId(): ?int
    {
        $id = $this->pdo->query("SELECT id FROM ga_jobs WHERE status = 'queued' AND job_type = 'ga' ORDER BY id LIMIT 1")->fetchColumn();
        return $id ? (int) $id : null;
    }

    public function requestStop(int $id): void
    {
        $this->pdo->prepare('UPDATE ga_jobs SET stop_requested = 1 WHERE id = ?')->execute([$id]);
        // A job that never started can be stopped at once.
        $this->pdo->prepare(
            "UPDATE ga_jobs SET status = 'stopped', finished_at = NOW(), notes = 'Stopped before it started.'
             WHERE id = ? AND status = 'queued'"
        )->execute([$id]);
    }

    public function stopRequested(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT stop_requested FROM ga_jobs WHERE id = ?');
        $stmt->execute([$id]);
        return (bool) $stmt->fetchColumn();
    }

    public function markFailed(int $id, string $message): void
    {
        $this->pdo->prepare(
            "UPDATE ga_jobs SET status = 'failed', finished_at = NOW(), notes = ? WHERE id = ?"
        )->execute([mb_substr($message, 0, 2000), $id]);
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

    /** Progress rows with generation greater than $after. */
    public function progressAfter(int $jobId, int $after, int $limit = 5000): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT generation, best_penalty, avg_penalty, hard_violations, soft_penalty, elapsed_ms
             FROM ga_progress WHERE job_id = ? AND generation > ? ORDER BY generation LIMIT ' . (int) $limit
        );
        $stmt->execute([$jobId, $after]);
        return $stmt->fetchAll();
    }

    /** @return array<string,mixed>[] newest first */
    public function list(array $filters = [], int $limit = 200): array
    {
        $where = ['1 = 1'];
        $params = [];
        foreach (['semester_id', 'job_type', 'status'] as $column) {
            if (($filters[$column] ?? '') !== '') {
                $where[] = "j.{$column} = ?";
                $params[] = $filters[$column];
            }
        }
        if (($filters['label'] ?? '') !== '') {
            $where[] = 'j.label LIKE ?';
            $params[] = '%' . $filters['label'] . '%';
        }
        $stmt = $this->pdo->prepare(
            'SELECT j.*, u.full_name AS created_by_name FROM ga_jobs j LEFT JOIN users u ON u.id = j.created_by
             WHERE ' . implode(' AND ', $where) . ' ORDER BY j.id DESC LIMIT ' . (int) $limit
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
