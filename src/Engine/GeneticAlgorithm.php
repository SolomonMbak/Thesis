<?php
declare(strict_types=1);

namespace App\Engine;

/**
 * The genetic algorithm main loop (BUILD_SPEC 8.7).
 *
 * repair_enabled = 0 gives the standard GA baseline (SGA); = 1 gives the
 * proposed GA with targeted repair (RGA). Nothing else differs between them.
 *
 * Callbacks keep the engine free of database code:
 *   onGeneration(int $generation, array $stats)  every log_every generations (and generation 0)
 *   shouldStop(): bool                            asked every 10 generations
 */
final class GeneticAlgorithm
{
    private const STOP_CHECK_EVERY = 10;

    public function __construct(private ProblemData $d, private GaParams $params)
    {
        for ($s = 0; $s < $d->S; $s++) {
            if (!$d->allowedStarts[$s] || !$d->allowedRooms[$s]) {
                throw new \InvalidArgumentException(
                    "Session {$s} has no valid start or room. Run the feasibility check and fix the problems it lists."
                );
            }
        }
        if ($d->S === 0) {
            throw new \InvalidArgumentException('There are no class sessions to schedule.');
        }
    }

    public function run(?callable $onGeneration = null, ?callable $shouldStop = null): RunResult
    {
        $started = hrtime(true);
        memory_reset_peak_usage();   // so peak memory is this run's, even when several runs share a process
        $p = $this->params;
        $rng = new Rng($p->seed);
        $evaluator = new FitnessEvaluator($this->d);
        $selection = new TournamentSelection($p->tournamentSize, $rng);
        $crossover = new UniformCrossover($rng);
        $mutation = new MoveMutation($this->d, $p->mutationRate, $rng);
        $repair = $p->repairEnabled ? new RepairOperator($this->d, $p->maxRepairMoves, $rng) : null;

        $members = (new Initialiser($this->d, $rng))->population($p->populationSize, $p->greedyShare);
        foreach ($members as $c) {
            $evaluator->evaluate($c);
        }
        $population = new Population($members);
        $best = $population->best()->copy();
        $firstFeasible = $best->evaluation->isFeasible() ? 0 : null;
        $lastImprovement = 0;
        $generation = 0;
        $stopped = false;
        $this->report($onGeneration, 0, $best, $population, $started);

        for ($t = 1; $t <= $p->maxGenerations; $t++) {
            $generation = $t;
            $next = array_map(fn (Chromosome $c) => $c->copy(), $population->elite($p->eliteCount));
            while (count($next) < $p->populationSize) {
                $a = $selection->select($population->members);
                $b = $selection->select($population->members);
                $children = $rng->chance($p->crossoverRate) ? $crossover->cross($a, $b) : [$a->copy(), $b->copy()];
                foreach ($children as $child) {
                    if (count($next) >= $p->populationSize) {
                        break;
                    }
                    $mutation->mutate($child);
                    $repair?->repair($child);
                    $evaluator->evaluate($child);
                    $next[] = $child;
                }
            }
            $population = new Population($next);

            $candidate = $population->best();
            if ($candidate->evaluation->penalty < $best->evaluation->penalty) {
                $best = $candidate->copy();
                $lastImprovement = $t;
            }
            if ($firstFeasible === null && $best->evaluation->isFeasible()) {
                $firstFeasible = $t;
            }
            if ($t % $p->logEvery === 0) {
                $this->report($onGeneration, $t, $best, $population, $started);
            }
            if ($shouldStop !== null && $t % self::STOP_CHECK_EVERY === 0 && $shouldStop()) {
                $stopped = true;
                break;
            }
            if ($best->evaluation->isFeasible() && $t - $lastImprovement >= $p->stallGenerations) {
                break;
            }
        }
        if ($onGeneration !== null && $generation % $p->logEvery !== 0) {
            $this->report($onGeneration, $generation, $best, $population, $started);   // always record the last generation
        }

        return new RunResult(
            $best,
            $best->evaluation,
            $generation,
            $firstFeasible,
            (int) round((hrtime(true) - $started) / 1e6),
            round(memory_get_peak_usage(true) / 1048576, 2),
            $stopped
        );
    }

    private function report(?callable $onGeneration, int $t, Chromosome $best, Population $population, int $started): void
    {
        if ($onGeneration === null) {
            return;
        }
        $e = $best->evaluation;
        $onGeneration($t, [
            'best_penalty' => $e->penalty,
            'best_fitness' => $e->fitness,
            'avg_penalty' => $population->averagePenalty(),
            'hard' => $e->hard,
            'soft' => $e->soft,
            'elapsed_ms' => (int) round((hrtime(true) - $started) / 1e6),
        ]);
    }
}
