<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\SchoolContext;
use PDO;

/**
 * Reads experiment runs (jobs of one school grouped by label) for export and statistics.
 */
final class ExperimentResults
{
    public const COLUMNS = ['job_id', 'label', 'type', 'seed', 'repair', 'population', 'mutation_rate', 'max_generations',
        'V_H', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'soft', 'S1', 'S2', 'S3', 'S4', 'S5', 'penalty', 'fitness',
        'feasible', 'first_feasible_generation', 'generations', 'runtime_ms', 'peak_memory_mb'];

    public function __construct(private PDO $pdo, private SchoolContext $school)
    {
    }

    /** One row per finished run with this label, in seed order. */
    public function runs(string $label): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM ga_jobs WHERE school_id = ? AND label = ? AND status = 'finished' ORDER BY random_seed, id"
        );
        $stmt->execute([$this->school->id, $label]);
        $rows = [];
        foreach ($stmt->fetchAll() as $job) {
            $b = json_decode((string) $job['breakdown_json'], true) ?: [];
            $rows[] = [
                'job_id' => (int) $job['id'],
                'label' => $job['label'],
                'type' => $job['job_type'] === 'manual' ? 'MAN' : ($job['repair_enabled'] ? 'RGA' : 'SGA'),
                'seed' => $job['random_seed'],
                'repair' => $job['repair_enabled'],
                'population' => $job['population_size'],
                'mutation_rate' => $job['mutation_rate'],
                'max_generations' => $job['max_generations'],
                'V_H' => (int) $job['hard_violations'],
                'H1' => $b['H1'] ?? null, 'H2' => $b['H2'] ?? null, 'H3' => $b['H3'] ?? null,
                'H4' => $b['H4'] ?? null, 'H5' => $b['H5'] ?? null, 'H6' => $b['H6'] ?? null,
                'soft' => (int) $job['soft_penalty'],
                'S1' => $b['S1'] ?? null, 'S2' => $b['S2'] ?? null, 'S3' => $b['S3'] ?? null,
                'S4' => $b['S4'] ?? null, 'S5' => $b['S5'] ?? null,
                'penalty' => (int) $job['best_penalty'],
                'fitness' => (float) $job['best_fitness'],
                'feasible' => (int) $job['hard_violations'] === 0 ? 1 : 0,
                'first_feasible_generation' => $job['first_feasible_generation'] !== null ? (int) $job['first_feasible_generation'] : null,
                'generations' => $job['generations_run'] !== null ? (int) $job['generations_run'] : null,
                'runtime_ms' => $job['runtime_ms'] !== null ? (int) $job['runtime_ms'] : null,
                'peak_memory_mb' => $job['peak_memory_mb'] !== null ? (float) $job['peak_memory_mb'] : null,
            ];
        }
        return $rows;
    }

    /**
     * Median best penalty and median V_H per generation across the runs.
     * A run that stopped early keeps its final value for later generations,
     * so every generation has one value per run.
     * @return array<int, array{generation:int, median_penalty:float, median_hard:float}>
     */
    public function medianCurve(array $jobIds): array
    {
        if (!$jobIds) {
            return [];
        }
        $series = [];
        $maxGen = 0;
        $stmt = $this->pdo->prepare('SELECT generation, best_penalty, hard_violations FROM ga_progress WHERE job_id = ? AND school_id = ? ORDER BY generation');
        foreach ($jobIds as $id) {
            $stmt->execute([$id, $this->school->id]);
            foreach ($stmt->fetchAll() as $row) {
                $series[$id][(int) $row['generation']] = [(int) $row['best_penalty'], (int) $row['hard_violations']];
                $maxGen = max($maxGen, (int) $row['generation']);
            }
        }
        $curve = [];
        $last = [];
        for ($g = 0; $g <= $maxGen; $g++) {
            $penalties = $hards = [];
            foreach ($series as $id => $points) {
                if (isset($points[$g])) {
                    $last[$id] = $points[$g];
                }
                if (isset($last[$id])) {
                    $penalties[] = $last[$id][0];
                    $hards[] = $last[$id][1];
                }
            }
            if ($penalties) {
                $curve[] = ['generation' => $g, 'runs' => count($penalties),
                    'median_penalty' => Statistics::median($penalties), 'median_hard' => Statistics::median($hards)];
            }
        }
        return $curve;
    }

    /** The newest finished manual job, optionally by label. */
    public function manual(?string $label = null): ?array
    {
        $sql = "SELECT * FROM ga_jobs WHERE school_id = ? AND job_type = 'manual' AND status = 'finished'" . ($label ? ' AND label = ?' : '') . ' ORDER BY id DESC LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($label ? [$this->school->id, $label] : [$this->school->id]);
        return $stmt->fetch() ?: null;
    }
}
