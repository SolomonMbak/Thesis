<?php
/**
 * Manual timetable import.
 * @var array|null $semester
 * @var array $errors
 * @var array $columns
 */
?>
<h1>Import a manual timetable</h1>
<p>Upload the existing hand-made timetable to score it with exactly the same rules and weights as generated timetables.
  It is saved as a timetable of type "Manual" and can be compared with generated ones.</p>
<ul class="muted">
  <li>One row per class session: <code><?= e(implode(',', $columns)) ?></code>.</li>
  <li>Session numbers follow the <a href="<?= e(url('sessions')) ?>">How courses become classes</a> page (for example a 3-hour lecture has sessions 1, 2 and 3).</li>
  <li>day is Mon to Fri; start_time is the first period, like 08:00; a 2-hour session needs only its first period.</li>
  <li>Every session of the semester must appear exactly once. Any row that does not match is listed, and nothing is saved.</li>
</ul>
<p><a class="btn btn-small" href="<?= e(url('timetables/manual-template')) ?>">Download manual_timetable.csv template</a></p>

<?php if (!$semester): ?>
  <div class="panel empty-state"><p>There is no active semester.</p></div>
<?php else: ?>
  <?php if ($errors): ?>
    <div class="alert alert-error" role="alert">
      <p><strong>Nothing was imported.</strong> Fix these problems and upload the file again:</p>
      <ul><?php foreach (array_slice($errors, 0, 300) as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>
  <form method="post" action="<?= e(url('timetables/manual-upload')) ?>" enctype="multipart/form-data" class="panel">
    <?= $csrf->field() ?>
    <div class="form-grid">
      <?= $this->partial('partials/field', ['name' => 'label', 'label' => 'Label', 'value' => 'Manual timetable', 'errors' => []]) ?>
      <div class="field">
        <label for="file">CSV file</label>
        <input type="file" id="file" name="file" accept=".csv,text/csv" required>
      </div>
    </div>
    <button type="submit" class="btn btn-primary">Import and score</button>
  </form>
<?php endif; ?>
