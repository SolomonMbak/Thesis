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
use App\Data\SemesterRepository;
?>
<h1>Generate timetable</h1>

<?php if (!$semester): ?>
  <div class="panel empty-state"><p>There is no active semester. An administrator must create one under Manage, Semesters.</p></div>
<?php else: ?>
  <p class="page-intro">Pressing Start runs the <strong>genetic algorithm</strong> on <?= e(SemesterRepository::label($semester)) ?>: it breeds
    <?= e($values['population_size']) ?> candidate timetables over many generations and keeps the best one with no clashes. It runs on the server,
    so you can leave this page and come back. It first checks readiness and does not start if a problem is found. Most runs take one to two minutes.</p>
  <details class="panel">
    <summary>How the genetic algorithm works</summary>
    <?= $this->partial('partials/ga_loop', ['population' => $values['population_size'], 'stall' => $values['stall_generations'],
        'maxGenerations' => $values['max_generations'], 'tournament' => $values['tournament_size'], 'elite' => $values['elite_count'],
        'compact' => true]) ?>
  </details>

  <?php if ($active): ?>
    <div class="alert alert-info" role="status">
      Run #<?= e($active['id']) ?> is <?= $active['status'] === 'queued' ? 'waiting to start' : 'running' ?>.
      <a href="<?= e(url('generate/job', ['id' => $active['id']])) ?>">Watch its progress</a>. A school can have one run at a time.
    </div>
  <?php endif; ?>

  <?php if ($errors): ?>
    <div class="alert alert-error" role="alert">The run was not started. Please correct the fields marked below.</div>
  <?php endif; ?>

  <form method="post" action="<?= e(url('generate/start')) ?>" class="panel" novalidate>
    <?= $csrf->field() ?>
    <div class="form-grid">
      <?= $this->partial('partials/field', ['name' => 'label', 'label' => 'Name for this run (optional)', 'value' => $values['label'] ?? '',
          'errors' => $errors, 'help' => 'Helps you find it later, for example "Draft 1" or "With new lab".']) ?>
    </div>
    <?= $this->partial('partials/field', ['name' => 'repair_enabled', 'label' => 'Use targeted repair (recommended)',
        'type' => 'checkbox', 'value' => !empty($values['repair_enabled']), 'errors' => $errors,
        'help' => 'Repair moves each clashing class to the best free time and room, so clashes disappear much faster. Untick only to compare with the standard genetic algorithm.']) ?>
    <details>
      <summary>Genetic algorithm settings (advanced)</summary>
      <p class="small muted">The usual values work for most departments. A <?= term('seed') ?> lets you repeat a run exactly;
        the other values control the <?= term('generation', 'generations') ?> of the search.</p>
      <div class="form-grid">
        <?= $this->partial('partials/field', ['name' => 'random_seed', 'label' => 'Random seed (optional)', 'type' => 'number',
            'value' => $values['random_seed'] ?? '', 'errors' => $errors,
            'help' => 'Leave blank for a new one. The same seed with the same data and settings gives the same timetable.']) ?>
      </div>
      <?= $this->partial('partials/ga_fields', ['values' => $values, 'errors' => $errors]) ?>
    </details>
    <div class="button-row">
      <button type="submit" class="btn btn-primary"<?= $active ? ' disabled' : '' ?>><?= icon('generate') ?> Start the genetic algorithm</button>
      <?php if ($launchMode === 'queue'): ?><span class="small muted">The run starts when the background worker next checks, usually within a minute.</span><?php endif; ?>
    </div>
  </form>

  <?php if ($recent): ?>
    <h2>Recent runs</h2>
    <?= $this->partial('partials/job_table', ['jobs' => $recent]) ?>
  <?php endif; ?>
<?php endif; ?>
