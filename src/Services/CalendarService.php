<?php
declare(strict_types=1);

namespace App\Services;

/**
 * The monthly calendar view (BUILD_SPEC 10.7). The timetable is one week
 * that repeats all semester; this lays that week onto the real dates of a
 * month, leaving out weekends, days outside the semester and days without
 * teaching (holidays).
 *
 * Pure functions on arrays, so they are easy to test.
 */
final class CalendarService
{
    public const MONTHS = [1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August',
        'September', 'October', 'November', 'December'];

    /**
     * Which month to show: ?month=YYYY-MM if valid, else today's month if it
     * is inside the semester, else the semester's first month.
     * @return array{0:int, 1:int} [year, month]
     */
    public static function pickMonth(?string $requested, ?array $semester, ?int $today = null): array
    {
        if ($requested !== null && preg_match('/^(\d{4})-(\d{2})$/', $requested, $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12) {
            return [(int) $m[1], (int) $m[2]];
        }
        $now = date('Y-m-d', $today ?? time());
        $start = $semester['start_date'] ?? null;
        $end = $semester['end_date'] ?? null;
        if ($start && $end && ($now < $start || $now > $end)) {
            return [(int) substr($start, 0, 4), (int) substr($start, 5, 2)];
        }
        return [(int) substr($now, 0, 4), (int) substr($now, 5, 2)];
    }

    /**
     * One month as weeks of Monday to Friday cells.
     *
     * @param array $entries   timetable entries of one level, lecturer or room (TimetableService::entries, filtered)
     * @param array $periods   period_no => [start, end] (to show end times of multi-hour classes)
     * @param array $semester  semester row with start_date and end_date (may be null)
     * @param array $closures  date => reason
     * @return array{label:string, prev:string, next:string, has_dates:bool, teaching_days:int, closed_days:int,
     *               weeks: array<int, array<int, array{date:string, day:int, number:int, in_month:bool, status:string, reason:?string, items:array}>>}
     */
    public static function month(int $year, int $month, array $entries, array $periods, ?array $semester, array $closures): array
    {
        $byDay = [];
        foreach ($entries as $e) {
            $last = (int) $e['period_no'] + (int) $e['duration'] - 1;
            $byDay[(int) $e['day']][] = $e + ['end_time' => $periods[$last]['end'] ?? ''];
        }
        foreach ($byDay as &$list) {
            usort($list, fn ($a, $b) => [$a['start_time'], $a['course_code']] <=> [$b['start_time'], $b['course_code']]);
        }
        unset($list);

        $start = $semester['start_date'] ?? null;
        $end = $semester['end_date'] ?? null;
        $first = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $cursor = $first->modify('monday this week');
        $last = $first->modify('last day of this month');
        $weeks = [];
        $teaching = $closed = 0;
        while ($cursor <= $last) {
            $week = [];
            for ($d = 1; $d <= 5; $d++) {
                $date = $cursor->modify('+' . ($d - 1) . ' days');
                $iso = $date->format('Y-m-d');
                $inMonth = (int) $date->format('n') === $month;
                $status = self::status($iso, $start, $end, $closures);
                if ($inMonth && $status === 'teaching') {
                    $teaching++;
                } elseif ($inMonth && $status === 'closed') {
                    $closed++;
                }
                $week[$d] = [
                    'date' => $iso, 'day' => $d, 'number' => (int) $date->format('j'), 'in_month' => $inMonth,
                    'status' => $status, 'reason' => $closures[$iso] ?? null,
                    'items' => $inMonth && in_array($status, ['teaching', 'no_dates'], true) ? ($byDay[$d] ?? []) : [],
                ];
            }
            $weeks[] = $week;
            $cursor = $cursor->modify('+7 days');
        }
        return [
            'label' => self::MONTHS[$month] . ' ' . $year,
            'prev' => $first->modify('-1 month')->format('Y-m'),
            'next' => $first->modify('+1 month')->format('Y-m'),
            'has_dates' => $start !== null && $end !== null,
            'span' => self::spanText($semester),
            'teaching_days' => $teaching,
            'closed_days' => $closed,
            'weeks' => $weeks,
        ];
    }

    /**
     * Every class on its real date across the semester, holidays left out
     * (for the "Export with dates" CSV). Needs semester dates.
     * @return array<int, array> entries with 'date' added, in date and time order
     */
    public static function occurrences(array $entries, array $periods, array $semester, array $closures): array
    {
        if (empty($semester['start_date']) || empty($semester['end_date'])) {
            return [];
        }
        $byDay = [];
        foreach ($entries as $e) {
            $last = (int) $e['period_no'] + (int) $e['duration'] - 1;
            $byDay[(int) $e['day']][] = $e + ['end_time' => $periods[$last]['end'] ?? ''];
        }
        $rows = [];
        $date = new \DateTimeImmutable($semester['start_date']);
        $end = new \DateTimeImmutable($semester['end_date']);
        for (; $date <= $end; $date = $date->modify('+1 day')) {
            $day = (int) $date->format('N');
            $iso = $date->format('Y-m-d');
            if ($day > 5 || isset($closures[$iso])) {
                continue;
            }
            foreach ($byDay[$day] ?? [] as $e) {
                $rows[] = ['date' => $iso] + $e;
            }
        }
        usort($rows, fn ($a, $b) => [$a['date'], $a['start_time'], $a['course_code']] <=> [$b['date'], $b['start_time'], $b['course_code']]);
        return $rows;
    }

    /**
     * The last day of teaching for a semester that runs a number of weeks
     * from $start. A last day on a weekend moves back to the Friday, so the
     * saved date is always a teaching weekday.
     */
    public static function endAfterWeeks(string $start, int $weeks): string
    {
        $end = (new \DateTimeImmutable($start))->modify('+' . ($weeks * 7 - 1) . ' days');
        while ((int) $end->format('N') > 5) {
            $end = $end->modify('-1 day');
        }
        return $end->format('Y-m-d');
    }

    /**
     * How long the semester runs: calendar weeks it touches, weekdays in it,
     * and weekdays left for teaching after the days without teaching.
     * Null when the semester has no dates.
     * @return array{weeks:int, weekdays:int, closed:int, teaching_days:int}|null
     */
    public static function length(?array $semester, array $closures): ?array
    {
        $start = $semester['start_date'] ?? null;
        $end = $semester['end_date'] ?? null;
        if (!$start || !$end) {
            return null;
        }
        $from = new \DateTimeImmutable($start);
        $to = new \DateTimeImmutable($end);
        $weekdays = $closed = 0;
        for ($day = $from; $day <= $to; $day = $day->modify('+1 day')) {
            if ((int) $day->format('N') <= 5) {
                $weekdays++;
                $closed += isset($closures[$day->format('Y-m-d')]) ? 1 : 0;
            }
        }
        return [
            'weeks' => intdiv((int) $from->diff($to)->days + 7, 7),
            'weekdays' => $weekdays,
            'closed' => $closed,
            'teaching_days' => $weekdays - $closed,
        ];
    }

    /** One line such as "14 Sep 2026 to 11 Dec 2026 (13 weeks)", or null without dates. */
    public static function spanText(?array $semester): ?string
    {
        $length = self::length($semester, []);
        if ($length === null) {
            return null;
        }
        return date('j M Y', strtotime($semester['start_date'])) . ' to ' . date('j M Y', strtotime($semester['end_date']))
            . ' (' . $length['weeks'] . ($length['weeks'] === 1 ? ' week)' : ' weeks)');
    }

    /** teaching, closed (holiday), outside (before or after the semester) or no_dates (semester has no dates yet). */
    private static function status(string $date, ?string $start, ?string $end, array $closures): string
    {
        if ($start === null || $end === null) {
            return isset($closures[$date]) ? 'closed' : 'no_dates';
        }
        if ($date < $start || $date > $end) {
            return 'outside';
        }
        return isset($closures[$date]) ? 'closed' : 'teaching';
    }
}
