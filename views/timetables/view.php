<?php
/**
 * One saved timetable: verdict first, then scores, the view selector and the grid.
 * @var array $job
 * @var array $breakdown
 * @var array $options
 * @var string $view
 * @var int $selectedId
 * @var array $layout
 * @var array $rows
 * @var int $entryCount
 * @var array|null $verdict
 * @var bool $canPublish
 * @var string $publicLink
 */
use App\Services\TimetableService;

$ready = in_array($job['status'], ['finished', 'stopped'], true);
?>
<div class="page-head no-print">
  <h1>Timetable #<?= e($job['id']) ?><?= $job['label'] ? ': ' . e($job['label']) : '' ?>
    <?php if ($job['is_published']): ?><span class="tag tag-success">Published</span><?php endif; ?></h1>
  <?php if ($ready): ?>
    <div class="button-row">
      <a class="btn" href="<?= e(url('timetables/report', ['id' => $job['id']])) ?>">Quality report</a>
      <a class="btn" href="<?= e(url('timetables/export', ['id' => $job['id']])) ?>">Export CSV</a>
      <a class="btn" href="<?= e(url('timetables/print', ['id' => $job['id'], 'view' => $view])) ?>"><?= icon('print') ?> Print view</a>
      <?php if ($canPublish && $job['is_published']): ?>
        <form method="post" action="<?= e(url('timetables/unpublish')) ?>" class="inline-form" data-confirm="Remove this timetable from the public page?">
          <?= $csrf->field() ?><input type="hidden" name="id" value="<?= e($job['id']) ?>">
          <button type="submit" class="btn">Unpublish</button>
        </form>
      <?php elseif ($canPublish): ?>
        <form method="post" action="<?= e(url('timetables/publish')) ?>" class="inline-form"
              data-confirm="<?= (int) $job['hard_violations'] > 0 ? 'This timetable still has clashes. Publish it anyway? ' : 'Publish this timetable? ' ?>It replaces any published timetable for this semester and everyone with the link can see it.">
          <?= $csrf->field() ?><input type="hidden" name="id" value="<?= e($job['id']) ?>">
          <button type="submit" class="btn btn-primary"><?= icon('publish') ?> Publish</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php if (!$ready): ?>
  <div class="panel empty-state">
    <p>This run has no saved timetable (<?= e($job['status'] === 'failed' ? 'it failed' : 'it has not finished') ?>).</p>
    <?php if ($job['notes']): ?><p><?= e($job['notes']) ?></p><?php endif; ?>
    <div class="button-row"><a class="btn" href="<?= e(url('generate/job', ['id' => $job['id']])) ?>">View the run</a></div>
  </div>
