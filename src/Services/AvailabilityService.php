<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Lecturer availability per period. "available" is the default and is
 * stored as no row; only preferred and unavailable periods have rows.
 */
final class AvailabilityService
{
    public const STATUSES = ['available', 'preferred', 'unavailable'];

    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<int,string> timeslot_id => status (only non-default) */
    public function forLecturer(int $lecturerId): array
    {
        $stmt = $this->pdo->prepare('SELECT timeslot_id, status FROM lecturer_availability WHERE lecturer_id = ?');
        $stmt->execute([$lecturerId]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
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
        $stmt = $this->pdo->prepare(
            'SELECT (SELECT COUNT(*) FROM lecturers WHERE id = ?) AS lec,
                    (SELECT COUNT(*) FROM timeslots WHERE id = ? AND is_blocked = 0) AS slot'
        );
        $stmt->execute([$lecturerId, $timeslotId]);
        $found = $stmt->fetch();
        if (!(int) $found['lec'] || !(int) $found['slot']) {
            throw new \InvalidArgumentException('That lecturer or period does not exist, or the period is blocked.');
        }
        $current = $this->forLecturer($lecturerId)[$timeslotId] ?? 'available';
        $next = self::next($current);
        $this->set($lecturerId, $timeslotId, $next);
        return $next;
    }

    public function set(int $lecturerId, int $timeslotId, string $status): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException('Unknown availability status.');
        }
        if ($status === 'available') {
            $this->pdo->prepare('DELETE FROM lecturer_availability WHERE lecturer_id = ? AND timeslot_id = ?')
                ->execute([$lecturerId, $timeslotId]);
            return;
        }
        $this->pdo->prepare(
            'INSERT INTO lecturer_availability (lecturer_id, timeslot_id, status) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE status = VALUES(status)'
        )->execute([$lecturerId, $timeslotId, $status]);
    }

    /**
     * Set one status for every active lecturer on a whole day or at one period number.
     * Returns the number of cells set.
     */
    public function bulk(string $scope, int $value, string $status): int
    {
        $column = $scope === 'day' ? 'day_of_week' : 'period_no';
        $stmt = $this->pdo->prepare("SELECT id FROM timeslots WHERE {$column} = ?");
        $stmt->execute([$value]);
        $slots = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $lecturers = $this->pdo->query('SELECT id FROM lecturers WHERE is_active = 1')->fetchAll(PDO::FETCH_COLUMN);
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
}
