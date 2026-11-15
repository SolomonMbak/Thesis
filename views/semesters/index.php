<?php
/**
 * Semesters list and create form.
 * @var array $semesters
 * @var array $errors
 * @var array $old
 */
use App\Data\SemesterRepository;
?>
<h1>Semesters</h1>
<p class="muted">Lecturers, groups, rooms and periods are shared by all semesters. Courses belong to one semester.
  Only the active semester is used for building sessions and generating timetables.</p>

<?php if (!$semesters): ?>
  <div class="empty-state panel">No semesters yet. Create the first one below; it becomes active automatically.</div>
<?php else: ?>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th scope="col">Semester</th><th scope="col">Courses</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($semesters as $s): ?>
      <tr>
        <td><?= e(SemesterRepository::label($s)) ?></td>
        <td><?= e($s['course_count']) ?></td>
        <td><?= $s['is_active'] ? '<span class="tag tag-success">Active</span>' : '' ?></td>
        <td class="actions">
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
  <form method="post" action="<?= e(url('semesters/store')) ?>">
    <?= $csrf->field() ?>
    <div class="form-grid">
      <?= $this->partial('partials/field', ['name' => 'session_label', 'label' => 'Session', 'value' => $old['session_label'],
          'errors' => $errors, 'required' => true, 'help' => 'For example 2025/2026.']) ?>
      <?= $this->partial('partials/field', ['name' => 'semester_no', 'label' => 'Semester', 'type' => 'select',
          'value' => $old['semester_no'], 'errors' => $errors, 'options' => ['1' => 'First', '2' => 'Second', '3' => 'Third']]) ?>
    </div>
    <button type="submit" class="btn btn-primary">Create semester</button>
  </form>
</section>
