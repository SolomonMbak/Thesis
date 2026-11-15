<?php
/**
 * Live progress page. assets/js/progress.js polls api/progress.php.
 * @var array $job
 * @var string $launchMode
 */
use App\Core\Request;

$active = in_array($job['status'], ['queued', 'running'], true);
?>
<div class="page-head">
  <h1>Run #<?= e($job['id']) ?><?= $job['label'] ? ': ' . e($job['label']) : '' ?></h1>
  <div class="button-row">
    <?php if ($active): ?>
      <form method="post" action="<?= e(url('generate/stop')) ?>" class="inline-form"
            data-confirm="Stop this run? The best timetable found so far will be saved." id="stop-form">
        <?= $csrf->field() ?>
        <input type="hidden" name="id" value="<?= e($job['id']) ?>">
        <button type="submit" class="btn btn-danger">Stop</button>
      </form>
    <?php endif; ?>
    <a class="btn<?= $active ? ' hidden' : '' ?>" id="results-link" href="<?= e(url('timetables/view', ['id' => $job['id']])) ?>">Open the timetable</a>
  </div>
</div>

<div id="progress" data-api="<?= e(Request::basePath() . '/api/progress.php') ?>" data-job="<?= e($job['id']) ?>"
     data-max="<?= e($job['max_generations']) ?>">
  <div class="stat-grid">
    <div class="stat"><span class="stat-value" data-field="status"><?= e(ucfirst($job['status'])) ?></span><span class="stat-label">Status</span></div>
    <div class="stat"><span class="stat-value" data-field="generation"><?= e($job['generations_run'] ?? 0) ?></span><span class="stat-label">Generation of <?= e($job['max_generations']) ?></span></div>
    <div class="stat"><span class="stat-value" data-field="elapsed">0s</span><span class="stat-label">Elapsed time</span></div>
    <div class="stat"><span class="stat-value" data-field="best_penalty"><?= e($job['best_penalty'] ?? '-') ?></span><span class="stat-label">Best penalty</span></div>
    <div class="stat"><span class="stat-value" data-field="hard"><?= e($job['hard_violations'] ?? '-') ?></span><span class="stat-label">Hard violations (V_H)</span></div>
    <div class="stat"><span class="stat-value" data-field="soft"><?= e($job['soft_penalty'] ?? '-') ?></span><span class="stat-label">Soft penalty</span></div>
  </div>

  <div class="progress-wrap">
    <div class="progress-bar" role="progressbar" aria-label="Generations completed" aria-valuemin="0"
         aria-valuemax="<?= e($job['max_generations']) ?>" aria-valuenow="<?= e($job['generations_run'] ?? 0) ?>">
      <span></span>
    </div>
    <p class="small muted">The run may stop before the maximum: once clash-free, it stops after
      <?= e($job['stall_generations']) ?> generations without improvement.</p>
  </div>

  <p class="alert alert-info" id="queued-hint"<?= $job['status'] === 'queued' ? '' : ' hidden' ?>>
    Waiting to start.<?= $launchMode === 'queue'
      ? ' In queue mode the worker (cron job) starts it, usually within a minute. If nothing happens, ask the administrator to check the health page.'
      : ' If it does not start within a minute, check the health page.' ?>
  </p>
  <p class="alert alert-warning" id="notes"<?= $job['notes'] ? '' : ' hidden' ?>><?= e($job['notes']) ?></p>

  <div class="chart-box">
    <canvas id="progress-chart" aria-label="Best penalty and hard violations by generation" role="img"></canvas>
  </div>
  <p class="small muted">Best penalty on a logarithmic scale (left) and hard violations V_H (right) per generation.
    You can close this page; the run continues on the server.</p>
</div>

<h2>Settings</h2>
<?= $this->partial('partials/job_settings', ['job' => $job]) ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js" defer></script>
<script src="<?= e(asset('js/progress.js')) ?>" defer></script>
