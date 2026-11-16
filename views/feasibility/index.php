<?php
/**
 * Check readiness (feasibility pre-check) result.
 * @var array|null $semester
 * @var array|null $last
 * @var int $sessions class sessions of the semester
 */
$rules = [
    0 => 'Courses',
    1 => 'Enough rooms overall',
    2 => 'Enough lab time',
    3 => 'Lecturer availability',
    4 => 'Hours of each level',
    5 => 'Every class can be placed',
];
?>
<div class="page-head">
  <h1>Check readiness</h1>
  <?php if ($semester): ?>
    <form method="post" action="<?= e(url('feasibility/run')) ?>">
      <?= $csrf->field() ?>
      <button type="submit" class="btn btn-primary"><?= icon('check') ?> <?= $last ? 'Check again' : 'Check readiness now' ?></button>
    </form>
  <?php endif; ?>
</div>
<p class="page-intro">This finds problems in your data that make a timetable with no clashes impossible, such as too few rooms or a lecturer
  with too little free time, so you can fix them before waiting for a run. It takes a second.
  Passing means a clash-free timetable is <em>possible</em>, not guaranteed: the run shows whether one is found.</p>

<?php if (!$semester): ?>
  <div class="panel empty-state"><p>There is no active semester. An administrator must create one under Manage, Semesters.</p></div>
<?php else: ?>
  <p class="small muted"><?= e($sessions) ?> <?= term('class session', 'class sessions') ?> to place this semester, worked out from your courses.
    <a href="<?= e(url('sessions')) ?>">See how courses become classes</a>.</p>

  <?php if (!$last): ?>
    <div class="panel empty-state"><p>Not checked yet for this semester. Press <strong>Check readiness now</strong>.</p></div>
  <?php elseif ($last['ok'] && $last['current']): ?>
    <section class="panel verdict verdict-good">
      <h2>Ready to generate</h2>
      <p>Nothing in your data makes a clash-free timetable impossible. Checked <?= e(substr((string) $last['checked_at'], 0, 16)) ?>.</p>
      <div class="button-row"><a class="btn btn-primary" href="<?= e(url('generate')) ?>"><?= icon('generate') ?> Generate your timetable</a></div>
    </section>
  <?php elseif ($last['ok']): ?>
    <section class="panel panel-warning">
      <h2>Your data changed since the last check</h2>
      <p>The check passed on <?= e(substr((string) $last['checked_at'], 0, 16)) ?>, but rooms, periods, lecturers, availability, levels or courses
        have changed since. Check again to be sure.</p>
    </section>
  <?php else: ?>
    <section class="panel verdict verdict-bad">
      <h2>Not ready: <?= e(count($last['failures'])) ?> problem(s) to fix</h2>
      <p class="muted">Checked <?= e(substr((string) $last['checked_at'], 0, 16)) ?><?= $last['current'] ? '' : ' (your data has changed since; check again after fixing)' ?>.
        Fix each problem with the link beside it, then check again.</p>
      <div class="table-wrap">
        <table class="table table-stack">
          <thead><tr><th scope="col">Area</th><th scope="col">Problem and how to fix it</th><th scope="col"><span class="visually-hidden">Fix</span></th></tr></thead>
          <tbody>
          <?php foreach ($last['failures'] as $failure): ?>
            <tr>
              <td data-label="Area"><?= e($rules[$failure['rule']] ?? 'Rule ' . $failure['rule']) ?></td>
              <td data-label="Problem"><?= e($failure['message']) ?></td>
              <td class="actions">
                <?php if (!empty($failure['link'])): ?>
                  <a class="btn btn-small" href="<?= e(url($failure['link'][0], $failure['link'][1])) ?>"><?= e($failure['link'][2]) ?></a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endif; ?>
<?php endif; ?>
