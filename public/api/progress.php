<?php
/**
 * GET api/progress.php?job=ID&after=G
 * Live progress for the Generate page (BUILD_SPEC section 9):
 * {status, generation, max_generations, best_penalty, hard, soft, elapsed_ms, points: [{g, p, h}]}
 * Only rows after generation G are returned, so polling stays cheap.
 */
declare(strict_types=1);

use App\Core\Api;
use App\Core\Response;
use App\Data\JobRepository;

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$app = Api::boot('GET');
$jobId = (int) $app->request->query('job', 0);
$after = (int) $app->request->query('after', -1);

$jobs = new JobRepository($app->db());
$job = $jobs->find($jobId);
if (!$job) {
    Response::json(['error' => 'That run does not exist.'], 404);
}

$rows = $jobs->progressAfter($jobId, $after);
$last = $rows ? end($rows) : null;
$elapsed = $last ? (int) $last['elapsed_ms'] : (int) ($job['runtime_ms'] ?? 0);
if ($job['status'] === 'running' && $job['started_at']) {
    $elapsed = max($elapsed, (time() - strtotime((string) $job['started_at'])) * 1000);
}

Response::json([
    'status' => $job['status'],
    'generation' => (int) ($job['generations_run'] ?? 0),
    'max_generations' => (int) $job['max_generations'],
    'best_penalty' => $job['best_penalty'] !== null ? (int) $job['best_penalty'] : null,
    'hard' => $job['hard_violations'] !== null ? (int) $job['hard_violations'] : null,
    'soft' => $job['soft_penalty'] !== null ? (int) $job['soft_penalty'] : null,
    'elapsed_ms' => $elapsed,
    'first_feasible' => $job['first_feasible_generation'] !== null ? (int) $job['first_feasible_generation'] : null,
    'notes' => $job['notes'],
    'points' => array_map(fn ($r) => [
        'g' => (int) $r['generation'],
        'p' => (int) $r['best_penalty'],
        'h' => (int) $r['hard_violations'],
    ], $rows),
]);
