<?php
declare(strict_types=1);

namespace App\Engine;

/**
 * Counts hard and soft constraint violations (BUILD_SPEC section 6).
 *
 * evaluate() is the fast path used millions of times by the GA: one pass
 * over the sessions for H1 to H6, S1, S3 and S5, then one pass over the
 * group-day grid for S2 and S4. Clash counts use the identity
 *   sum over cells of max(0, count - 1) = number of placements into an
 *   already occupied cell,
 * so no separate pass over the occupancy arrays is needed.
 *
 * explain() is the slow path for reports: it lists every violation and
 * where it occurs. Tests check that both paths agree.
 *
 * A session covers periods start .. start + duration - 1. Periods past the
 * end of the day are not placed; each one counts 1 under H6.
 */
final class FitnessEvaluator
{
    private array $zeroLec;
    private array $zeroRoom;
    private array $zeroGroup;
    private array $zeroCourseDay;

    public function __construct(private ProblemData $d)
    {
        $this->zeroLec = array_fill(0, max(1, $d->L * $d->P), 0);
        $this->zeroRoom = array_fill(0, max(1, $d->R * $d->P), 0);
        $this->zeroGroup = array_fill(0, max(1, $d->G * $d->P), 0);
        $this->zeroCourseDay = array_fill(0, max(1, $d->C * $d->D), 0);
    }

    public function evaluate(Chromosome $c): Evaluation
    {
        if ($c->evaluation !== null) {
            return $c->evaluation;
        }
        $d = $this->d;
        $P = $d->P;
        $D = $d->D;
        $lec = $this->zeroLec;
        $room = $this->zeroRoom;
        $grp = $this->zeroGroup;
        $courseDay = $this->zeroCourseDay;
        $h1 = $h2 = $h3 = $h4 = $h5 = $h6 = 0;
        $s1 = $s3 = $s5 = 0;

        for ($s = 0; $s < $d->S; $s++) {
            $st = $c->start[$s];
            $r = $c->room[$s];
            $dur = $d->sDur[$s];
            $l = $d->sLec[$s];
            $day = $d->periodDay[$st];

            if ($d->roomCap[$r] < $d->sSize[$s]) {
                $h4++;
            }
            if ($d->sLab[$s] && !$d->roomLab[$r]) {
                $h5++;
            }
            if ($courseDay[$d->sCourse[$s] * $D + $day]++ > 0) {
                $s3++;
            }

            $lb = $l * $P;
            $rb = $r * $P;
            $gb = $d->sGroup[$s] * $P;
            $hasPref = $d->lecHasPref[$l];
            $coversLast = false;
            for ($k = 0; $k < $dur; $k++) {
                $p = $st + $k;
                if ($p >= $P || $d->periodDay[$p] !== $day) {
                    $h6 += $dur - $k;      // the rest of the session runs past the day end
                    break;
                }
                if ($lec[$lb + $p]++ > 0) {
                    $h1++;
                }
                if ($room[$rb + $p]++ > 0) {
                    $h2++;
                }
                if ($grp[$gb + $p]++ > 0) {
                    $h3++;
                }
                if ($d->blocked[$p] || $d->unavailable[$lb + $p]) {
                    $h6++;
                }
                if ($hasPref && !$d->preferred[$lb + $p]) {
                    $s1++;
                }
                if ($d->lastOfDay[$p]) {
                    $coversLast = true;
                }
            }
            if ($coversLast) {
                $s5++;
            }
        }

        [$s2, $s4] = $this->groupDayPenalties($grp);

        $hard = $h1 + $h2 + $h3 + $h4 + $h5 + $h6;
        $w = $d->wS;
        $soft = $w[1] * $s1 + $w[2] * $s2 + $w[3] * $s3 + $w[4] * $s4 + $w[5] * $s5;
        return $c->evaluation = new Evaluation($hard, $soft, $d->wH, [
            'H1' => $h1, 'H2' => $h2, 'H3' => $h3, 'H4' => $h4, 'H5' => $h5, 'H6' => $h6,
            'S1' => $s1, 'S2' => $s2, 'S3' => $s3, 'S4' => $s4, 'S5' => $s5,
        ]);
    }

    /**
     * S2 (idle open periods between a group's first and last class of a day)
     * and S4 (periods beyond 3 in each unbroken run), in one scan.
     * @return array{0:int, 1:int}
     */
    private function groupDayPenalties(array $grp): array
    {
        $d = $this->d;
        $s2 = $s4 = 0;
        for ($g = 0; $g < $d->G; $g++) {
            $gb = $g * $d->P;
            for ($day = 0; $day < $d->D; $day++) {
                [$gaps, $long] = self::scanDay($grp, $gb, $d->dayFirst[$day], $d->dayLength[$day], $d->blocked);
                $s2 += $gaps;
                $s4 += $long;
            }
        }
        return [$s2, $s4];
    }

