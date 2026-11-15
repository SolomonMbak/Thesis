<?php
declare(strict_types=1);

namespace App\Engine;

/**
 * Outcome of one GA run.
 */
final class RunResult
{
    public function __construct(
        public readonly Chromosome $best,
        public readonly Evaluation $evaluation,
        public readonly int $generations,
        public readonly ?int $firstFeasibleGeneration,
        public readonly int $runtimeMs,
        public readonly float $peakMemoryMb,
        /** true when stopped on request, false when it finished by itself */
        public readonly bool $stopped
    ) {
    }
}
