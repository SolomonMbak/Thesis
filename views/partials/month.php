<?php
/**
 * Month view (BUILD_SPEC 10.7): the repeating weekly timetable laid onto the
 * real dates of one month. On phones the weeks become a list of days.
 * @var array  $month    CalendarService::month()
 * @var string $view     level | lecturer | room
 * @var string $caption
 * @var callable $monthUrl fn(string 'YYYY-MM'): string
 */
use App\Services\PeriodGenerator;
?>
<div class="month-head">
  <a class="btn btn-small no-print" href="<?= e($monthUrl($month['prev'])) ?>" aria-label="Previous month">&lsaquo; Previous</a>
  <h2 class="month-title"><?= e($caption) ?>: <?= e($month['label']) ?></h2>
  <a class="btn btn-small no-print" href="<?= e($monthUrl($month['next'])) ?>" aria-label="Next month">Next &rsaquo;</a>
</div>
<?php if ($month['has_dates']): ?>
  <p class="small muted"><?= e($month['teaching_days']) ?> teaching day(s) this month<?= $month['closed_days'] ? ', ' . e($month['closed_days']) . ' without teaching' : '' ?>.
    Weekends are left out.</p>
<?php else: ?>
  <p class="alert alert-info">The semester has no start and end date yet, so every weekday shows the weekly timetable.
    An administrator can set the dates and holidays under Manage, Semesters, Dates and holidays.</p>
<?php endif; ?>

<div class="table-wrap">
  <table class="month-grid">
    <caption class="visually-hidden"><?= e($caption) ?>, <?= e($month['label']) ?></caption>
    <thead><tr><?php foreach (PeriodGenerator::DAYS as $name): ?><th scope="col"><?= e($name) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
      <?php foreach ($month['weeks'] as $week): ?>
        <tr>
          <?php foreach ($week as $cell): ?>
            <?php
            $classes = ['month-day', 'month-' . $cell['status']];
            if (!$cell['in_month']) { $classes[] = 'month-other'; }
            ?>
            <td class="<?= e(implode(' ', $classes)) ?>">
              <span class="month-date"><span class="month-dayname"><?= e(PeriodGenerator::SHORT_DAYS[$cell['day']]) ?> </span><?= e($cell['number']) ?></span>
              <?php if (!$cell['in_month']): ?>
              <?php elseif ($cell['status'] === 'closed'): ?>
                <span class="month-note">No classes: <?= e($cell['reason']) ?></span>
              <?php elseif ($cell['status'] === 'outside'): ?>
                <span class="month-note">Outside the semester</span>
              <?php elseif (!$cell['items']): ?>
                <span class="month-note">No classes</span>
              <?php else: ?>
                <ul class="month-items">
                  <?php foreach ($cell['items'] as $item): ?>
                    <li><span class="month-time"><?= e($item['start_time']) ?>-<?= e($item['end_time']) ?></span>
                      <strong><?= e($item['course_code']) ?></strong>
                      <?= e($view === 'room' ? $item['group_code'] : $item['room_code']) ?><?= $view === 'lecturer' ? ' · ' . e($item['group_code']) : '' ?></li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
