<?php
/**
 * Availability grid for one lecturer, plus bulk setting.
 * @var array $lecturers
 * @var array|null $selected
 * @var array $statuses  timeslot_id => status
 * @var array $grid
 * @var array $summary   lecturer_id => [preferred, unavailable]
 */
use App\Services\PeriodGenerator;

$labels = ['available' => 'Available', 'preferred' => 'Preferred', 'unavailable' => 'Unavailable'];
?>
<h1>Lecturer availability</h1>

<?php if (!$lecturers): ?>
  <div class="panel empty-state">No active lecturers. <a href="<?= e(url('lecturers/create')) ?>">Add a lecturer</a> first.</div>
<?php elseif (!$grid['rows']): ?>
  <div class="panel empty-state">No periods exist yet. <a href="<?= e(url('periods')) ?>">Generate the periods</a> first.</div>
<?php else: ?>
  <form method="get" action="<?= e(url()) ?>" class="toolbar">
    <input type="hidden" name="r" value="availability">
    <div class="field">
      <label for="lecturer">Lecturer</label>
      <select id="lecturer" name="lecturer" data-autosubmit>
        <?php foreach ($lecturers as $l): ?>
          <?php $s = $summary[$l['id']] ?? ['preferred' => 0, 'unavailable' => 0]; ?>
          <option value="<?= e($l['id']) ?>"<?= $selected && (int) $selected['id'] === (int) $l['id'] ? ' selected' : '' ?>>
            <?= e(trim($l['title'] . ' ' . $l['full_name'])) ?> (<?= e((int) $s['preferred']) ?> preferred, <?= e((int) $s['unavailable']) ?> unavailable)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <noscript><button type="submit" class="btn">Show</button></noscript>
  </form>

  <?php if ($selected): ?>
    <p class="muted">Click a period to change it: Available, then Preferred, then Unavailable. Changes save immediately.
      Preferred periods only matter if a lecturer marks at least one.</p>
    <p class="legend">
      <span class="cell-sample status-available">Available</span>
      <span class="cell-sample status-preferred">Preferred</span>
      <span class="cell-sample status-unavailable">Unavailable</span>
      <span class="cell-sample cell-blocked">Blocked for everyone</span>
    </p>
    <div class="table-wrap">
      <table class="table grid-table" id="availability-grid" data-api="<?= e(\App\Core\Request::basePath() . '/api/availability.php') ?>">
        <thead>
          <tr><th scope="col">Time</th>
            <?php foreach (PeriodGenerator::DAYS as $name): ?><th scope="col"><?= e($name) ?></th><?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($grid['rows'] as $no => $times): ?>
            <tr>
              <th scope="row"><?= e($times['start']) ?></th>
              <?php foreach (array_keys(PeriodGenerator::DAYS) as $day): ?>
                <?php $slot = $grid['cells'][$no][$day] ?? null; ?>
                <?php if (!$slot): ?>
                  <td></td>
                <?php elseif ($slot['is_blocked']): ?>
                  <td class="cell-blocked">Blocked</td>
                <?php else: ?>
                  <?php $status = $statuses[$slot['id']] ?? 'available'; ?>
                  <td class="status-<?= e($status) ?>">
                    <form method="post" action="<?= e(url('availability/toggle')) ?>" class="inline-form availability-cell">
                      <?= $csrf->field() ?>
                      <input type="hidden" name="lecturer" value="<?= e($selected['id']) ?>">
                      <input type="hidden" name="timeslot" value="<?= e($slot['id']) ?>">
                      <button type="submit" class="cell-button"
                              aria-label="<?= e(PeriodGenerator::DAYS[$day] . ' ' . $times['start']) ?>: <?= e($labels[$status]) ?>. Click to change.">
                        <?= e($labels[$status]) ?>
                      </button>
                    </form>
                  </td>
                <?php endif; ?>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <section class="panel" aria-labelledby="bulk-title">
    <h2 id="bulk-title">Set a whole day or period for all lecturers</h2>
    <form method="post" action="<?= e(url('availability/bulk')) ?>" class="toolbar"
          data-confirm="Change this for every active lecturer?">
      <?= $csrf->field() ?>
      <input type="hidden" name="lecturer" value="<?= e($selected['id'] ?? 0) ?>">
      <div class="field">
        <label for="scope">Apply to</label>
        <select id="scope" name="scope">
          <option value="day">A whole day</option>
          <option value="period">One period on every day</option>
        </select>
      </div>
      <div class="field">
        <label for="bulk_day">Day</label>
        <select id="bulk_day" name="day">
          <?php foreach (PeriodGenerator::DAYS as $n => $name): ?><option value="<?= e($n) ?>"><?= e($name) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="bulk_period">Period</label>
        <select id="bulk_period" name="period_no">
          <?php foreach ($grid['rows'] as $no => $times): ?><option value="<?= e($no) ?>"><?= e($times['start']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="bulk_status">Status</label>
        <select id="bulk_status" name="status">
          <?php foreach ($labels as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn">Apply to all lecturers</button>
    </form>
  </section>
<?php endif; ?>
