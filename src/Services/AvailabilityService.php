<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\SchoolContext;
use PDO;

/**
 * Lecturer availability per period. "available" is the default and is
 * stored as no row; only preferred and unavailable periods have rows.
 * The lecturer and the period must both belong to the context's school.
 */
final class AvailabilityService
{
    public const STATUSES = ['available', 'preferred', 'unavailable'];

    public function __construct(private PDO $pdo, private SchoolContext $school)
    {
    }

    /** @return array<int,string> timeslot_id => status (only non-default) */
    public function forLecturer(int $lecturerId): array
    {
        $stmt = $this->pdo->prepare('SELECT timeslot_id, status FROM lecturer_availability WHERE school_id = ? AND lecturer_id = ?');
        $stmt->execute([$this->school->id, $lecturerId]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /** Count of preferred and unavailable periods per lecturer: [lecturer_id => [preferred, unavailable]]. */
    public function summary(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT lecturer_id, SUM(status = 'preferred') AS preferred, SUM(status = 'unavailable') AS unavailable
             FROM lecturer_availability WHERE school_id = ? GROUP BY lecturer_id"
        );
        $stmt->execute([$this->school->id]);
        return $stmt->fetchAll(PDO::FETCH_UNIQUE);
    }

    /** Active lecturers with any non-default availability (Setup Guide step 6). */
    public function lecturersWithAvailability(): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(DISTINCT a.lecturer_id) FROM lecturer_availability a JOIN lecturers l ON l.id = a.lecturer_id
             WHERE a.school_id = ? AND l.is_active = 1'
        );
        $stmt->execute([$this->school->id]);
        return (int) $stmt->fetchColumn();
    }

    /** Next status in the click cycle: available, preferred, unavailable, available... */
    public static function next(string $status): string
    {
        $i = array_search($status, self::STATUSES, true);
        return self::STATUSES[(($i === false ? 0 : $i) + 1) % count(self::STATUSES)];
    }

    /** Advance one cell and return the new status. */
    public function cycle(int $lecturerId, int $timeslotId): string
    {
        $this->assertCell($lecturerId, $timeslotId);
        $current = $this->forLecturer($lecturerId)[$timeslotId] ?? 'available';
        $next = self::next($current);
        $this->set($lecturerId, $timeslotId, $next);
        return $next;
    }

    /** Set one cell. Callers must have checked the lecturer and period with assertCell or a scoped lookup. */
    public function set(int $lecturerId, int $timeslotId, string $status): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException('Unknown availability status.');
        }
        if ($status === 'available') {
            $this->pdo->prepare('DELETE FROM lecturer_availability WHERE school_id = ? AND lecturer_id = ? AND timeslot_id = ?')
                ->execute([$this->school->id, $lecturerId, $timeslotId]);
            return;
        }
        $this->pdo->prepare(
            'INSERT INTO lecturer_availability (school_id, lecturer_id, timeslot_id, status) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status = VALUES(status)'
        )->execute([$this->school->id, $lecturerId, $timeslotId, $status]);
    }

    /**
     * Set one status for every active lecturer on a whole day or at one period number.
     * Returns the number of cells set.
     */
    public function bulk(string $scope, int $value, string $status): int
    {
        $column = $scope === 'day' ? 'day_of_week' : 'period_no';
        $stmt = $this->pdo->prepare("SELECT id FROM timeslots WHERE school_id = ? AND is_blocked = 0 AND {$column} = ?");
        $stmt->execute([$this->school->id, $value]);
        $slots = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $stmt = $this->pdo->prepare('SELECT id FROM lecturers WHERE school_id = ? AND is_active = 1');
        $stmt->execute([$this->school->id]);
        $lecturers = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $this->pdo->beginTransaction();
        try {
            foreach ($lecturers as $l) {
                foreach ($slots as $t) {
                    $this->set((int) $l, (int) $t, $status);
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $ex) {
            $this->pdo->rollBack();
            throw $ex;
        }
        return count($lecturers) * count($slots);
    }

    /** The lecturer and the open period must both be this school's. */
    private function assertCell(int $lecturerId, int $timeslotId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT (SELECT COUNT(*) FROM lecturers WHERE id = ? AND school_id = ?) AS lec,
                    (SELECT COUNT(*) FROM timeslots WHERE id = ? AND school_id = ? AND is_blocked = 0) AS slot'
        );
        $stmt->execute([$lecturerId, $this->school->id, $timeslotId, $this->school->id]);
        $found = $stmt->fetch();
        if (!(int) $found['lec'] || !(int) $found['slot']) {
            throw new \InvalidArgumentException('That lecturer or period does not exist, or the period is blocked.');
        }
    }
}
