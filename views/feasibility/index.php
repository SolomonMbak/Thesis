<?php
/**
 * Feasibility check result.
 * @var array|null $semester
 * @var array|null $last
 */
$rules = [
    0 => 'Sessions',
    1 => 'Rule 1: enough rooms overall',
    2 => 'Rule 2: enough lab time',
    3 => 'Rule 3: lecturer availability',
    4 => 'Rule 4: group hours',
    5 => 'Rule 5: every session can be placed',
];
?>
<h1>Feasibility check</h1>
<p>This check looks for problems that make a clash-free timetable impossible, such as too few rooms or a lecturer
  with too little free time. <strong>Passing is necessary but does not guarantee</strong> that a clash-free
  timetable exists; the timetable run will show whether one can be found.</p>

<?php if (!$semester): ?>
  <div class="panel empty-state">There is no active semester. An administrator must create one first.</div>
<?php else: ?>
  <form method="post" action="<?= e(url('feasibility/run')) ?>" class="button-row">
    <?= $csrf->field() ?>
    <button type="submit" class="btn btn-primary">Run the feasibility check</button>
  </form>

  <?php if (!$last): ?>
    <div class="panel empty-state">The check has not been run for this semester yet.</div>
  <?php elseif ($last['ok']): ?>
    <div class="panel panel-success">
      <h2>Passed</h2>
      <p>Checked on <?= e($last['checked_at']) ?>. <a href="<?= e(url('generate')) ?>">Go to Generate</a>.</p>
    </div>
  <?php else: ?>
    <div class="panel panel-danger">
      <h2>Failed: <?= e(count($last['failures'])) ?> problem(s)</h2>
      <p class="muted">Checked on <?= e($last['checked_at']) ?>. Fix each problem using the link beside it, then run the check again.</p>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th scope="col">Check</th><th scope="col">Problem</th><th scope="col">Fix</th></tr></thead>
          <tbody>
          <?php foreach ($last['failures'] as $failure): ?>
            <tr>
              <td><?= e($rules[$failure['rule']] ?? 'Rule ' . $failure['rule']) ?></td>
              <td><?= e($failure['message']) ?></td>
              <td>
                <?php if (!empty($failure['link'])): ?>
                  <a href="<?= e(url($failure['link'][0], $failure['link'][1])) ?>"><?= e($failure['link'][2]) ?></a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>
