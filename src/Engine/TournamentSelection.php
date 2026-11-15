<?php
declare(strict_types=1);

namespace App\Engine;

/**
 * Tournament selection (BUILD_SPEC 8.5): draw k individuals at random (with
 * replacement) and return the one with the lowest penalty.
 */
final class TournamentSelection
{
    public function __construct(private int $size, private Rng $rng)
    {
    }

    /** @param Chromosome[] $population all evaluated */
    public function select(array $population): Chromosome
    {
        $last = count($population) - 1;
        $best = $population[$this->rng->int(0, $last)];
        for ($i = 1; $i < $this->size; $i++) {
            $rival = $population[$this->rng->int(0, $last)];
            if ($rival->evaluation->penalty < $best->evaluation->penalty) {
                $best = $rival;
            }
        }
        return $best;
    }
}
