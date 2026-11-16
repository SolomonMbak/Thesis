<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\SchoolContext;
use PDO;

/**
 * Builds the weekly period grid (timeslots) from day start, day end and
 * period length: Monday to Friday, equal periods each day.
 *
 * Existing periods keep their blocked flag. New periods that overlap
 * Friday 12:00 to 14:00 are blocked by default (Jumu'ah).
 */
final class PeriodGenerator
{
    public const DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday'];
    public const SHORT_DAYS = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri'];

    public function __construct(private PDO $pdo, private SchoolContext $school)
    {
    }

    /**
     * Work out the periods for one day.
     * @return array<int, array{start:string, end:string}> keyed by period_no from 1
     */
    public static function dayPlan(string $dayStart, string $dayEnd, int $minutes): array
    {
        $start = self::toMinutes($dayStart);
        $end = self::toMinutes($dayEnd);
        if ($minutes < 15 || $minutes > 240) {
            throw new \InvalidArgumentException('Period length must be between 15 and 240 minutes.');
        }
        if ($end <= $start) {
            throw new \InvalidArgumentException('The day must end after it starts.');
        }
        $plan = [];
        $no = 1;
        for ($t = $start; $t + $minutes <= $end; $t += $minutes) {
            $plan[$no++] = ['start' => self::toTime($t), 'end' => self::toTime($t + $minutes)];
        }
        if (!$plan) {
            throw new \InvalidArgumentException('The day is shorter than one period.');
        }
        return $plan;
    }

    /**
     * Create or update the timeslots. Returns ['created' => n, 'updated' => n, 'removed' => n].
     * Refuses to remove periods that saved timetables still use.
     */
    public function generate(string $dayStart, string $dayEnd, int $minutes): array
    {
        $plan = self::dayPlan($dayStart, $dayEnd, $minutes);
        $count = count($plan);
        $result = ['created' => 0, 'updated' => 0, 'removed' => 0];

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM timetable_entries te JOIN timeslots t ON t.id = te.timeslot_id
             WHERE t.school_id = ? AND t.period_no > ?'
        );
        $stmt->execute([$this->school->id, $count]);
        $used = (int) $stmt->fetchColumn();
        if ($used > 0) {
            throw new \RuntimeException(
                'Some periods that would be removed are used by saved timetables. '
                . 'Delete those timetables first, or keep a day at least as long as before.'
            );
        }

        // Join a transaction the caller already started (creating a school), else run our own.
        $own = !$this->pdo->inTransaction();
        if ($own) {
            $this->pdo->beginTransaction();
        }
        try {
            $find = $this->pdo->prepare('SELECT id FROM timeslots WHERE school_id = ? AND day_of_week = ? AND period_no = ?');
            $update = $this->pdo->prepare('UPDATE timeslots SET start_time = ?, end_time = ? WHERE id = ? AND school_id = ?');
            $insert = $this->pdo->prepare(
                'INSERT INTO timeslots (school_id, day_of_week, period_no, start_time, end_time, is_blocked) VALUES (?, ?, ?, ?, ?, ?)'
            );
            foreach (array_keys(self::DAYS) as $day) {
                foreach ($plan as $no => $times) {
                    $find->execute([$this->school->id, $day, $no]);
                    $id = $find->fetchColumn();
                    if ($id) {
                        $update->execute([$times['start'], $times['end'], $id, $this->school->id]);
                        $result['updated']++;
                    } else {
                        $blocked = self::isJumuah($day, $times['start'], $times['end']) ? 1 : 0;
                        $insert->execute([$this->school->id, $day, $no, $times['start'], $times['end'], $blocked]);
                        $result['created']++;
                    }
                }
            }
            $delete = $this->pdo->prepare('DELETE FROM timeslots WHERE school_id = ? AND period_no > ?');
            $delete->execute([$this->school->id, $count]);
            $result['removed'] = $delete->rowCount();
            if ($own) {
                $this->pdo->commit();
            }
        } catch (\Throwable $ex) {
            if ($own) {
                $this->pdo->rollBack();
            }
            throw $ex;
        }
        return $result;
    }

    /** Friday periods that overlap 12:00 to 14:00. */
    public static function isJumuah(int $day, string $start, string $end): bool
    {
        return $day === 5 && self::toMinutes($start) < 14 * 60 && self::toMinutes($end) > 12 * 60;
    }

    public static function toMinutes(string $time): int
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})/', $time, $m)) {
            throw new \InvalidArgumentException("Invalid time: {$time}");
        }
        return (int) $m[1] * 60 + (int) $m[2];
    }

    public static function toTime(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
