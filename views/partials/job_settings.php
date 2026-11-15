<?php
/**
 * Job parameters as a definition list.
 * @var array $job
 */
$weights = $job['weights_json'] ? json_decode((string) $job['weights_json'], true) : null;
$rows = $job['job_type'] === 'manual' ? [] : [
    'Repair' => $job['repair_enabled'] ? 'On (GA with targeted repair)' : 'Off (standard GA)',
    'Random seed' => $job['random_seed'],
    'Population size' => $job['population_size'],
    'Maximum generations' => $job['max_generations'],
    'Stall generations' => $job['stall_generations'],
    'Tournament size' => $job['tournament_size'],
    'Crossover rate' => $job['crossover_rate'],
    'Mutation rate' => $job['mutation_rate'],
    'Elite count' => $job['elite_count'],
    'Greedy share' => $job['greedy_share'],
    'Maximum repair moves' => $job['max_repair_moves'],
];
$rows += [
    'Created' => $job['created_at'],
    'Started' => $job['started_at'] ?? '-',
    'Finished' => $job['finished_at'] ?? '-',
    'Weights used' => $weights ? implode(', ', array_map(fn ($k, $v) => "{$k} {$v}", array_keys($weights), $weights)) : 'Not yet recorded',
];
?>
<dl class="settings-list">
  <?php foreach ($rows as $label => $value): ?>
    <dt><?= e($label) ?></dt><dd><?= e($value) ?></dd>
  <?php endforeach; ?>
</dl>
