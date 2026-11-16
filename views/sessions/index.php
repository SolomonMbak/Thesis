<?php
/**
 * How courses become class sessions (read-only; updated automatically).
 * @var array|null $semester
 * @var array $preview
 * @var int $planned
 * @var int $hours
 * @var array|null $state
 */
?>
<h1>How courses become classes</h1>
<p class="page-intro">The timetable places <?= term('class session', 'class sessions') ?>, not whole courses. Each course is split by its weekly hours:
  lectures become 1-hour classes (or one 2-hour block plus 1-hour classes when allowed), practicals become lab blocks of up to 3 hours.
  This happens automatically whenever you open Check readiness or Generate, so you never need to do it yourself.</p>

<?php if (!$semester): ?>
  <div class="panel empty-state"><p>There is no active semester. An administrator must create one under Manage, Semesters.</p></div>
<?php elseif (!$preview): ?>
  <div class="panel empty-state"><p>This semester has no courses yet, so there is nothing to split.</p>
    <div class="button-row"><a class="btn btn-primary" href="<?= e(url('courses/create')) ?>">Add a course</a></div></div>
<?php else: ?>
  <div class="stat-grid">
    <div class="stat"><span class="stat-value"><?= e(count($preview)) ?></span><span class="stat-label">Courses</span></div>
    <div class="stat"><span class="stat-value"><?= e($planned) ?></span><span class="stat-label">Classes to place each week</span></div>
    <div class="stat"><span class="stat-value"><?= e($hours) ?></span><span class="stat-label">Teaching hours per week</span></div>
  </div>

  <?php if ($state['up_to_date']): ?>
    <p><span class="tag tag-success">Up to date</span> The <?= e($state['sessions']) ?> class sessions match your courses.</p>
  <?php else: ?>
    <form method="post" action="<?= e(url('sessions/build')) ?>" class="panel button-row">
      <?= $csrf->field() ?>
      <span><span class="tag tag-warning">Out of date</span> Your courses changed. The classes are updated next time you open Check readiness or Generate, or now:</span>
      <button type="submit" class="btn">Update now</button>
    </form>
  <?php endif; ?>
  <?php if ($state['used_by_jobs']): ?>
    <p class="small muted">Saved timetables keep their own copy of the classes, so updating never changes a saved or published timetable.</p>
  <?php endif; ?>

  <div class="table-wrap">
    <table class="table table-stack">
      <thead><tr><th scope="col">Course</th><th scope="col">Level</th><th scope="col">Hours</th><th scope="col">Type</th><th scope="col">Classes (hours each)</th></tr></thead>
      <tbody>
      <?php foreach ($preview as $course): ?>
        <tr>
          <td data-label="Course"><?= e($course['code']) ?> <span class="muted"><?= e($course['title']) ?></span></td>
          <td data-label="Level"><?= e($course['group_code']) ?></td>
          <td data-label="Hours"><?= e($course['hours_per_week']) ?></td>
          <td data-label="Type"><?= $course['needs_lab'] ? 'Practical' : 'Lecture' ?></td>
          <td data-label="Classes"><?= e(implode(' + ', $course['durations'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
