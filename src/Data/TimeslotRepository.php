<?php
declare(strict_types=1);

namespace App\Data;

use App\Core\SchoolContext;
use PDO;

/**
 * The weekly period grid of one school.
 */
final class TimeslotRepository
{
    public function __construct(private PDO $pdo, private SchoolContext $school)
    {
    }

    /** All periods with HH:MM times, ordered by day then period. */
    public function all(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, day_of_week, period_no, TIME_FORMAT(start_time, '%H:%i') AS start_time,
                    TIME_FORMAT(end_time, '%H:%i') AS end_time, is_blocked
             FROM timeslots WHERE school_id = ? ORDER BY day_of_week, period_no"
        );
        $stmt->execute([$this->school->id]);
        return $stmt->fetchAll();
    }

    public function openCount(): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM timeslots WHERE school_id = ? AND is_blocked = 0');
        $stmt->execute([$this->school->id]);
        return (int) $stmt->fetchColumn();
    }

    /** Block an open period or open a blocked one. Returns false if it is not this school's. */
    public function toggleBlocked(int $id): bool
    {
        $stmt = $this->pdo->prepare('UPDATE timeslots SET is_blocked = 1 - is_blocked WHERE id = ? AND school_id = ?');
        $stmt->execute([$id, $this->school->id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Periods arranged as a grid for display: rows are period numbers, columns days.
     * @return array{rows: array<int, array{start:string, end:string}>, cells: array<int, array<int, array>>}
     */
    public function grid(): array
    {
        $rows = [];
        $cells = [];
        foreach ($this->all() as $slot) {
            $no = (int) $slot['period_no'];
            $rows[$no] ??= ['start' => $slot['start_time'], 'end' => $slot['end_time']];
            $cells[$no][(int) $slot['day_of_week']] = $slot;
        }
        ksort($rows);
        return ['rows' => $rows, 'cells' => $cells];
    }
}
