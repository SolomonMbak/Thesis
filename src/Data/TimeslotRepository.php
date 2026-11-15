<?php
declare(strict_types=1);

namespace App\Data;

use PDO;

/**
 * The weekly period grid.
 */
final class TimeslotRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** All periods with HH:MM times, ordered by day then period. */
    public function all(): array
    {
        return $this->pdo->query(
            "SELECT id, day_of_week, period_no, TIME_FORMAT(start_time, '%H:%i') AS start_time,
                    TIME_FORMAT(end_time, '%H:%i') AS end_time, is_blocked
             FROM timeslots ORDER BY day_of_week, period_no"
        )->fetchAll();
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
