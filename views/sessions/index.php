<?php
/**
 * Session preview and build.
 * @var array|null $semester
 * @var array $preview
 * @var int $planned
 * @var int $hours
 * @var array|null $state
 */
?>
<h1>Build class sessions</h1>
<p class="muted">Each course is split into class sessions, the units the timetable places. Lectures become 1-hour
  sessions (or one 2-hour block plus 1-hour sessions when allowed). Practicals become blocks of up to 3 hours.</p>

<?php if (!$semester): ?>
  <div class="panel empty-state">There is no active semester. An administrator must create one first.</div>
<?php elseif (!$preview): ?>
  <div class="panel empty-state">This semester has no courses yet. <a href="<?= e(url('courses')) ?>">Add courses</a> first.</div>
<?php else: ?>
  <div class="stat-grid">
    <div class="stat"><span class="stat-value"><?= e(count($preview)) ?></span><span class="stat-label">Courses</span></div>
    <div class="stat"><span class="stat-value"><?= e($planned) ?></span><span class="stat-label">Sessions to build</span></div>
    <div class="stat"><span class="stat-value"><?= e($hours) ?></span><span class="stat-label">Teaching hours per week</span></div>
    <div class="stat"><span class="stat-value"><?= e($state['sessions']) ?></span><span class="stat-label">Sessions built now</span></div>
  </div>

  <section class="panel" aria-labelledby="build-title">
    <h2 id="build-title">Build</h2>
    <?php if ($state['sessions'] > 0): ?>
      <p>Building again replaces the current <?= e($state['sessions']) ?> sessions.
        <?php if ($state['used_by_jobs']): ?>
          Saved timetables use them, so they will be archived (old timetables stay readable) rather than deleted.
        <?php else: ?>
          No saved timetable uses them, so they will be deleted.
        <?php endif; ?>
      </p>
    <?php endif; ?>
    <form method="post" action="<?= e(url('sessions/build')) ?>">
      <?= $csrf->field() ?>
      <div class="field field-checkbox">
        <label for="confirm"><input type="checkbox" id="confirm" name="confirm" value="1" required>
          I have checked the preview below and want to build <?= e($planned) ?> sessions</label>
      </div>
      <button type="submit" class="btn btn-primary">Build sessions</button>
    </form>
  </section>

  <h2>Preview</h2>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th scope="col">Course</th><th scope="col">Group</th><th scope="col">Hours</th><th scope="col">Type</th><th scope="col">Sessions (hours each)</th></tr></thead>
      <tbody>
      <?php foreach ($preview as $course): ?>
        <tr>
          <td><?= e($course['code']) ?> <span class="muted"><?= e($course['title']) ?></span></td>
          <td><?= e($course['group_code']) ?></td>
          <td><?= e($course['hours_per_week']) ?></td>
          <td><?= $course['needs_lab'] ? 'Practical' : 'Lecture' ?></td>
          <td><?= e(implode(' + ', $course['durations'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
