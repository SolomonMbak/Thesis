<?php
/**
 * Run the GA on the active semester with repair on and off and print the
 * results (BUILD_SPEC increment 3). Nothing is saved.
 *
 *   php bin/engine_demo.php [--generations=300] [--seed=1001] [--population=100] [--greedy=0.2]
 */
declare(strict_types=1);

use App\Core\Db;
use App\Data\SemesterRepository;
use App\Data\SettingsRepository;
use App\Engine\GaParams;
use App\Engine\GeneticAlgorithm;
use App\Services\GaDefaults;
use App\Services\ProblemLoader;

require dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line.\n");
}
ini_set('memory_limit', '1G');

$opts = getopt('', ['generations:', 'seed:', 'population:', 'greedy:']);
$pdo = Db::pdo();
$semester = (new SemesterRepository($pdo))->active();
if (!$semester) {
    fwrite(STDERR, "No active semester. Run php bin/seed.php first.\n");
    exit(1);
}
$ctx = (new ProblemLoader($pdo))->load((int) $semester['id']);
$d = $ctx->data;
printf("Problem: %d sessions (%d hours), %d periods (%d open), %d rooms, %d lecturers, %d groups\n\n",
    $d->S, array_sum($d->sDur), $d->P, count(array_filter($d->blocked, fn ($b) => !$b)), $d->R, $d->L, $d->G);

$base = GaDefaults::load(new SettingsRepository($pdo));
$base['max_generations'] = (int) ($opts['generations'] ?? 300);
$base['stall_generations'] = $base['max_generations'];   // run the full count for a fair comparison
$base['random_seed'] = (int) ($opts['seed'] ?? 1001);
if (isset($opts['greedy'])) {
    $base['greedy_share'] = (float) $opts['greedy'];
}
if (isset($opts['population'])) {
    $base['population_size'] = (int) $opts['population'];
}

foreach ([0 => 'Standard GA (repair off)', 1 => 'GA with targeted repair (repair on)'] as $repair => $label) {
    $params = GaParams::fromArray(['repair_enabled' => $repair] + $base);
    echo "{$label}, seed {$params->seed}, {$params->maxGenerations} generations, population {$params->populationSize}\n";
    $result = (new GeneticAlgorithm($d, $params))->run(function (int $g, array $s): void {
        if ($g % 50 === 0) {
            printf("  gen %4d  best penalty %7d  V_H %3d  soft %4d  %6.1fs\n", $g, $s['best_penalty'], $s['hard'], $s['soft'], $s['elapsed_ms'] / 1000);
        }
    });
    $e = $result->evaluation;
    printf("  Result: V_H = %d, penalty = %d, soft = %d, first feasible generation = %s, time = %.1fs, peak memory = %.1f MB\n",
        $e->hard, $e->penalty, $e->soft, $result->firstFeasibleGeneration ?? 'never', $result->runtimeMs / 1000, $result->peakMemoryMb);
    echo '  Breakdown: ' . implode(' ', array_map(fn ($k, $v) => "{$k}={$v}", array_keys($e->breakdown), $e->breakdown)) . "\n\n";
}
