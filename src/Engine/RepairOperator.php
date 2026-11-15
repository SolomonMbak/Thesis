<?php
declare(strict_types=1);

namespace App\Engine;

/**
 * Targeted repair (BUILD_SPEC 8.6): the contribution of this work.
 *
 * Takes the genes involved in a lecturer, room or group clash (H1, H2, H3),
 * in random order, and moves each one that still clashes to the clash-free
 * (start, room) with the lowest local soft cost. Genes that do not clash are
 * never touched. At most maxMoves genes are moved per call.
 *
 * Local soft cost of placing gene g at start st (room does not affect soft cost):
 *   S1  periods of g that are not preferred by its lecturer
 *   S3  1 if another session of g's course is already on that day
 *   S5  1 if g covers the last period of the day
 *   S2, S4  change in idle gaps and long runs for g's group on that day
 * Ties are broken at random. The full evaluator computes the exact global
 * soft penalty afterwards.
 */
final class RepairOperator
{
    private array $zeroLec;
    private array $zeroRoom;
    private array $zeroGroup;
    private array $zeroCourseDay;

    // Occupancy for the chromosome being repaired
    private array $lec = [];
    private array $room = [];
    private array $grp = [];
    private array $courseDay = [];

    public function __construct(private ProblemData $d, private int $maxMoves, private Rng $rng)
    {
        $this->zeroLec = array_fill(0, max(1, $d->L * $d->P), 0);
        $this->zeroRoom = array_fill(0, max(1, $d->R * $d->P), 0);
        $this->zeroGroup = array_fill(0, max(1, $d->G * $d->P), 0);
        $this->zeroCourseDay = array_fill(0, max(1, $d->C * $d->D), 0);
    }

    /** Repairs in place; returns the number of genes moved. */
    public function repair(Chromosome $c): int
    {
        $d = $this->d;
        $this->lec = $this->zeroLec;
        $this->room = $this->zeroRoom;
        $this->grp = $this->zeroGroup;
        $this->courseDay = $this->zeroCourseDay;
        for ($s = 0; $s < $d->S; $s++) {
            $this->place($s, $c->start[$s], $c->room[$s], 1);
        }

        $clashing = [];
        for ($s = 0; $s < $d->S; $s++) {
            if ($this->clashes($s, $c->start[$s], $c->room[$s])) {
                $clashing[] = $s;
            }
        }
        if (!$clashing || $this->maxMoves <= 0) {
            return 0;
        }

        $moves = 0;
        foreach ($this->rng->shuffle($clashing) as $g) {
            if ($moves >= $this->maxMoves) {
                break;
            }
            if (!$this->clashes($g, $c->start[$g], $c->room[$g])) {
                continue;   // an earlier move already resolved it
            }
            $this->place($g, $c->start[$g], $c->room[$g], -1);
            $best = $this->bestPosition($g);
            if ($best !== null) {
                $c->start[$g] = $best[0];
                $c->room[$g] = $best[1];
                $moves++;
            }
            $this->place($g, $c->start[$g], $c->room[$g], 1);
        }
        if ($moves > 0) {
            $c->invalidate();
        }
        return $moves;
    }

    /** Lowest-cost clash-free (start, room) for gene g, ties at random; null if none. */
    private function bestPosition(int $g): ?array
    {
        $d = $this->d;
        $P = $d->P;
        $dur = $d->sDur[$g];
        $lb = $d->sLec[$g] * $P;
        $gb = $d->sGroup[$g] * $P;
        $best = null;
        $bestCost = PHP_INT_MAX;
        $ties = 0;

        foreach ($d->allowedStarts[$g] as $st) {
            if (!self::free($this->lec, $lb, $st, $dur) || !self::free($this->grp, $gb, $st, $dur)) {
                continue;
            }
            $cost = $this->localCost($g, $st);
            if ($cost > $bestCost) {
                continue;
            }
            foreach ($d->allowedRooms[$g] as $r) {
                if (!self::free($this->room, $r * $P, $st, $dur)) {
                    continue;
                }
                if ($cost < $bestCost) {
                    [$best, $bestCost, $ties] = [[$st, $r], $cost, 1];
                } elseif ($this->rng->int(1, ++$ties) === 1) {
                    $best = [$st, $r];   // reservoir sampling: each tie equally likely
                }
            }
        }
        return $best;
    }

