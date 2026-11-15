<?php
declare(strict_types=1);

namespace App\Engine;

/**
 * One candidate timetable (BUILD_SPEC 8.2). Gene s = (start period, room)
 * for class session s, kept as two parallel int arrays for speed.
 * The evaluation is cached and cleared whenever a gene changes.
 */
final class Chromosome
{
    public ?Evaluation $evaluation = null;

    /**
     * @param int[] $start start period index per session
     * @param int[] $room  room index per session
     */
    public function __construct(public array $start, public array $room)
    {
    }

    public function setGene(int $s, int $start, int $room): void
    {
        $this->start[$s] = $start;
        $this->room[$s] = $room;
        $this->evaluation = null;
    }

    /** Independent copy (PHP arrays copy on write, so this is cheap until changed). */
    public function copy(): self
    {
        $clone = new self($this->start, $this->room);
        $clone->evaluation = $this->evaluation;
        return $clone;
    }

    public function invalidate(): void
    {
        $this->evaluation = null;
    }

    public function size(): int
    {
        return count($this->start);
    }
}
