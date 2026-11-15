<?php
declare(strict_types=1);

namespace App\Engine;

/**
 * Builds the first population (BUILD_SPEC 8.4): mostly random timetables
 * plus a share built greedily.
 *
 * Greedy: hardest sessions first (smallest domain |starts| x |rooms|, then
 * longest), ties in random order; each goes to the first clash-free
 * (start, room) found, scanning its domain in random order so that greedy
 * individuals differ from one another; if none is clash-free, a random one.
 */
final class Initialiser
{
    public function __construct(private ProblemData $d, private Rng $rng)
    {
    }

    /** @return Chromosome[] */
    public function population(int $size, float $greedyShare): array
    {
        $greedy = (int) round($size * $greedyShare);
        $population = [];
        for ($i = 0; $i < $size; $i++) {
            $population[] = $i < $greedy ? $this->greedy() : $this->random();
        }
        return $population;
    }

    public function random(): Chromosome
    {
        $start = $room = [];
        for ($s = 0; $s < $this->d->S; $s++) {
            $start[$s] = $this->rng->pick($this->d->allowedStarts[$s]);
            $room[$s] = $this->rng->pick($this->d->allowedRooms[$s]);
        }
        return new Chromosome($start, $room);
    }

    public function greedy(): Chromosome
    {
        $d = $this->d;
        $P = $d->P;
        $lecBusy = array_fill(0, max(1, $d->L * $P), false);
        $roomBusy = array_fill(0, max(1, $d->R * $P), false);
        $groupBusy = array_fill(0, max(1, $d->G * $P), false);
        $start = array_fill(0, $d->S, 0);
        $room = array_fill(0, $d->S, 0);

        foreach ($this->placementOrder() as $s) {
            $dur = $d->sDur[$s];
            $lb = $d->sLec[$s] * $P;
            $gb = $d->sGroup[$s] * $P;
            $placed = false;
            foreach ($this->rng->shuffle($d->allowedStarts[$s]) as $st) {
                if (!self::free($lecBusy, $lb, $st, $dur) || !self::free($groupBusy, $gb, $st, $dur)) {
                    continue;
                }
                foreach ($this->rng->shuffle($d->allowedRooms[$s]) as $r) {
                    if (self::free($roomBusy, $r * $P, $st, $dur)) {
                        [$start[$s], $room[$s], $placed] = [$st, $r, true];
                        break 2;
                    }
                }
            }
            if (!$placed) {
                $start[$s] = $this->rng->pick($d->allowedStarts[$s]);
                $room[$s] = $this->rng->pick($d->allowedRooms[$s]);
            }
            for ($k = 0; $k < $dur; $k++) {
                $p = $start[$s] + $k;
                $lecBusy[$lb + $p] = $groupBusy[$gb + $p] = $roomBusy[$room[$s] * $P + $p] = true;
            }
        }
        return new Chromosome($start, $room);
    }

    /** Smallest domain first, then longest duration; ties in random order. */
    private function placementOrder(): array
    {
        $d = $this->d;
        $order = $this->rng->shuffle(range(0, $d->S - 1));
        $key = [];
        foreach ($order as $s) {
            $key[$s] = [count($d->allowedStarts[$s]) * count($d->allowedRooms[$s]), -$d->sDur[$s]];
        }
        usort($order, fn ($a, $b) => $key[$a] <=> $key[$b]);   // usort is stable in PHP 8
        return $order;
    }

    private static function free(array $busy, int $base, int $start, int $dur): bool
    {
        for ($k = 0; $k < $dur; $k++) {
            if ($busy[$base + $start + $k]) {
                return false;
            }
        }
        return true;
    }
}
