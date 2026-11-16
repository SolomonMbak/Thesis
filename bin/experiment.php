<?php
/**
 * Run a batch of GA runs for Chapter Four (BUILD_SPEC section 15), one after
 * another in this process. Every run is a normal job with the given label,
 * so it appears under Timetables and can be exported.
 *
 *   php bin/experiment.php --label=SGA --repair=0 --seeds=1001-1030
 *   php bin/experiment.php --label=RGA --repair=1 --seeds=1001-1030
 *   php bin/experiment.php --label=POP50 --repair=1 --population=50 --seeds=2001-2010
 *   php bin/experiment.php --label=MUT01 --repair=1 --mutation=0.01 --seeds=2001-2010
 *
 * Other options (default = the settings page values):
 *   --generations=N --stall=N --crossover=R --elite=N --tournament=N --greedy=R --moves=N --log-every=N
 *   --seeds=1001,1005,1009   a list instead of a range
 *   --rerun                  run seeds again even if this label already has a finished run for them
 *   --school=<slug>          the school to run in (needed when there is more than one)
 *
 * Seeds already finished under the same label are skipped, so an interrupted
 * batch can be restarted with the same command.
 */
declare(strict_types=1);

use App\Core\Config;
use App\Core\Db;
use App\Data\JobRepository;
use App\Data\SemesterRepository;
use App\Data\SchoolSettingsRepository;
use App\Engine\GaParams;
use App\Core\SchoolContext;
use App\Services\CliSchool;
use App\Services\FeasibilityService;
use App\Services\GaDefaults;
use App\Services\JobRunner;

require dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line.\n");
}
set_time_limit(0);
ini_set('memory_limit', (string) Config::get('memory_limit_cli', '512M'));

$opts = getopt('', ['label:', 'repair:', 'seeds:', 'population:', 'mutation:', 'generations:', 'stall:', 'crossover:',
    'elite:', 'tournament:', 'greedy:', 'moves:', 'log-every:', 'rerun', 'school:']);
$label = trim((string) ($opts['label'] ?? ''));
if ($label === '' || !isset($opts['repair'], $opts['seeds'])) {
    fwrite(STDERR, "Usage: php bin/experiment.php --label=RGA --repair=1 --seeds=1001-1030 [--school=demo] [options]\n");
    exit(2);
}

$seeds = parseSeeds((string) $opts['seeds']);
if (!$seeds) {
    fwrite(STDERR, "Could not read --seeds. Use a range like 1001-1030 or a list like 1001,1002.\n");
    exit(2);
}

$pdo = Db::pdo();
$school = CliSchool::fromOptions($pdo, $opts);
$semester = (new SemesterRepository($pdo, $school))->active();
if (!$semester) {
    fwrite(STDERR, "There is no active semester.\n");
    exit(1);
}
$feasibility = (new FeasibilityService($pdo, $school))->run((int) $semester['id'], null);
if (!$feasibility['ok']) {
    fwrite(STDERR, 'The feasibility check failed (' . count($feasibility['failures']) . " problems). Fix them first.\n");
    exit(1);
}

$params = GaDefaults::load(new SchoolSettingsRepository($pdo, $school));
$map = ['population' => 'population_size', 'mutation' => 'mutation_rate', 'generations' => 'max_generations',
    'stall' => 'stall_generations', 'crossover' => 'crossover_rate', 'elite' => 'elite_count',
    'tournament' => 'tournament_size', 'greedy' => 'greedy_share', 'moves' => 'max_repair_moves', 'log-every' => 'log_every'];
foreach ($map as $option => $column) {
    if (isset($opts[$option])) {
        $params[$column] = $opts[$option];
    }
}
$params['repair_enabled'] = (string) $opts['repair'] === '1' ? 1 : 0;
$errors = GaParams::validate($params + ['random_seed' => $seeds[0]]);
if ($errors) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(2);
}

$jobs = new JobRepository($pdo, $school);
$runner = new JobRunner($pdo);
$done = $skipped = $failed = 0;
$started = microtime(true);
printf("School %s, experiment %s: %d seed(s), repair %s, population %s, mutation %s, up to %s generations (stall %s)\n",
    $school->slug(), $label, count($seeds), $params['repair_enabled'] ? 'on' : 'off', $params['population_size'],
    $params['mutation_rate'], $params['max_generations'], $params['stall_generations']);

foreach ($seeds as $seed) {
    if (!isset($opts['rerun']) && alreadyDone($pdo, $school, $label, $seed)) {
        $skipped++;
        echo "  seed {$seed}: already done, skipped\n";
        continue;
    }
    $jobId = $jobs->createGaJob((int) $semester['id'], ['random_seed' => $seed] + $params, $label, null);
    $status = waitAndRun($runner, $jobId);
    if ($status === null) {
        echo "  seed {$seed}: job #{$jobId} was taken by another worker; it will still be labelled {$label}\n";
        continue;
    }
    $job = $jobs->find($jobId);
    $status === 'failed' ? $failed++ : $done++;
    printf("  seed %d: job #%d %s, V_H %s, penalty %s, first feasible %s, %s generations, %.1f s\n",
        $seed, $jobId, $status, $job['hard_violations'] ?? '-', $job['best_penalty'] ?? '-',
        $job['first_feasible_generation'] ?? 'never', $job['generations_run'] ?? '-', (int) $job['runtime_ms'] / 1000);
}
printf("Done: %d run, %d skipped, %d failed, %.1f minutes. Next: php bin/export_results.php --label=%s\n",
    $done, $skipped, $failed, (microtime(true) - $started) / 60, $label);
exit($failed > 0 ? 1 : 0);

function parseSeeds(string $text): array
{
    if (preg_match('/^(\d+)-(\d+)$/', $text, $m) && (int) $m[2] >= (int) $m[1] && (int) $m[2] - (int) $m[1] < 10000) {
        return range((int) $m[1], (int) $m[2]);
    }
    $list = array_filter(array_map('trim', explode(',', $text)), fn ($s) => ctype_digit($s));
    return array_values(array_map('intval', $list));
}

/**
 * Claim and run the job, waiting while max_concurrent_jobs other runs are
 * going, so experiment runs never share the processor with each other
 * (their run times stay comparable).
 */
function waitAndRun(JobRunner $runner, int $jobId): ?string
{
    $queue = new App\Data\JobQueue(Db::pdo());
    while (true) {
        $status = $runner->claimAndRun($jobId);
        if ($status !== null || $queue->position($jobId) === null) {
            return $status;
        }
        sleep(5);
    }
}

function alreadyDone(PDO $pdo, SchoolContext $school, string $label, int $seed): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ga_jobs WHERE school_id = ? AND label = ? AND random_seed = ? AND status = 'finished'");
    $stmt->execute([$school->id, $label, $seed]);
    return (int) $stmt->fetchColumn() > 0;
}
