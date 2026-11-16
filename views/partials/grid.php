<?php
/**
 * Timetable grid: rows are periods, columns days. Multi-hour sessions span
 * rows; blocked periods are shaded; clashes are red with a tooltip.
 * Each cell shows course code, lecturer short name and room code (plus the
 * group in lecturer and room views).
 * @var array  $rows    period_no => [start, end]
 * @var array  $layout  from TimetableService::layout()
 * @var string $view    level | lecturer | room
 * @var string $caption
 */
use App\Services\PeriodGenerator;
?>
<div class="table-wrap">
  <table class="timetable">
    <caption><?= e($caption) ?></caption>
    <thead>
      <tr>
        <th scope="col" class="tt-time">Time</th>
        <?php foreach (PeriodGenerator::DAYS as $name): ?><th scope="col"><?= e($name) ?></th><?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $no => $times): ?>
        <tr>
          <th scope="row" class="tt-time"><?= e($times['start']) ?><span class="tt-end"> to <?= e($times['end']) ?></span></th>
          <?php foreach (array_keys(PeriodGenerator::DAYS) as $day): ?>
            <?php $cell = $layout[$no][$day] ?? ['skip' => false, 'rowspan' => 1, 'items' => [], 'clash' => false, 'blocked' => false]; ?>
            <?php if ($cell['skip']) { continue; } ?>
            <?php
            $classes = [];
            if ($cell['blocked']) { $classes[] = 'cell-blocked'; }
            if ($cell['clash']) { $classes[] = 'tt-clash'; }
            if ($cell['items'] && !$cell['clash']) { $classes[] = 'tt-busy'; }
            $tooltip = $cell['clash']
                ? 'Clash: ' . implode('; ', array_map(fn ($i) => $i['entry']['course_code'] . ' (' . $i['entry']['group_code'] . ', '
                    . $i['entry']['lecturer_short'] . ', ' . $i['entry']['room_code'] . ')', $cell['items']))
                : '';
            ?>
            <td class="<?= e(implode(' ', $classes)) ?>"<?= $cell['rowspan'] > 1 ? ' rowspan="' . e($cell['rowspan']) . '"' : '' ?><?= $tooltip ? ' title="' . e($tooltip) . '"' : '' ?>>
              <?php if ($cell['clash']): ?><span class="tt-clash-label">Clash</span><span class="visually-hidden"><?= e($tooltip) ?></span><?php endif; ?>
              <?php foreach ($cell['items'] as $item): ?>
                <?php $en = $item['entry']; ?>
                <div class="tt-item<?= $en['needs_lab'] ? ' tt-lab' : '' ?>"<?= $cell['clash'] ? ' aria-hidden="true"' : '' ?>>
                  <strong><?= e($en['course_code']) ?></strong><?= $item['start'] ? '' : ' <span class="muted">(cont.)</span>' ?>
                  <span><?= e($en['lecturer_short']) ?></span>
                  <span><?= e($en['room_code']) ?><?= $view !== 'level' ? ' &middot; ' . e($en['group_code']) : '' ?></span>
                </div>
              <?php endforeach; ?>
              <?php if (!$cell['items'] && $cell['blocked']): ?><span class="muted small">Blocked</span><?php endif; ?>
            </td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
