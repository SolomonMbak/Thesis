<?php
/**
 * Runs and results: every run and imported timetable, with filters and compare.
 * @var array|null $semester
 * @var array $jobs
 * @var array $filters
 * @var bool $canPublish officers and above (generate, import)
 */
$filtered = $filters['job_type'] !== '' || $filters['status'] !== '' || $filters['label'] !== '';
?>
<div class="page-head">
  <h1>Runs and results</h1>
  <?php if ($canPublish): ?>
    <div class="button-row">
      <a class="btn" href="<?= e(url('timetables/manual')) ?>"><?= icon('import') ?> Import a manual timetable</a>
      <a class="btn btn-primary" href="<?= e(url('generate')) ?>"><?= icon('generate') ?> Generate timetable</a>
    </div>
  <?php endif; ?>
</div>
<p class="page-intro">Every timetable run of this semester, newest first. Open one to see its grids and quality report,
  or tick two or more finished runs and compare them side by side.</p>

<?php if (!$semester): ?>
  <div class="panel empty-state"><p>There is no active semester. An administrator must create one under Manage, Semesters.</p></div>
<?php else: ?>
  <?php if ($jobs || $filtered): ?>
    <form method="get" action="<?= e(url()) ?>" class="toolbar">
      <input type="hidden" name="r" value="timetables">
      <div class="field">
        <label for="type">Type</label>
        <select id="type" name="type">
          <?php foreach (['' => 'All', 'ga' => 'Generated', 'manual' => 'Imported manual timetables'] as $v => $l): ?>
            <option value="<?= e($v) ?>"<?= $filters['job_type'] === $v ? ' selected' : '' ?>><?= e($l) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status">
          <?php foreach (['' => 'All', 'finished' => 'Finished', 'stopped' => 'Stopped', 'running' => 'Running', 'queued' => 'Waiting to start', 'failed' => 'Failed'] as $v => $l): ?>
            <option value="<?= e($v) ?>"<?= $filters['status'] === $v ? ' selected' : '' ?>><?= e($l) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="label">Name contains</label>
        <input type="search" id="label" name="label" value="<?= e($filters['label']) ?>">
      </div>
      <button type="submit" class="btn">Filter</button>
      <?php if ($filtered): ?><a class="btn" href="<?= e(url('timetables')) ?>">Clear</a><?php endif; ?>
    </form>
  <?php endif; ?>

  <?php if (!$jobs && $filtered): ?>
    <div class="panel empty-state"><p>No runs match. Try another filter or clear it.</p></div>
  <?php elseif (!$jobs): ?>
    <div class="panel empty-state">
      <p>No timetable has been made for this semester yet. Generate one, or import the timetable you made by hand to score it with the same rules.</p>
      <?php if ($canPublish): ?>
        <div class="button-row">
          <a class="btn btn-primary" href="<?= e(url('generate')) ?>">Generate timetable</a>
          <a class="btn" href="<?= e(url('timetables/manual')) ?>">Import a manual timetable</a>
        </div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <form method="get" action="<?= e(url()) ?>" id="compare-form" class="button-row">
      <input type="hidden" name="r" value="timetables/compare">
      <button type="submit" class="btn">Compare ticked runs</button>
    </form>
    <?= $this->partial('partials/job_table', ['jobs' => $jobs, 'selectable' => true]) ?>
  <?php endif; ?>
<?php endif; ?>
