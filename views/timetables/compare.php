<?php
/**
 * Side-by-side comparison of timetables.
 * @var array $jobs
 */
$measures = [
    'Type' => fn ($j) => $j['job_type'] === 'manual' ? 'Manual' : ($j['repair_enabled'] ? 'GA + repair' : 'Standard GA'),
    'Label' => fn ($j) => $j['label'],
    'Status' => fn ($j) => ucfirst($j['status']),
    'Hard violations V_H' => fn ($j) => $j['hard_violations'],
];
foreach (['H1', 'H2', 'H3', 'H4', 'H5', 'H6'] as $code) {
    $measures["  {$code}"] = fn ($j) => $j['breakdown'][$code] ?? '';
}
$measures['Soft penalty'] = fn ($j) => $j['soft_penalty'];
foreach (['S1', 'S2', 'S3', 'S4', 'S5'] as $code) {
    $measures["  {$code}"] = fn ($j) => $j['breakdown'][$code] ?? '';
}
$measures += [
    'Total penalty' => fn ($j) => $j['best_penalty'],
    'Fitness' => fn ($j) => number_format((float) $j['best_fitness'], 8),
    'Generations' => fn ($j) => $j['job_type'] === 'ga' ? $j['generations_run'] : '',
    'First feasible generation' => fn ($j) => $j['job_type'] === 'ga' ? ($j['first_feasible_generation'] ?? 'never') : '',
    'Run time (s)' => fn ($j) => $j['job_type'] === 'ga' ? number_format((int) $j['runtime_ms'] / 1000, 1) : '',
    'Peak memory (MB)' => fn ($j) => $j['peak_memory_mb'],
    'Random seed' => fn ($j) => $j['random_seed'],
];
?>
<h1>Compare timetables</h1>
<p><a href="<?= e(url('timetables')) ?>">Back to timetables</a></p>
<div class="table-wrap">
  <table class="table">
    <thead>
      <tr><th scope="col">Measure</th>
        <?php foreach ($jobs as $job): ?>
          <th scope="col" class="num"><a href="<?= e(url('timetables/view', ['id' => $job['id']])) ?>">#<?= e($job['id']) ?></a></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($measures as $label => $fn): ?>
        <tr>
          <th scope="row"<?= str_starts_with($label, '  ') ? ' class="indent"' : '' ?>><?= e(trim($label)) ?></th>
          <?php foreach ($jobs as $job): ?><td class="num"><?= e($fn($job)) ?></td><?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
