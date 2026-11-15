<?php
declare(strict_types=1);

namespace App\Engine;

/**
 * Genetic algorithm parameters (BUILD_SPEC 8.7). Keys match the ga_jobs
 * columns. Nothing in the engine has a hard-coded parameter: everything
 * comes in through this object.
 */
final class GaParams
{
    /** key => [label, type, min, max] */
    public const FIELDS = [
        'population_size' => ['Population size', 'int', 4, 2000],
        'max_generations' => ['Maximum generations', 'int', 1, 1000000],
        'stall_generations' => ['Stop after this many generations without improvement', 'int', 1, 1000000],
        'tournament_size' => ['Tournament size', 'int', 2, 20],
        'crossover_rate' => ['Crossover rate', 'float', 0, 1],
        'mutation_rate' => ['Mutation rate', 'float', 0, 1],
        'elite_count' => ['Elite count', 'int', 0, 100],
        'greedy_share' => ['Greedy share of the first population', 'float', 0, 1],
        'max_repair_moves' => ['Maximum repair moves per child', 'int', 0, 10000],
        'log_every' => ['Record progress every N generations', 'int', 1, 10000],
    ];

    public function __construct(
        public readonly int $populationSize,
        public readonly int $maxGenerations,
        public readonly int $stallGenerations,
        public readonly int $tournamentSize,
        public readonly float $crossoverRate,
        public readonly float $mutationRate,
        public readonly int $eliteCount,
        public readonly float $greedyShare,
        public readonly int $maxRepairMoves,
        public readonly bool $repairEnabled,
        public readonly int $seed,
        public readonly int $logEvery
    ) {
        $errors = self::validate($this->toArray());
        if ($errors) {
            throw new \InvalidArgumentException(implode(' ', $errors));
        }
    }

    public static function fromArray(array $a): self
    {
        return new self(
            (int) $a['population_size'],
            (int) $a['max_generations'],
            (int) $a['stall_generations'],
            (int) $a['tournament_size'],
            (float) $a['crossover_rate'],
            (float) $a['mutation_rate'],
            (int) $a['elite_count'],
            (float) $a['greedy_share'],
            (int) $a['max_repair_moves'],
            (bool) (int) ($a['repair_enabled'] ?? 1),
            (int) ($a['random_seed'] ?? 1),
            (int) ($a['log_every'] ?? 1)
        );
    }

    public function toArray(): array
    {
        return [
            'population_size' => $this->populationSize,
            'max_generations' => $this->maxGenerations,
            'stall_generations' => $this->stallGenerations,
            'tournament_size' => $this->tournamentSize,
            'crossover_rate' => $this->crossoverRate,
            'mutation_rate' => $this->mutationRate,
            'elite_count' => $this->eliteCount,
            'greedy_share' => $this->greedyShare,
            'max_repair_moves' => $this->maxRepairMoves,
            'repair_enabled' => $this->repairEnabled ? 1 : 0,
            'random_seed' => $this->seed,
            'log_every' => $this->logEvery,
        ];
    }

    /**
     * Check raw values (e.g. from a form). Returns field => plain English message.
     * @return array<string,string>
     */
    public static function validate(array $a): array
    {
        $errors = [];
        foreach (self::FIELDS as $key => [$label, $type, $min, $max]) {
            $raw = $a[$key] ?? null;
            $text = is_scalar($raw) ? trim((string) $raw) : '';
            $valid = $type === 'int' ? preg_match('/^-?\d+$/', $text) === 1 : is_numeric($text);
            if (!$valid) {
                $errors[$key] = "{$label} must be " . ($type === 'int' ? 'a whole number.' : 'a number.');
            } elseif ((float) $text < $min || (float) $text > $max) {
                $errors[$key] = "{$label} must be between {$min} and {$max}.";
            }
        }
        if (!$errors) {
            if ((int) $a['elite_count'] >= (int) $a['population_size']) {
                $errors['elite_count'] = 'Elite count must be smaller than the population size.';
            }
            if ((int) $a['tournament_size'] > (int) $a['population_size']) {
                $errors['tournament_size'] = 'Tournament size cannot be larger than the population size.';
            }
        }
        $seed = $a['random_seed'] ?? null;
        if ($seed !== null && $seed !== '' && !preg_match('/^\d{1,9}$/', trim((string) $seed))) {
            $errors['random_seed'] = 'Random seed must be a whole number from 0 to 999999999, or blank.';
        }
        return $errors;
    }
}
