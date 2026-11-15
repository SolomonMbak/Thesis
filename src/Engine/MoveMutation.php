<?php
declare(strict_types=1);

namespace App\Engine;

/**
 * Move mutation (BUILD_SPEC 8.5): each gene, with probability pm, moves to
 * a random allowed start; half of those moves also take a random allowed
 * room. Values always come from the session's domain.
 */
final class MoveMutation
{
    public function __construct(private ProblemData $d, private float $rate, private Rng $rng)
    {
    }

    /** Mutates in place; returns the number of genes changed. */
    public function mutate(Chromosome $c): int
    {
        $changed = 0;
        for ($s = 0; $s < $this->d->S; $s++) {
            if (!$this->rng->chance($this->rate)) {
                continue;
            }
            $c->start[$s] = $this->rng->pick($this->d->allowedStarts[$s]);
            if ($this->rng->int(0, 1) === 1) {
                $c->room[$s] = $this->rng->pick($this->d->allowedRooms[$s]);
            }
            $changed++;
        }
        if ($changed > 0) {
            $c->invalidate();
        }
        return $changed;
    }
}
