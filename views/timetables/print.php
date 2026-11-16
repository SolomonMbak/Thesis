<?php
/**
 * Print view: one grid per page, A4 landscape.
 * @var array $job
 * @var string $view
 * @var array $pages
 * @var array $heading [institution, department]
 */
use App\Services\TimetableService;
?>
<div class="no-print button-row print-tools">
  <button type="button" class="btn btn-primary" data-print>Print</button>
  <a class="btn" href="<?= e(url('timetables/view', ['id' => $job['id'], 'view' => $view])) ?>">Back</a>
  <?php foreach (TimetableService::VIEWS as $v => $l): ?>
    <?php if ($v !== $view): ?><a class="btn" href="<?= e(url('timetables/print', ['id' => $job['id'], 'view' => $v])) ?>">Print by <?= e(strtolower($l)) ?></a><?php endif; ?>
  <?php endforeach; ?>
</div>
<?php foreach ($pages as $i => $page): ?>
  <section class="print-page<?= $i < count($pages) - 1 ? ' page-break' : '' ?>">
    <p class="print-heading"><?= e($heading[0]) ?> · <?= e($heading[1]) ?> · Timetable #<?= e($job['id']) ?></p>
    <?= $this->partial('partials/grid', ['rows' => $page['rows'], 'layout' => $page['layout'], 'view' => $view,
        'caption' => TimetableService::VIEWS[$view] . ': ' . $page['name']]) ?>
  </section>
<?php endforeach; ?>