<?php else: ?>
  <?php if ($verdict): ?><?= $this->partial('partials/verdict', ['verdict' => $verdict, 'job' => $job]) ?><?php endif; ?>
  <?php if ($job['job_type'] === 'ga'): ?>
    <?php $scored = (int) $job['population_size'] * ((int) $job['generations_run'] + 1); ?>
    <section class="panel ga-live no-print" aria-labelledby="ga-found-title">
      <h2 id="ga-found-title">How the genetic algorithm found this timetable</h2>
      <p>It bred a population of <strong><?= e((int) $job['population_size']) ?> timetables</strong> for
        <strong><?= e((int) $job['generations_run']) ?> <?= term('generation', 'generations') ?></strong>, scoring
        <strong><?= e(number_format($scored)) ?> timetables</strong> in <?= e(number_format((int) $job['runtime_ms'] / 1000, 1)) ?> seconds, and kept the best one.
        <?php if ($job['first_feasible_generation'] !== null): ?>
          The first timetable without clashes appeared in generation <strong><?= e($job['first_feasible_generation']) ?></strong>; the rest of the run
          improved the soft rules.
        <?php else: ?>
          It did not find a timetable without clashes in this run.
        <?php endif; ?>
        <?= $job['repair_enabled'] ? 'The targeted repair step was on.' : 'This was the standard genetic algorithm, without the repair step.' ?></p>
      <p class="small"><a href="<?= e(url('generate/job', ['id' => $job['id']])) ?>">See its progress chart, generation by generation</a>
        · Seed <?= e($job['random_seed']) ?>: the same seed, data and settings give this same timetable again.</p>
    </section>
  <?php elseif ($job['job_type'] === 'manual'): ?>
    <p class="alert alert-info no-print">This timetable was made by hand and imported. It is scored with exactly the same rules as the timetables the
      genetic algorithm makes, so you can compare them.</p>
  <?php endif; ?>
  <?php if ($job['notes']): ?><div class="alert alert-warning"><?= e($job['notes']) ?></div><?php endif; ?>

  <?php if ($job['is_published']): ?>
    <div class="panel no-print">
      <p>Published. Students and staff open it at:</p>
      <div class="copy-row">
        <label for="share-link" class="visually-hidden">Public link</label>
        <input type="text" id="share-link" value="<?= e($publicLink) ?>" readonly>
        <button type="button" class="btn" data-copy="share-link"><?= icon('copy') ?> Copy link</button>
      </div>
    </div>
  <?php endif; ?>

  <div class="stat-grid no-print">
    <div class="stat"><span class="stat-value <?= (int) $job['hard_violations'] === 0 ? 'text-success' : 'text-danger' ?>"><?= e($job['hard_violations']) ?></span>
      <span class="stat-label"><?= term('clash', 'Clashes') ?> (hard rule breaks)</span></div>
    <div class="stat"><span class="stat-value"><?= e($job['soft_penalty']) ?></span><span class="stat-label"><?= term('soft rule', 'Soft rule') ?> penalty</span></div>
    <div class="stat"><span class="stat-value"><?= e($job['best_penalty']) ?></span><span class="stat-label">Total <?= term('penalty') ?></span></div>
    <div class="stat"><span class="stat-value"><?= e(number_format((float) $job['best_fitness'], 6)) ?></span><span class="stat-label"><?= term('fitness', 'Fitness') ?></span></div>
    <?php if ($job['job_type'] === 'ga'): ?>
      <div class="stat"><span class="stat-value"><?= e($job['generations_run']) ?></span><span class="stat-label"><?= term('generation', 'Generations') ?></span></div>
      <div class="stat"><span class="stat-value"><?= e(number_format((int) $job['runtime_ms'] / 1000, 1)) ?>s</span><span class="stat-label">Run time</span></div>
    <?php endif; ?>
  </div>

  <?php if ($breakdown): ?>
    <p class="small muted no-print">Rule by rule: <?= e(implode(' · ', array_map(fn ($k, $v) => "{$k} {$v}", array_keys($breakdown), $breakdown))) ?>
      (<a href="<?= e(url('rules')) ?>">what the codes mean</a>)</p>
  <?php endif; ?>

  <form method="get" action="<?= e(url()) ?>" class="toolbar no-print">
    <input type="hidden" name="r" value="timetables/view">
    <input type="hidden" name="id" value="<?= e($job['id']) ?>">
    <div class="field">
      <label for="view">Show by</label>
      <select id="view" name="view" data-autosubmit>
        <?php foreach (TimetableService::VIEWS as $v => $l): ?>
          <option value="<?= e($v) ?>"<?= $view === $v ? ' selected' : '' ?>><?= e($l) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="vid"><?= e(TimetableService::VIEWS[$view]) ?></label>
      <select id="vid" name="vid" data-autosubmit>
        <?php foreach ($options[$view] as $id => $name): ?>
          <option value="<?= e($id) ?>"<?= (int) $id === $selectedId ? ' selected' : '' ?>><?= e($name) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <input type="hidden" name="mode" value="<?= e($mode) ?>">
    <noscript><button type="submit" class="btn">Show</button></noscript>
  </form>

  <?php if ($selectedId): ?>
    <?php
    $base = ['id' => $job['id'], 'view' => $view, 'vid' => $selectedId];
    $caption = TimetableService::VIEWS[$view] . ': ' . $options[$view][$selectedId];
    ?>
    <nav class="view-switch no-print" aria-label="Week or month">
      <a class="btn btn-small<?= $mode === 'week' ? ' btn-primary' : '' ?>" href="<?= e(url('timetables/view', $base)) ?>"<?= $mode === 'week' ? ' aria-current="page"' : '' ?>>Week</a>
      <a class="btn btn-small<?= $mode === 'month' ? ' btn-primary' : '' ?>" href="<?= e(url('timetables/view', $base + ['mode' => 'month'])) ?>"<?= $mode === 'month' ? ' aria-current="page"' : '' ?>>Month</a>
      <a class="btn btn-small" href="<?= e(url('timetables/export-dates', ['id' => $job['id']])) ?>">Export with dates (CSV)</a>
    </nav>
    <?php if ($mode === 'month' && $month): ?>
      <?= $this->partial('partials/month', ['month' => $month, 'view' => $view, 'caption' => $caption,
          'monthUrl' => fn (string $m) => url('timetables/view', $base + ['mode' => 'month', 'month' => $m])]) ?>
    <?php else: ?>
      <p class="small muted">The genetic algorithm builds one week that repeats all semester. Choose <strong>Month</strong> to see it on real dates, with holidays left out.</p>
      <?= $this->partial('partials/grid', ['rows' => $rows, 'layout' => $layout, 'view' => $view, 'caption' => $caption]) ?>
    <?php endif; ?>
  <?php else: ?>
    <div class="panel empty-state"><p>This timetable has no classes.</p></div>
  <?php endif; ?>

  <details class="no-print">
    <summary>Settings of this run</summary>
    <?= $this->partial('partials/job_settings', ['job' => $job]) ?>
  </details>
<?php endif; ?>
