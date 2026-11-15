<?php
declare(strict_types=1);

namespace Tests;

use App\Engine\Chromosome;
use App\Engine\FitnessEvaluator;
use App\Engine\GaParams;
use App\Engine\GeneticAlgorithm;
use App\Engine\MoveMutation;
use App\Engine\Population;
use App\Engine\ProblemData;
use App\Engine\Rng;
use App\Engine\UniformCrossover;

/**
 * Operators, elitism and determinism of the whole run.
 */
final class GeneticAlgorithmTest extends TestCase
{
    private function problem(): ProblemData
    {
        $sessions = [];
        for ($i = 0; $i < 24; $i++) {
            $lab = $i % 8 === 0;
            $sessions[] = [$i % 5, $i % 3, intdiv($i, 2), $lab ? 50 : 50 + ($i % 3) * 20, $lab, $lab ? 2 : 1];
        }
        return $this->context([
            'days' => 3, 'perDay' => 6, 'blocked' => [17], 'groups' => 3, 'courses' => 12,
            'rooms' => [[60], [100], [120], [60, true]],
            'lecturers' => [['unavailable' => [0, 1]], ['preferred' => [2, 3, 4]], [], ['unavailable' => [7]], []],
            'sessions' => $sessions,
        ])->data;
    }

    private function params(array $overrides = []): GaParams
    {
        return GaParams::fromArray($overrides + [
            'population_size' => 20, 'max_generations' => 40, 'stall_generations' => 40, 'tournament_size' => 3,
            'crossover_rate' => 0.8, 'mutation_rate' => 0.05, 'elite_count' => 2, 'greedy_share' => 0.2,
            'max_repair_moves' => 30, 'repair_enabled' => 1, 'random_seed' => 1001, 'log_every' => 1,
        ]);
    }

    public function testSameSeedGivesTheSameResult(): void
    {
        $data = $this->problem();
        $a = (new GeneticAlgorithm($data, $this->params()))->run();
        $b = (new GeneticAlgorithm($data, $this->params()))->run();
        $this->assertSame($a->best->start, $b->best->start);
        $this->assertSame($a->best->room, $b->best->room);
        $this->assertSame($a->evaluation->penalty, $b->evaluation->penalty);
        $this->assertSame($a->generations, $b->generations);
    }

    public function testDifferentSeedGivesADifferentRun(): void
    {
        $data = $this->problem();
        $curve = function (int $seed) use ($data): array {
            $points = [];
            $result = (new GeneticAlgorithm($data, $this->params(['random_seed' => $seed])))
                ->run(function (int $g, array $s) use (&$points): void {
                    $points[] = $s['avg_penalty'];
                });
            return [$result->best->start, $points];
        };
        $this->assertTrue($curve(1001) !== $curve(2002), 'Two seeds should not give identical runs.');
    }

    public function testCrossoverKeepsGenesAtTheirIndex(): void
    {
        $a = new Chromosome([0, 1, 2, 3, 4, 5, 6, 7], [0, 0, 0, 0, 0, 0, 0, 0]);
        $b = new Chromosome([10, 11, 12, 13, 14, 15, 16, 17], [1, 1, 1, 1, 1, 1, 1, 1]);
        [$c1, $c2] = (new UniformCrossover(new Rng(9)))->cross($a, $b);
        $swapped = 0;
        for ($i = 0; $i < 8; $i++) {
            $fromA = [$a->start[$i], $a->room[$i]];
            $fromB = [$b->start[$i], $b->room[$i]];
            $g1 = [$c1->start[$i], $c1->room[$i]];
            $g2 = [$c2->start[$i], $c2->room[$i]];
            $this->assertTrue(($g1 === $fromA && $g2 === $fromB) || ($g1 === $fromB && $g2 === $fromA),
                "Gene {$i} must come whole from a parent at the same index.");
            $swapped += $g1 === $fromB ? 1 : 0;
        }
        $this->assertTrue($swapped > 0 && $swapped < 8, 'A fair coin should swap some genes but not all.');
        $this->assertSame([0, 1, 2, 3, 4, 5, 6, 7], $a->start, 'Parents are unchanged.');
    }