    /** Weighted soft cost of putting gene g at start st, given the other genes. */
    private function localCost(int $g, int $st): int
    {
        $d = $this->d;
        $P = $d->P;
        $l = $d->sLec[$g];
        $dur = $d->sDur[$g];
        $day = $d->periodDay[$st];

        $s1 = 0;
        $coversLast = false;
        for ($k = 0; $k < $dur; $k++) {
            $p = $st + $k;
            if ($d->lecHasPref[$l] && !$d->preferred[$l * $P + $p]) {
                $s1++;
            }
            $coversLast = $coversLast || $d->lastOfDay[$p];
        }
        $s3 = $this->courseDay[$d->sCourse[$g] * $d->D + $day] > 0 ? 1 : 0;

        // Group's day with and without g: the difference is g's share of S2 and S4.
        $gb = $d->sGroup[$g] * $P;
        $first = $d->dayFirst[$day];
        $length = $d->dayLength[$day];
        [$gapsBefore, $longBefore] = FitnessEvaluator::scanDay($this->grp, $gb, $first, $length, $d->blocked);
        $withG = $this->grp;
        for ($k = 0; $k < $dur; $k++) {
            $withG[$gb + $st + $k]++;
        }
        [$gapsAfter, $longAfter] = FitnessEvaluator::scanDay($withG, $gb, $first, $length, $d->blocked);

        $w = $d->wS;
        return $w[1] * $s1
            + $w[2] * ($gapsAfter - $gapsBefore)
            + $w[3] * $s3
            + $w[4] * ($longAfter - $longBefore)
            + $w[5] * ($coversLast ? 1 : 0);
    }

    /** Does gene s share a lecturer, room or group period with another gene? */
    private function clashes(int $s, int $st, int $r): bool
    {
        $d = $this->d;
        $P = $d->P;
        $lb = $d->sLec[$s] * $P;
        $rb = $r * $P;
        $gb = $d->sGroup[$s] * $P;
        $day = $d->periodDay[$st];
        for ($k = 0; $k < $d->sDur[$s]; $k++) {
            $p = $st + $k;
            if ($p >= $P || $d->periodDay[$p] !== $day) {
                break;
            }
            if ($this->lec[$lb + $p] > 1 || $this->room[$rb + $p] > 1 || $this->grp[$gb + $p] > 1) {
                return true;
            }
        }
        return false;
    }

    /** Add ($delta = 1) or remove ($delta = -1) gene s from the occupancy maps. */
    private function place(int $s, int $st, int $r, int $delta): void
    {
        $d = $this->d;
        $P = $d->P;
        $lb = $d->sLec[$s] * $P;
        $rb = $r * $P;
        $gb = $d->sGroup[$s] * $P;
        $day = $d->periodDay[$st];
        $this->courseDay[$d->sCourse[$s] * $d->D + $day] += $delta;
        for ($k = 0; $k < $d->sDur[$s]; $k++) {
            $p = $st + $k;
            if ($p >= $P || $d->periodDay[$p] !== $day) {
                break;
            }
            $this->lec[$lb + $p] += $delta;
            $this->room[$rb + $p] += $delta;
            $this->grp[$gb + $p] += $delta;
        }
    }

    private static function free(array $count, int $base, int $start, int $dur): bool
    {
        for ($k = 0; $k < $dur; $k++) {
            if ($count[$base + $start + $k] > 0) {
                return false;
            }
        }
        return true;
    }
}
