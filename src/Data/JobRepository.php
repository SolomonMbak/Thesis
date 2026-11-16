<?php
declare(strict_types=1);

namespace App\Data;

use App\Core\SchoolContext;
use PDO;

/**
 * Timetable jobs (ga_jobs) of one school, their progress rows and entries.
 * Queue handling across all schools (claiming, stale recovery, the
 * concurrency cap) lives in JobQueue.
 */
final class JobRepository
{
    public const ACTIVE_STATUSES = ['queued', 'running'];
    public const READY_STATUSES = ['finished', 'stopped'];

    public function __construct(private PDO $pdo, private SchoolContext $school)
    {
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ga_jobs WHERE id = ? AND school_id = ?');
        $stmt->execute([$id, $this->school->id]);
        return $stmt->fetch() ?: null;
    }

    /** The school's queued or running job, if any (one at a time per school, BUILD_SPEC 9). */
    public function active(): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM ga_jobs WHERE school_id = ? AND status IN ('queued','running') ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$this->school->id]);
        return $stmt->fetch() ?: null;
    }

    public function latest(?int $semesterId = null): ?array
    {
        $sql = 'SELECT * FROM ga_jobs WHERE school_id = ?' . ($semesterId ? ' AND semester_id = ?' : '') . ' ORDER BY id DESC LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($semesterId ? [$this->school->id, $semesterId] : [$this->school->id]);
        return $stmt->fetch() ?: null;
    }

    public function published(int $semesterId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM ga_jobs WHERE school_id = ? AND semester_id = ? AND is_published = 1 AND status IN ('finished','stopped') LIMIT 1"
        );
        $stmt->execute([$this->school->id, $semesterId]);
        return $stmt->fetch() ?: null;
    }

    /** Has the semester a finished run with no hard violations? (Setup Guide step 8) */
    public function hasClashFree(int $semesterId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM ga_jobs WHERE school_id = ? AND semester_id = ? AND job_type = 'ga'
               AND status IN ('finished','stopped') AND hard_violations = 0"
        );
        $stmt->execute([$this->school->id, $semesterId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** Insert a queued GA job; $params uses ga_jobs column names. */
    public function createGaJob(int $semesterId, array $params, ?string $label, ?int $userId): int
    {
        $columns = ['school_id', 'semester_id', 'job_type', 'label', 'status', 'created_by', 'population_size', 'max_generations',
            'stall_generations', 'crossover_rate', 'mutation_rate', 'tournament_size', 'elite_count', 'greedy_share',
            'max_repair_moves', 'repair_enabled', 'random_seed', 'log_every'];
        $values = [$this->school->id, $semesterId, 'ga', $label, 'queued', $userId];
        foreach (array_slice($columns, 6) as $column) {
            $values[] = $params[$column];
        }
        $this->pdo->prepare('INSERT INTO ga_jobs (' . implode(', ', $columns) . ') VALUES ('
            . implode(', ', array_fill(0, count($columns), '?')) . ')')->execute($values);
        return (int) $this->pdo->lastInsertId();
    }

    public function requestStop(int $id): void
    {
        $this->pdo->prepare('UPDATE ga_jobs SET stop_requested = 1 WHERE id = ? AND school_id = ?')->execute([$id, $this->school->id]);
        // A job that never started can be stopped at once.
        $this->pdo->prepare(
            "UPDATE ga_jobs SET status = 'stopped', finished_at = NOW(), notes = 'Stopped before it started.'
             WHERE id = ? AND school_id = ? AND status = 'queued'"
        )->execute([$id, $this->school->id]);
    }

    public function stopRequested(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT stop_requested FROM ga_jobs WHERE id = ? AND school_id = ?');
        $stmt->execute([$id, $this->school->id]);
        return (bool) $stmt->fetchColumn();
    }

    public function markFailed(int $id, string $message): void
    {
        $this->pdo->prepare(
            "UPDATE ga_jobs SET status = 'failed', finished_at = NOW(), notes = ? WHERE id = ? AND school_id = ?"
        )->execute([mb_substr($message, 0, 2000), $id, $this->school->id]);
    }

    /** Make one job the published timetable of its semester (and no other). */
    public function publish(array $job): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('UPDATE ga_jobs SET is_published = 0 WHERE school_id = ? AND semester_id = ?')
                ->execute([$this->school->id, $job['semester_id']]);
            $this->pdo->prepare('UPDATE ga_jobs SET is_published = 1 WHERE id = ? AND school_id = ?')
                ->execute([$job['id'], $this->school->id]);
            $this->pdo->commit();
        } catch (\Throwable $ex) {
            $this->pdo->rollBack();
            throw $ex;
        }
    }

    public function unpublish(int $id): void
    {
        $this->pdo->prepare('UPDATE ga_jobs SET is_published = 0 WHERE id = ? AND school_id = ?')->execute([$id, $this->school->id]);
    }

    /** Progress rows with generation greater than $after. */
    public function progressAfter(int $jobId, int $after, int $limit = 5000): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT generation, best_penalty, avg_penalty, hard_violations, soft_penalty, elapsed_ms
             FROM ga_progress WHERE job_id = ? AND school_id = ? AND generation > ? ORDER BY generation LIMIT ' . (int) $limit
        );
        $stmt->execute([$jobId, $this->school->id, $after]);
        return $stmt->fetchAll();
    }

    /** @return array<string,mixed>[] newest first */
    public function list(array $filters = [], int $limit = 200): array
    {
        $where = ['j.school_id = ?'];
        $params = [$this->school->id];
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

    /** Counts by status, for the Health-like summary on Runs and results. */
    public function countsByStatus(): array
    {
        $stmt = $this->pdo->prepare('SELECT status, COUNT(*) FROM ga_jobs WHERE school_id = ? GROUP BY status');
        $stmt->execute([$this->school->id]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /** Delete this school's ended, unpublished runs older than $days. Returns how many. */
    public function deleteOld(int $days, bool $includeManual): int
    {
        $types = $includeManual ? "'ga','manual'" : "'ga'";
        $stmt = $this->pdo->prepare(
            "DELETE FROM ga_jobs WHERE school_id = ? AND is_published = 0 AND status IN ('finished','stopped','failed')
               AND job_type IN ({$types}) AND created_at < NOW() - INTERVAL ? DAY"
        );
        $stmt->execute([$this->school->id, $days]);
        return $stmt->rowCount();
    }
}
