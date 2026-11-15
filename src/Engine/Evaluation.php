<?php
declare(strict_types=1);

namespace App\Engine;

/**
 * Score of one chromosome (BUILD_SPEC section 6):
 *   hard    = V_H = H1 + ... + H6 (unweighted count)
 *   soft    = wS1*S1 + ... + wS5*S5
 *   penalty = wH * hard + soft
 *   fitness = 1 / (1 + penalty)
 */
final class Evaluation
{
    public readonly int $penalty;
    public readonly float $fitness;

    /**
     * @param array<string,int> $breakdown raw counts keyed H1..H6, S1..S5
     */
    public function __construct(
        public readonly int $hard,
        public readonly int $soft,
        int $hardWeight,
        public readonly array $breakdown
    ) {
        $this->penalty = $hardWeight * $hard + $soft;
        $this->fitness = 1 / (1 + $this->penalty);
    }

    public function isFeasible(): bool
    {
        return $this->hard === 0;
    }

    public function toArray(): array
    {
        return [
            'penalty' => $this->penalty,
            'fitness' => $this->fitness,
            'hard' => $this->hard,
            'soft' => $this->soft,
            'breakdown' => $this->breakdown,
        ];
    }
}
