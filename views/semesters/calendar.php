<?php
/**
 * Dates and holidays of one semester (BUILD_SPEC 10.7).
 * @var array $semester
 * @var array $closures
 * @var array $errors
 * @var array $old
 */
use App\Data\SemesterRepository;
?>
<h1>Dates and holidays: <?= e(SemesterRepository::label($semester)) ?></h1>
<p class="page-intro">The genetic algorithm builds one week that repeats all semester. These dates let everyone see that week on the real
  days of each month (the Month view of every timetable, and the public page), and leave out holidays and breaks. They do not change the
  timetable itself.</p>

<form method="post" action="<?= e(url('semesters/dates')) ?>" class="panel" novalidate>
  <?= $csrf->field() ?>
  <input type="hidden" name="id" value="<?= e($semester['id']) ?>">
  <h2>Teaching dates</h2>
  <div class="form-grid">
    <?= $this->partial('partials/field', ['name' => 'start_date', 'label' => 'First day of teaching', 'type' => 'date', 'value' => $old['start_date'],
        'errors' => $errors, 'help' => 'For example the Monday lectures begin.']) ?>
    <?= $this->partial('partials/field', ['name' => 'end_date', 'label' => 'Last day of teaching', 'type' => 'date', 'value' => $old['end_date'],
        'errors' => $errors, 'help' => 'Usually the Friday before examinations.']) ?>
  </div>
  <button type="submit" class="btn btn-primary">Save dates</button>
</form>

<form method="post" action="<?= e(url('semesters/closure-add')) ?>" class="panel" novalidate>
  <?= $csrf->field() ?>
  <input type="hidden" name="id" value="<?= e($semester['id']) ?>">
  <h2>Add a day (or days) without teaching</h2>
  <div class="form-grid">
    <?= $this->partial('partials/field', ['name' => 'closed_on', 'label' => 'Date', 'type' => 'date', 'value' => $old['closed_on'],
        'errors' => $errors, 'required' => true]) ?>
    <?= $this->partial('partials/field', ['name' => 'closed_to', 'label' => 'Until (optional)', 'type' => 'date', 'value' => $old['closed_to'],
        'errors' => $errors, 'help' => 'For a break of several days, for example mid-semester break. Weekends are skipped.']) ?>
    <?= $this->partial('partials/field', ['name' => 'reason', 'label' => 'Reason', 'value' => $old['reason'], 'errors' => $errors, 'required' => true,
        'help' => 'Shown on the calendar, for example Eid al-Fitr, Workers\' Day or Mid-semester break.']) ?>
  </div>
  <button type="submit" class="btn">Add</button>
</form>

<h2>Days without teaching</h2>
<?php if (!$closures): ?>
  <div class="panel empty-state"><p>None yet. Add public holidays and breaks so the month view and the dated export leave them out.</p></div>
<?php else: ?>
  <div class="table-wrap">
    <table class="table table-stack">
      <thead><tr><th scope="col">Date</th><th scope="col">Reason</th><th scope="col"><span class="visually-hidden">Remove</span></th></tr></thead>
      <tbody>
        <?php foreach ($closures as $c): ?>
          <tr>
            <td data-label="Date"><?= e(date('D j M Y', strtotime($c['closed_on']))) ?></td>
            <td data-label="Reason"><?= e($c['reason']) ?></td>
            <td class="actions">
              <form method="post" action="<?= e(url('semesters/closure-remove')) ?>" class="inline-form">
                <?= $csrf->field() ?>
                <input type="hidden" name="id" value="<?= e($semester['id']) ?>">
                <input type="hidden" name="closure_id" value="<?= e($c['id']) ?>">
                <button type="submit" class="btn btn-small btn-danger-outline">Remove</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<p><a href="<?= e(url('semesters')) ?>">Back to semesters</a></p>
