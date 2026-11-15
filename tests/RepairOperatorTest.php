<?php
declare(strict_types=1);

namespace Tests;

use App\Engine\Chromosome;
use App\Engine\FitnessEvaluator;
use App\Engine\RepairOperator;
use App\Engine\Rng;

final class RepairOperatorTest extends TestCase
{
    /** Six sessions (own lecturer, own group) all planted in room 0 at period 0. */
    private function plantedRoomClash(): array
    {
        $sessions = [];
        for ($i = 0; $i < 6; $i++) {
            $sessions[] = [$i, $i, $i];
        }
        $data = $this->context([
            'days' => 2, 'perDay' => 5, 'rooms' => [[100], [100], [100]],
            'lecturers' => array_fill(0, 6, []), 'groups' => 6, 'courses' => 6, 'sessions' => $sessions,
        ])->data;
        return [$data, new Chromosome(array_fill(0, 6, 0), array_fill(0, 6, 0))];
    }

    public function testPlantedClashesWithFreeSlotsEndFeasible(): void
    {
        [$data, $c] = $this->plantedRoomClash();
        $evaluator = new FitnessEvaluator($data);
        $this->assertSame(5, $evaluator->evaluate($c)->breakdown['H2']);

        $moves = (new RepairOperator($data, 30, new Rng(1)))->repair($c);
        $this->assertTrue($moves > 0 && $moves <= 5, "Expected 1 to 5 moves, got {$moves}.");
        $this->assertSame(0, $evaluator->evaluate($c)->hard, 'Repair should remove every clash.');
    }

    public function testLecturerAndGroupClashesAreRepaired(): void
    {
        // Same lecturer for both sessions, same group, same period: H1 and H3.
        $data = $this->context([
            'days' => 1, 'perDay' => 6, 'rooms' => [[100], [100]], 'courses' => 2,
            'sessions' => [[0, 0, 0, 50, false, 2], [0, 0, 1]],
        ])->data;
        $c = new Chromosome([0, 1], [0, 1]);
        $evaluator = new FitnessEvaluator($data);
        $this->assertTrue($evaluator->evaluate($c)->hard > 0);
        (new RepairOperator($data, 30, new Rng(3)))->repair($c);
        $this->assertSame(0, $evaluator->evaluate($c)->hard);
    }

    public function testNonClashingGenesNeverMove(): void
    {
        $sessions = [];
        for ($i = 0; $i < 8; $i++) {
            $sessions[] = [$i, $i, $i];
        }
        $data = $this->context([
            'days' => 1, 'perDay' => 10, 'rooms' => [[100], [100]],
            'lecturers' => array_fill(0, 8, []), 'groups' => 8, 'courses' => 8, 'sessions' => $sessions,
        ])->data;
        // Genes 0..3 clash in room 0 at period 0; genes 4..7 sit alone.
        $start = [0, 0, 0, 0, 2, 4, 6, 8];
        $room = [0, 0, 0, 0, 1, 1, 1, 1];
        for ($seed = 1; $seed <= 10; $seed++) {
            $c = new Chromosome($start, $room);
            (new RepairOperator($data, 30, new Rng($seed)))->repair($c);
            for ($s = 4; $s < 8; $s++) {
                $this->assertSame([$start[$s], $room[$s]], [$c->start[$s], $c->room[$s]], "Seed {$seed}, gene {$s} moved:");
            }
        }
    }

    public function testMaxMovesIsRespected(): void
    {
        [$data] = $this->plantedRoomClash();
        foreach ([0, 1, 3] as $max) {
            $c = new Chromosome(array_fill(0, 6, 0), array_fill(0, 6, 0));
            $moves = (new RepairOperator($data, $max, new Rng(5)))->repair($c);
            $changed = 0;
            for ($s = 0; $s < 6; $s++) {
                $changed += ($c->start[$s] !== 0 || $c->room[$s] !== 0) ? 1 : 0;
            }
            $this->assertSame($max, $moves, "maxMoves {$max}:");
            $this->assertTrue($changed <= $max, "maxMoves {$max} but {$changed} genes changed.");
        }
    }

    public function testRepairPrefersLowerSoftCost(): void
    {
        // Lecturer prefers period 3 only; the clashing gene should move there (S1 = 0).
        $data = $this->context([
            'days' => 1, 'perDay' => 6, 'rooms' => [[100]], 'groups' => 2, 'courses' => 2,
            'lecturers' => [[], ['preferred' => [3]]],
            'sessions' => [[0, 0, 0], [1, 1, 1]],
        ])->data;
        for ($seed = 1; $seed <= 5; $seed++) {
            $c = new Chromosome([0, 0], [0, 0]);
            (new RepairOperator($data, 30, new Rng($seed)))->repair($c);
            $moved = $c->start[0] !== 0 ? 0 : 1;
            if ($moved === 1) {
                $this->assertSame(3, $c->start[1], "Seed {$seed}: lecturer 2 should get the preferred period.");
            }
            $this->assertSame(0, (new FitnessEvaluator($data))->evaluate($c)->hard);
        }
    }

    public function testNothingToRepairMeansNoMoves(): void
    {
        [$data] = $this->plantedRoomClash();
        $c = new Chromosome([0, 1, 2, 3, 4, 5], [0, 0, 0, 0, 0, 0]);
        $this->assertSame(0, (new RepairOperator($data, 30, new Rng(1)))->repair($c));
    }
}
