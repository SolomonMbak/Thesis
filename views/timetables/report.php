<?php
/**
 * Quality report.
 * @var array $job
 * @var \App\Engine\Evaluation $evaluation
 * @var array $summary  code => [count, weight, weighted]
 * @var array $items
 * @var array $verdict
 */
$names = [
    'H1' => 'Lecturer in two places at once', 'H2' => 'Room double-booked', 'H3' => 'Level has two classes at once',
    'H4' => 'Room too small', 'H5' => 'Practical not in a lab', 'H6' => 'Blocked period, lecturer unavailable or past the end of the day',
    'S1' => 'Lecturer preferred periods', 'S2' => 'Idle gaps for levels', 'S3' => 'Course sessions on the same day',
    'S4' => 'More than three consecutive hours', 'S5' => 'Last period of the day',
];
?>
<h1>Quality report: timetable #<?= e($job['id']) ?><?= $job['label'] ? ' (' . e($job['label']) . ')' : '' ?></h1>
<p><a href="<?= e(url('timetables/view', ['id' => $job['id']])) ?>">Back to the timetable</a></p>
<?= $this->partial('partials/verdict', ['verdict' => $verdict, 'job' => $job]) ?>
<p class="muted">Scored with the weights used for this run, against the current periods, rooms and availability.
  <?= term('hard rule', 'Hard rules') ?> must all be zero for a usable timetable; <?= term('soft rule', 'soft rules') ?> measure comfort.</p>

<?php if ((int) $evaluation->penalty !== (int) $job['best_penalty']): ?>
  <div class="alert alert-warning">The penalty is now <?= e($evaluation->penalty) ?>, but it was <?= e($job['best_penalty']) ?>
    when the run finished. Periods, rooms or availability have changed since.</div>
<?php endif; ?>

<div class="table-wrap">
  <table class="table">
    <thead><tr><th scope="col">Code</th><th scope="col">Constraint</th><th scope="col" class="num">Count</th>
      <th scope="col" class="num">Weight</th><th scope="col" class="num">Weighted</th></tr></thead>
    <tbody>
      <?php foreach ($summary as $code => $row): ?>
        <tr class="<?= $code[0] === 'H' && $row['count'] > 0 ? 'row-danger' : '' ?>">
          <td><?= e($code) ?></td><td><?= e($names[$code]) ?></td>
          <td class="num"><?= e($row['count']) ?></td><td class="num"><?= e($row['weight']) ?></td><td class="num"><?= e($row['weighted']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr><th scope="row" colspan="2">Hard violations V_H</th><td class="num"><?= e($evaluation->hard) ?></td><td></td><td class="num"><?= e($evaluation->hard * $summary['H1']['weight']) ?></td></tr>
      <tr><th scope="row" colspan="4">Soft penalty</th><td class="num"><?= e($evaluation->soft) ?></td></tr>
      <tr><th scope="row" colspan="4">Total penalty</th><td class="num"><strong><?= e($evaluation->penalty) ?></strong></td></tr>
      <tr><th scope="row" colspan="4">Fitness = 1 / (1 + penalty)</th><td class="num"><?= e(number_format($evaluation->fitness, 8)) ?></td></tr>
    </tfoot>
  </table>
</div>

<h2>Every violation (<?= e(count($items)) ?>)</h2>
<?php if (!$items): ?>
  <div class="panel empty-state">No violations at all.</div>
<?php else: ?>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th scope="col">Code</th><th scope="col" class="num">Count</th><th scope="col">Where</th></tr></thead>
      <tbody>
        <?php foreach ($items as $item): ?>
          <tr class="<?= $item['code'][0] === 'H' ? 'row-danger' : '' ?>">
            <td><?= e($item['code']) ?></td><td class="num"><?= e($item['count']) ?></td><td><?= e($item['text']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
