<?php
/**
 * Period grid with block / unblock buttons.
 * @var array $grid
 * @var string $dayStart
 * @var string $dayEnd
 * @var string $minutes
 */
use App\Services\PeriodGenerator;
?>
<h1>Periods</h1>
<p class="muted">The week runs Monday to Friday from <?= e($dayStart) ?> to <?= e($dayEnd) ?> in periods of
  <?= e($minutes) ?> minutes. Blocked periods (shaded) are never used, for example Friday prayers.
  Click a period to block or unblock it.</p>

<form method="post" action="<?= e(url('periods/generate')) ?>" class="button-row no-print"
      data-confirm="Generate the periods from the current settings? Existing periods keep their blocked status.">
  <?= $csrf->field() ?>
  <button type="submit" class="btn"><?= $grid['rows'] ? 'Generate again from settings' : 'Generate periods' ?></button>
  <?php if ($isAdmin): ?><a class="btn" href="<?= e(url('settings')) ?>">Change day times</a><?php endif; ?>
</form>

<?php if (!$grid['rows']): ?>
  <div class="panel empty-state">No periods yet. Use "Generate periods" to create them from the settings.</div>
<?php else: ?>
  <div class="table-wrap">
    <table class="table grid-table">
      <thead>
        <tr><th scope="col">Time</th>
          <?php foreach (PeriodGenerator::DAYS as $name): ?><th scope="col"><?= e($name) ?></th><?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($grid['rows'] as $no => $times): ?>
          <tr>
            <th scope="row"><?= e($times['start']) ?> to <?= e($times['end']) ?></th>
            <?php foreach (array_keys(PeriodGenerator::DAYS) as $day): ?>
              <?php $slot = $grid['cells'][$no][$day] ?? null; ?>
              <td class="<?= $slot && $slot['is_blocked'] ? 'cell-blocked' : '' ?>">
                <?php if ($slot): ?>
                  <form method="post" action="<?= e(url('periods/toggle')) ?>" class="inline-form">
                    <?= $csrf->field() ?>
                    <input type="hidden" name="id" value="<?= e($slot['id']) ?>">
                    <button type="submit" class="cell-button"
                            aria-label="<?= e(PeriodGenerator::DAYS[$day] . ' ' . $times['start'] . ': ' . ($slot['is_blocked'] ? 'blocked, click to unblock' : 'open, click to block')) ?>">
                      <?= $slot['is_blocked'] ? 'Blocked' : 'Open' ?>
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
