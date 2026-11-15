<?php
declare(strict_types=1);

namespace App\Engine;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * The single source of randomness for a run (BUILD_SPEC 8.9).
 * Mersenne Twister with a fixed seed: the same seed, data and settings
 * always give the same timetable.
 */
final class Rng
{
    private Randomizer $randomizer;

    public function __construct(public readonly int $seed)
    {
        $this->randomizer = new Randomizer(new Mt19937($seed));
    }

    /** Whole number from $min to $max inclusive. */
    public function int(int $min, int $max): int
    {
        return $this->randomizer->getInt($min, $max);
    }

    /** Number in [0, 1). */
    public function float(): float
    {
        return $this->randomizer->getInt(0, 9007199254740991) / 9007199254740992;
    }

    /** True with probability $p. */
    public function chance(float $p): bool
    {
        if ($p <= 0.0) {
            return false;
        }
        return $p >= 1.0 || $this->float() < $p;
    }

    /** Random element of a non-empty list. */
    public function pick(array $list): mixed
    {
        return $list[$this->randomizer->getInt(0, count($list) - 1)];
    }

    /** Fisher-Yates shuffle; returns a new list. */
    public function shuffle(array $list): array
    {
        $list = array_values($list);
        for ($i = count($list) - 1; $i > 0; $i--) {
            $j = $this->randomizer->getInt(0, $i);
            [$list[$i], $list[$j]] = [$list[$j], $list[$i]];
        }
        return $list;
    }
}
