<?php
/**
 * The result in plain words first (BUILD_SPEC 10.3 item 7).
 * @var array $verdict from TimetableService::verdict()
 * @var array $job
 */
?>
<section class="panel verdict <?= $verdict['clash_free'] ? 'verdict-good' : 'verdict-bad' ?>" aria-labelledby="verdict-title">
  <h2 id="verdict-title"><?= e($verdict['headline']) ?></h2>
  <p><?= e($verdict['detail']) ?></p>
  <?php if ($verdict['causes']): ?>
    <ul>
      <?php foreach (array_slice($verdict['causes'], 0, 12) as $cause): ?>
        <li><?= e($cause['text']) ?>
          <?php if ($cause['target']): ?>
            <a href="<?= e(url('timetables/view', ['id' => $job['id'], 'view' => $cause['target']['view'], 'vid' => $cause['target']['vid']])) ?>">Show <?= e($cause['target']['name']) ?></a>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php if (count($verdict['causes']) > 12): ?>
      <p class="small">And <?= e(count($verdict['causes']) - 12) ?> more in the <a href="<?= e(url('timetables/report', ['id' => $job['id']])) ?>">quality report</a>.</p>
    <?php endif; ?>
  <?php endif; ?>
</section>