    public function testMutationStaysWithinDomains(): void
    {
        $data = $this->problem();
        $rng = new Rng(4);
        $c = (new \App\Engine\Initialiser($data, $rng))->random();
        $changed = (new MoveMutation($data, 1.0, $rng))->mutate($c);
        $this->assertSame($data->S, $changed, 'Rate 1.0 changes every gene.');
        for ($s = 0; $s < $data->S; $s++) {
            $this->assertTrue(in_array($c->start[$s], $data->allowedStarts[$s], true), "Start of {$s} outside its domain.");
            $this->assertTrue(in_array($c->room[$s], $data->allowedRooms[$s], true), "Room of {$s} outside its domain.");
        }
        $this->assertSame(0, (new MoveMutation($data, 0.0, $rng))->mutate($c), 'Rate 0 changes nothing.');
    }

    public function testInitialPopulationIsWithinDomains(): void
    {
        $data = $this->problem();
        $population = (new \App\Engine\Initialiser($data, new Rng(8)))->population(10, 0.5);
        $this->assertCount(10, $population);
        foreach ($population as $c) {
            for ($s = 0; $s < $data->S; $s++) {
                $this->assertTrue(in_array($c->start[$s], $data->allowedStarts[$s], true));
                $this->assertTrue(in_array($c->room[$s], $data->allowedRooms[$s], true));
            }
        }
    }

    public function testElitismKeepsTheBest(): void
    {
        // The best penalty reported each generation never gets worse.
        $data = $this->problem();
        $best = [];
        (new GeneticAlgorithm($data, $this->params(['repair_enabled' => 0, 'mutation_rate' => 0.3])))
            ->run(function (int $g, array $s) use (&$best): void {
                $best[] = $s['best_penalty'];
            });
        for ($i = 1; $i < count($best); $i++) {
            $this->assertTrue($best[$i] <= $best[$i - 1], "Best penalty rose at generation {$i}.");
        }
    }

    public function testPopulationEliteIsSortedBestFirst(): void
    {
        $data = $this->context(['days' => 1, 'perDay' => 10, 'sessions' => [[0, 0, 0]]])->data;
        $evaluator = new FitnessEvaluator($data);
        $members = [];
        foreach ([9, 0, 9, 0] as $start) {
            $c = new Chromosome([$start], [0]);
            $evaluator->evaluate($c);
            $members[] = $c;
        }
        $elite = (new Population($members))->elite(2);
        $this->assertSame(0, $elite[0]->evaluation->penalty);
        $this->assertSame(0, $elite[1]->evaluation->penalty);
    }

    public function testRepairFindsAFeasibleTimetable(): void
    {
        $result = (new GeneticAlgorithm($this->problem(), $this->params(['greedy_share' => 0])))->run();
        $this->assertSame(0, $result->evaluation->hard, 'RGA should reach V_H = 0 on this small problem.');
        $this->assertTrue($result->firstFeasibleGeneration !== null);
    }

    public function testStopRequestIsHonoured(): void
    {
        $result = (new GeneticAlgorithm($this->problem(), $this->params(['max_generations' => 500, 'stall_generations' => 500])))
            ->run(null, fn () => true);
        $this->assertTrue($result->stopped);
        $this->assertSame(10, $result->generations, 'Stop is checked every 10 generations.');
    }

    public function testStallStopsOnceFeasible(): void
    {
        $result = (new GeneticAlgorithm($this->problem(), $this->params(['max_generations' => 1000, 'stall_generations' => 5])))->run();
        $this->assertTrue($result->generations < 1000, 'A feasible run with no improvement for 5 generations stops early.');
        $this->assertFalse($result->stopped);
    }

    public function testEmptyDomainIsRejected(): void
    {
        $data = $this->context(['days' => 1, 'perDay' => 4, 'rooms' => [[10]], 'sessions' => [[0, 0, 0, 50]]])->data;
        $this->assertThrows(\InvalidArgumentException::class, fn () => new GeneticAlgorithm($data, $this->params()));
    }

    public function testParamsValidation(): void
    {
        $errors = GaParams::validate(['population_size' => '10', 'max_generations' => '0', 'stall_generations' => '5',
            'tournament_size' => '20', 'crossover_rate' => '1.5', 'mutation_rate' => 'x', 'elite_count' => '1',
            'greedy_share' => '0.2', 'max_repair_moves' => '30', 'log_every' => '1', 'random_seed' => 'abc']);
        foreach (['max_generations', 'crossover_rate', 'mutation_rate', 'random_seed'] as $key) {
            $this->assertArrayHasKey($key, $errors);
        }
    }
}
