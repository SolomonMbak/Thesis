<?php
/**
 * Generate form.
 * @var array|null $semester
 * @var array|null $active
 * @var array $recent
 * @var array $values
 * @var array $errors
 * @var string $launchMode
 */
use App\Engine\GaParams;
use App\Data\SemesterRepository;
?>
<h1>Generate a timetable</h1>

<?php if (!$semester): ?>
  <div class="panel empty-state">There is no active semester. An administrator must create one first.</div>
<?php else: ?>
  <p class="muted">Semester: <?= e(SemesterRepository::label($semester)) ?>. The run happens on the server in the
    background<?= $launchMode === 'queue' ? ' (queue mode: it starts when the worker next checks, usually within a minute)' : '' ?>.
    The feasibility check runs first; the run is not started if it fails.</p>

  <?php if ($active): ?>
    <div class="alert alert-info" role="status">
      Run #<?= e($active['id']) ?> is <?= e($active['status']) ?>.
      <a href="<?= e(url('generate/job', ['id' => $active['id']])) ?>">Watch its progress</a>. Only one run per semester at a time.
    </div>
  <?php endif; ?>

  <?php if ($errors): ?>
    <div class="alert alert-error" role="alert">The run was not started. Please correct the fields marked below.</div>
  <?php endif; ?>

  <form method="post" action="<?= e(url('generate/start')) ?>" class="panel" novalidate>
    <?= $csrf->field() ?>
    <div class="form-grid">
      <?= $this->partial('partials/field', ['name' => 'label', 'label' => 'Label (optional)', 'value' => $values['label'] ?? '',
          'errors' => $errors, 'help' => 'For example "Draft 1" or "RGA trial".']) ?>
      <?= $this->partial('partials/field', ['name' => 'random_seed', 'label' => 'Random seed (optional)', 'type' => 'number',
          'value' => $values['random_seed'] ?? '', 'errors' => $errors,
          'help' => 'Leave blank for a new random seed. The same seed and data give the same timetable.']) ?>
    </div>
    <?= $this->partial('partials/field', ['name' => 'repair_enabled', 'label' => 'Use targeted repair (recommended)',
        'type' => 'checkbox', 'value' => !empty($values['repair_enabled']),
        'errors' => $errors, 'help' => 'Untick to run the standard GA without repair, for comparison.']) ?>
    <details>
      <summary>GA parameters</summary>
      <div class="form-grid">
        <?php foreach (GaParams::FIELDS as $key => [$label, $type]): ?>
          <?= $this->partial('partials/field', ['name' => $key, 'label' => $label, 'type' => 'number',
              'value' => $values[$key] ?? '', 'errors' => $errors, 'step' => $type === 'float' ? '0.001' : '1']) ?>
        <?php endforeach; ?>
      </div>
    </details>
    <div class="button-row">
      <button type="submit" class="btn btn-primary"<?= $active ? ' disabled' : '' ?>>Start the run</button>
    </div>
  </form>

  <?php if ($recent): ?>
    <h2>Recent runs</h2>
    <?= $this->partial('partials/job_table', ['jobs' => $recent]) ?>
  <?php endif; ?>
<?php endif; ?>