    /**
     * Gaps and over-long runs for one group on one day.
     * Shared with RepairOperator so local and global costs agree.
     * @return array{0:int, 1:int}
     */
    public static function scanDay(array $occupancy, int $base, int $first, int $length, array $blocked): array
    {
        $gaps = $long = 0;
        $seen = false;
        $pendingGaps = 0;
        $run = 0;
        for ($i = 0; $i < $length; $i++) {
            $p = $first + $i;
            if ($occupancy[$base + $p] > 0) {
                if ($seen) {
                    $gaps += $pendingGaps;
                }
                $seen = true;
                $pendingGaps = 0;
                $run++;
            } else {
                if ($run > 3) {
                    $long += $run - 3;
                }
                $run = 0;
                if ($seen && !$blocked[$p]) {
                    $pendingGaps++;
                }
            }
        }
        if ($run > 3) {
            $long += $run - 3;
        }
        return [$gaps, $long];
    }

    /**
     * Every violation with its location, for the quality report.
     * Indexes only (no names): the caller turns them into text.
     *
     * @return array<int, array{code:string, count:int, sessions:int[], period:?int, room:?int,
     *                          lecturer:?int, group:?int, course:?int, day:?int, reason:string}>
     */
    public function explain(Chromosome $c): array
    {
        $d = $this->d;
        $P = $d->P;
        $items = [];
        $lecCells = $roomCells = $groupCells = [];
        $courseDay = [];

        for ($s = 0; $s < $d->S; $s++) {
            $st = $c->start[$s];
            $r = $c->room[$s];
            $l = $d->sLec[$s];
            $g = $d->sGroup[$s];
            $day = $d->periodDay[$st];
            if ($d->roomCap[$r] < $d->sSize[$s]) {
                $items[] = self::item('H4', 1, [$s], null, $r, null, null, null, null, 'room too small');
            }
            if ($d->sLab[$s] && !$d->roomLab[$r]) {
                $items[] = self::item('H5', 1, [$s], null, $r, null, null, null, null, 'practical not in a lab');
            }
            $courseDay[$d->sCourse[$s]][$day][] = $s;
            $coversLast = false;
            for ($k = 0; $k < $d->sDur[$s]; $k++) {
                $p = $st + $k;
                if ($p >= $P || $d->periodDay[$p] !== $day) {
                    $items[] = self::item('H6', $d->sDur[$s] - $k, [$s], null, null, $l, null, null, $day, 'runs past the end of the day');
                    break;
                }
                $lecCells[$l][$p][] = $s;
                $roomCells[$r][$p][] = $s;
                $groupCells[$g][$p][] = $s;
                if ($d->blocked[$p]) {
                    $items[] = self::item('H6', 1, [$s], $p, null, $l, null, null, $day, 'blocked period');
                } elseif ($d->unavailable[$l * $P + $p]) {
                    $items[] = self::item('H6', 1, [$s], $p, null, $l, null, null, $day, 'lecturer unavailable');
                }
                if ($d->lecHasPref[$l] && !$d->preferred[$l * $P + $p]) {
                    $items[] = self::item('S1', 1, [$s], $p, null, $l, null, null, $day, 'not a preferred period');
                }
                $coversLast = $coversLast || $d->lastOfDay[$p];
            }
            if ($coversLast) {
                $items[] = self::item('S5', 1, [$s], null, null, null, $g, null, $day, 'uses the last period of the day');
            }
        }

        foreach (['H1' => [$lecCells, 'lecturer'], 'H2' => [$roomCells, 'room'], 'H3' => [$groupCells, 'group']] as $code => [$cells, $who]) {
            foreach ($cells as $owner => $periods) {
                foreach ($periods as $p => $sessions) {
                    if (count($sessions) > 1) {
                        $items[] = self::item($code, count($sessions) - 1, $sessions, $p,
                            $who === 'room' ? $owner : null, $who === 'lecturer' ? $owner : null,
                            $who === 'group' ? $owner : null, null, $d->periodDay[$p], "{$who} double-booked");
                    }
                }
            }
        }
        foreach ($courseDay as $course => $days) {
            foreach ($days as $day => $sessions) {
                if (count($sessions) > 1) {
                    $items[] = self::item('S3', count($sessions) - 1, $sessions, null, null, null, null, $course, $day, 'course taught more than once on one day');
                }
            }
        }
        $grp = $this->zeroGroup;
        foreach ($groupCells as $g => $periods) {
            foreach ($periods as $p => $sessions) {
                $grp[$g * $P + $p] = count($sessions);
            }
        }
        for ($g = 0; $g < $d->G; $g++) {
            for ($day = 0; $day < $d->D; $day++) {
                [$gaps, $long] = self::scanDay($grp, $g * $P, $d->dayFirst[$day], $d->dayLength[$day], $d->blocked);
                if ($gaps > 0) {
                    $items[] = self::item('S2', $gaps, [], null, null, null, $g, null, $day, 'idle periods between classes');
                }
                if ($long > 0) {
                    $items[] = self::item('S4', $long, [], null, null, null, $g, null, $day, 'more than three hours in a row');
                }
            }
        }
        return $items;
    }

    private static function item(string $code, int $count, array $sessions, ?int $period, ?int $room, ?int $lecturer,
                                 ?int $group, ?int $course, ?int $day, string $reason): array
    {
        return compact('code', 'count', 'sessions', 'period', 'room', 'lecturer', 'group', 'course', 'day', 'reason');
    }
}
