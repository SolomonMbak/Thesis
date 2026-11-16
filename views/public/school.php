<?php
/**
 * Public school page /s/{slug} and one grid /s/{slug}/{level|lecturer|room}/{id}.
 * No sign-in; only the published timetable of the active semester.
 * @var array $schoolRow
 * @var array|null $semester
 * @var array|null $job
 * @var array $options view => [id => name]
 * @var string $view ('' on the school page)
 * @var int $selectedId
 * @var array $rows
 * @var array $layout
 * @var string|null $logoUrl
 */
use App\Data\SemesterRepository;
use App\Services\TimetableService;

$slug = $schoolRow['slug'];
$headings = ['level' => 'Levels', 'lecturer' => 'Lecturers', 'room' => 'Rooms'];
?>
<header class="public-head">
  <?php if ($logoUrl): ?><img src="<?= e($logoUrl) ?>" alt=""><?php endif; ?>
  <div>
    <h1><?= e($schoolRow['name']) ?></h1>
    <p><?= e(trim(($schoolRow['department_label'] ? $schoolRow['department_label'] . ' · ' : '') . 'Class timetable'
        . ($semester ? ' · ' . SemesterRepository::label($semester) : ''))) ?></p>
  </div>
</header>

<?php if (!$job): ?>
  <div class="panel empty-state"><p>No timetable has been published for this semester yet. Please check back later.</p></div>
<?php elseif ($view === ''): ?>
  <p>Choose your level, lecturer or room to see the week. You can print any timetable or save the page on your phone.</p>
  <?php foreach ($headings as $v => $heading): ?>
    <?php if ($options[$v]): ?>
      <h2><?= e($heading) ?></h2>
      <nav class="link-grid" aria-label="<?= e($heading) ?>">
        <?php foreach ($options[$v] as $id => $name): ?>
          <a href="<?= e(public_url($slug, $v, (int) $id)) ?>"><?= e($name) ?></a>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>
  <?php endforeach; ?>
<?php else: ?>
  <div class="button-row no-print">
    <a class="btn" href="<?= e(public_url($slug)) ?>">All levels, lecturers and rooms</a>
    <button type="button" class="btn" data-print><?= icon('print') ?> Print</button>
  </div>
  <form method="get" action="<?= e(url()) ?>" class="toolbar no-print">
    <input type="hidden" name="r" value="public/school">
    <input type="hidden" name="slug" value="<?= e($slug) ?>">
    <input type="hidden" name="view" value="<?= e($view) ?>">
    <div class="field">
      <label for="pick"><?= e(TimetableService::VIEWS[$view]) ?></label>
      <select id="pick" name="id" data-autosubmit>
        <?php foreach ($options[$view] as $id => $name): ?>
          <option value="<?= e($id) ?>"<?= (int) $id === $selectedId ? ' selected' : '' ?>><?= e($name) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <input type="hidden" name="mode" value="<?= e($mode) ?>">
    <noscript><button type="submit" class="btn">Show</button></noscript>
  </form>
  <?php
  $here = public_url($slug, $view, $selectedId);
  $caption = TimetableService::VIEWS[$view] . ': ' . $options[$view][$selectedId];
  ?>
  <nav class="view-switch no-print" aria-label="Week or month">
    <a class="btn btn-small<?= $mode === 'week' ? ' btn-primary' : '' ?>" href="<?= e($here) ?>"<?= $mode === 'week' ? ' aria-current="page"' : '' ?>>Week</a>
    <a class="btn btn-small<?= $mode === 'month' ? ' btn-primary' : '' ?>" href="<?= e($here . '?mode=month') ?>"<?= $mode === 'month' ? ' aria-current="page"' : '' ?>>Month</a>
  </nav>
  <?php if ($mode === 'month'): ?>
    <?= $this->partial('partials/month', ['month' => $month, 'view' => $view, 'caption' => $caption,
        'monthUrl' => fn (string $m) => $here . '?mode=month&month=' . rawurlencode($m)]) ?>
  <?php else: ?>
    <?= $this->partial('partials/grid', ['rows' => $rows, 'layout' => $layout, 'view' => $view, 'caption' => $caption]) ?>
    <p class="small muted">Each cell shows the course code, the lecturer and the room. Shaded periods are blocked.
      The same week repeats all semester; choose <strong>Month</strong> to see real dates and holidays.</p>
  <?php endif; ?>
<?php endif; ?>
