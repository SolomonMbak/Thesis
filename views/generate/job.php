<?php
/**
 * Live progress page. assets/js/progress.js polls api/progress.php.
 * @var array $job
 * @var string $launchMode
 * @var int|null $queuePosition
 * @var bool $canStop
 */
use App\Core\Request;

$active = in_array($job['status'], ['queued', 'running'], true);
$statusText = ['finished' => 'Finished', 'failed' => 'Failed', 'stopped' => 'Stopped', 'running' => 'Running', 'queued' => 'Queued'];
?>
<div class="page-head">
  <h1>Run #<?= e($job['id']) ?><?= $job['label'] ? ': ' . e($job['label']) : '' ?></h1>
  <div class="button-row">
    <?php if ($active && $canStop): ?>
      <form method="post" action="<?= e(url('generate/stop')) ?>" class="inline-form"
            data-confirm="Stop this run? The best timetable found so far will be saved." id="stop-form">
        <?= $csrf->field() ?>
        <input type="hidden" name="id" value="<?= e($job['id']) ?>">
        <button type="submit" class="btn btn-danger">Stop</button>
      </form>
    <?php endif; ?>
    <a class="btn btn-primary<?= $active || $job['status'] === 'failed' ? ' hidden' : '' ?>" id="results-link" href="<?= e(url('timetables/view', ['id' => $job['id']])) ?>">See the result</a>
  </div>
</div>

<div id="progress" data-api="<?= e(Request::basePath() . '/api/progress.php') ?>" data-job="<?= e($job['id']) ?>"
     data-max="<?= e($job['max_generations']) ?>" data-population="<?= e((int) $job['population_size']) ?>">
  <p class="alert alert-info" id="queued-hint"<?= $job['status'] === 'queued' ? '' : ' hidden' ?>>
    Waiting to start. <span id="queue-position"><?= $queuePosition ? 'Your run is number ' . e($queuePosition) . ' in the queue.' : '' ?></span>
    <?= $launchMode === 'queue'
      ? 'The background worker starts it, usually within a minute.'
      : 'It starts as soon as the run before it finishes.' ?>
    You can close this page.
  </p>

  <div class="stat-grid">
    <div class="stat"><span class="stat-value" data-field="status"><?= e($statusText[$job['status']] ?? $job['status']) ?></span><span class="stat-label">Status</span></div>
    <div class="stat"><span class="stat-value" data-field="generation"><?= e($job['generations_run'] ?? 0) ?></span><span class="stat-label"><?= term('generation', 'Generation') ?> (of at most <?= e($job['max_generations']) ?>)</span></div>
    <div class="stat"><span class="stat-value" data-field="elapsed">0s</span><span class="stat-label">Run time</span></div>
    <div class="stat"><span class="stat-value" data-field="hard"><?= e($job['hard_violations'] ?? '-') ?></span><span class="stat-label"><?= term('clash', 'Clashes') ?> left</span></div>
    <div class="stat"><span class="stat-value" data-field="soft"><?= e($job['soft_penalty'] ?? '-') ?></span><span class="stat-label"><?= term('soft rule', 'Soft rule') ?> penalty</span></div>
    <div class="stat"><span class="stat-value" data-field="best_penalty"><?= e($job['best_penalty'] ?? '-') ?></span><span class="stat-label">Best <?= term('penalty') ?></span></div>
  </div>

  <section class="panel ga-live" aria-labelledby="ga-live-title">
    <h2 id="ga-live-title">What the genetic algorithm is doing</h2>
    <p>It keeps a population of <strong><?= e((int) $job['population_size']) ?> timetables</strong>. In every <?= term('generation') ?> it picks the better ones
      as parents (tournaments of <?= e((int) $job['tournament_size']) ?>), mixes them (crossover rate <?= e((float) $job['crossover_rate']) ?>), moves a few classes at random
      (mutation rate <?= e((float) $job['mutation_rate']) ?>)<?= $job['repair_enabled'] ? ', <strong>repairs every clash it can</strong> (up to ' . e((int) $job['max_repair_moves']) . ' moves per timetable)' : ', without the repair step (standard GA)' ?>
      and keeps the <?= e((int) $job['elite_count']) ?> best unchanged.</p>
    <dl class="ga-facts">
      <div><dt>Timetables scored so far</dt><dd data-field="evaluated">0</dd></div>
      <div><dt>Average penalty of the population</dt><dd data-field="avg">-</dd></div>
      <div><dt>Best penalty</dt><dd data-field="best_penalty_copy"><?= e($job['best_penalty'] ?? '-') ?></dd></div>
      <div><dt>Random seed</dt><dd><?= e($job['random_seed']) ?> (same seed and data give the same timetable)</dd></div>
    </dl>
  </section>

  <div class="progress-wrap">
    <div class="progress-bar" role="progressbar" aria-label="Generations completed" aria-valuemin="0"
         aria-valuemax="<?= e($job['max_generations']) ?>" aria-valuenow="<?= e($job['generations_run'] ?? 0) ?>">
      <span></span>
    </div>
    <p class="small muted">Most runs stop well before the maximum: once there are no clashes, the run stops after
      <?= e($job['stall_generations']) ?> generations without improvement.</p>
  </div>

  <p class="alert alert-warning" id="notes"<?= $job['notes'] ? '' : ' hidden' ?>><?= e($job['notes']) ?></p>

  <div class="chart-box">
    <canvas id="progress-chart" aria-label="Best penalty, population average and clashes left by generation" role="img"></canvas>
  </div>
  <p class="small muted">How the genetic algorithm improves the timetables, generation by generation: the blue line is the best timetable's
    penalty and the dashed line the average of the whole population (log scale, left); the red line is the number of clashes left (right).
    You can close this page; the run continues on the server.</p>
</div>

<details>
  <summary>Settings of this run</summary>
  <?= $this->partial('partials/job_settings', ['job' => $job]) ?>
</details>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js" integrity="sha512-CQBWl4fJHWbryGE+Pc7UAxWMUMNMWzWxF4SQo9CgkJIN1kx6djDQZjh3Y8SZ1d+6I+1zze6Z7kHXO7q3UyZAWw==" crossorigin="anonymous" referrerpolicy="no-referrer" defer></script>
<script src="<?= e(asset('js/progress.js')) ?>" defer></script>
