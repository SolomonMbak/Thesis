<?php
declare(strict_types=1);

namespace App\Engine;

/**
 * Uniform crossover (BUILD_SPEC 8.5): for each gene index, a fair coin
 * decides whether the two children swap that gene. A gene (start and room
 * together) always stays at its own index, so every child gene comes from
 * one parent at the same position.
 */
final class UniformCrossover
{
    public function __construct(private Rng $rng)
    {
    }

    /** @return array{0: Chromosome, 1: Chromosome} two new children */
    public function cross(Chromosome $a, Chromosome $b): array
    {
        $s1 = $a->start;
        $r1 = $a->room;
        $s2 = $b->start;
        $r2 = $b->room;
        $n = count($s1);
        for ($i = 0; $i < $n; $i++) {
            if ($this->rng->int(0, 1) === 1) {
                [$s1[$i], $s2[$i]] = [$s2[$i], $s1[$i]];
                [$r1[$i], $r2[$i]] = [$r2[$i], $r1[$i]];
            }
        }
        return [new Chromosome($s1, $r1), new Chromosome($s2, $r2)];
    }
}
