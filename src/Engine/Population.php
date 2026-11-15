<?php
declare(strict_types=1);

namespace App\Engine;

/**
 * A generation of evaluated chromosomes.
 */
final class Population
{
    /** @param Chromosome[] $members all evaluated */
    public function __construct(public readonly array $members)
    {
    }

    public function size(): int
    {
        return count($this->members);
    }

    /** Lowest penalty; the first one found wins a tie. */
    public function best(): Chromosome
    {
        $best = $this->members[0];
        foreach ($this->members as $c) {
            if ($c->evaluation->penalty < $best->evaluation->penalty) {
                $best = $c;
            }
        }
        return $best;
    }

    /** The $n lowest-penalty members, best first (stable for equal penalties). */
    public function elite(int $n): array
    {
        if ($n <= 0) {
            return [];
        }
        $indexes = array_keys($this->members);
        usort($indexes, fn ($a, $b) => $this->members[$a]->evaluation->penalty <=> $this->members[$b]->evaluation->penalty);
        return array_map(fn ($i) => $this->members[$i], array_slice($indexes, 0, $n));
    }

    public function averagePenalty(): float
    {
        $sum = 0;
        foreach ($this->members as $c) {
            $sum += $c->evaluation->penalty;
        }
        return $sum / max(1, count($this->members));
    }
}
