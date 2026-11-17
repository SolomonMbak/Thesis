<?php
/**
 * Semesters list and create form.
 * @var array $semesters
 * @var array $errors
 * @var array $old
 */
use App\Data\SemesterRepository;
use App\Services\CalendarService;
?>
<h1>Semesters</h1>
<p class="page-intro">Rooms, levels, lecturers and periods are shared by all semesters. Courses belong to one semester.
  Everyone in the school works on the active semester; at the start of a new one, create it, set it active and copy last semester's courses into it.</p>

<?php if (!$semesters): ?>
  <div class="empty-state panel"><p>No semesters yet. Create the first one below; it becomes active automatically.</p></div>
<?php else: ?>
  <div class="table-wrap">
  <table class="table table-stack">
    <thead><tr><th scope="col">Semester</th><th scope="col">Courses</th><th scope="col">Teaching dates</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($semesters as $s): ?>
      <tr>
        <td data-label="Semester"><?= e(SemesterRepository::label($s)) ?></td>
        <td data-label="Courses"><?= e($s['course_count']) ?></td>
        <td data-label="Teaching dates"><?= $s['start_date'] ? e(CalendarService::spanText($s)) : '<span class="muted">Not set</span>' ?></td>
        <td data-label="Status"><?= $s['is_active'] ? '<span class="tag tag-success">Active</span>' : '' ?></td>
        <td class="actions">
          <a class="btn btn-small" href="<?= e(url('semesters/calendar', ['id' => $s['id']])) ?>">Dates and holidays</a>
          <?php if (!$s['is_active']): ?>
            <form method="post" action="<?= e(url('semesters/activate')) ?>" class="inline-form">
              <?= $csrf->field() ?>
              <input type="hidden" name="id" value="<?= e($s['id']) ?>">
              <button type="submit" class="btn btn-small">Set active</button>
            </form>
          <?php endif; ?>
          <?php if (count($semesters) > 1): ?>
            <form method="post" action="<?= e(url('semesters/copy')) ?>" class="inline-form"
                  data-confirm="Copy the courses from the chosen semester into this one?">
              <?= $csrf->field() ?>
              <input type="hidden" name="id" value="<?= e($s['id']) ?>">
              <label class="visually-hidden" for="from_<?= e($s['id']) ?>">Copy courses from</label>
              <select name="from_id" id="from_<?= e($s['id']) ?>" class="input-inline">
                <?php foreach ($semesters as $other): ?>
                  <?php if ($other['id'] !== $s['id']): ?>
                    <option value="<?= e($other['id']) ?>"><?= e(SemesterRepository::label($other)) ?></option>
                  <?php endif; ?>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-small">Copy courses here</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
<?php endif; ?>

<section class="panel" aria-labelledby="new-sem">
  <h2 id="new-sem">Create a semester</h2>
  <form method="post" action="<?= e(url('semesters/store')) ?>" novalidate>
    <?= $csrf->field() ?>
    <div class="form-grid">
      <?= $this->partial('partials/field', ['name' => 'session_label', 'label' => 'Session', 'value' => $old['session_label'],
          'errors' => $errors, 'required' => true, 'help' => 'For example 2025/2026.']) ?>
      <?= $this->partial('partials/field', ['name' => 'semester_no', 'label' => 'Semester', 'type' => 'select',
          'value' => $old['semester_no'], 'errors' => $errors, 'options' => ['1' => 'First', '2' => 'Second', '3' => 'Third']]) ?>
    </div>
    <details class="semester-dates"<?= ($old['start_date'] !== '' || isset($errors['start_date']) || isset($errors['end_date']) || isset($errors['weeks'])) ? ' open' : '' ?>>
      <summary>Teaching dates (optional): when the semester starts and how long it runs</summary>
      <p class="field-help">Used only by the Month view and the dated export. You can add or change them later under Dates and holidays.</p>
      <?= $this->partial('partials/semester_dates', ['old' => $old, 'errors' => $errors, 'optional' => true]) ?>
    </details>
    <button type="submit" class="btn btn-primary">Create semester</button>
  </form>
</section>
