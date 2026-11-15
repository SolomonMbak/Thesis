<?php
/**
 * CSV import page.
 * @var array  $types
 * @var array  $templates
 * @var array  $result
 * @var string $resultType
 */
?>
<h1>Import CSV</h1>
<p class="muted">Import in this order: groups, rooms, lecturers, then courses and availability (they refer to the
  others). Each file must be CSV (UTF-8), at most 2 MB, with the template's header row. Every row is checked first;
  nothing is saved unless all rows are valid. Existing records with the same code or staff number are updated.</p>

<?php if ($result && !$result['ok']): ?>
  <div class="alert alert-error" role="alert">
    <p><strong>Nothing was imported<?= $resultType ? ' (' . e($types[$resultType]) . ')' : '' ?>.</strong>
      Fix these problems in the file and upload it again:</p>
    <ul>
      <?php foreach (array_slice($result['errors'], 0, 200) as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
    </ul>
    <?php if (count($result['errors']) > 200): ?><p>...and <?= e(count($result['errors']) - 200) ?> more.</p><?php endif; ?>
  </div>
<?php endif; ?>

<section class="panel" aria-labelledby="upload-title">
  <h2 id="upload-title">Upload a file</h2>
  <form method="post" action="<?= e(url('import/upload')) ?>" enctype="multipart/form-data">
    <?= $csrf->field() ?>
    <div class="form-grid">
      <?= $this->partial('partials/field', ['name' => 'type', 'label' => 'The file contains', 'type' => 'select',
          'value' => $resultType, 'options' => $types, 'errors' => []]) ?>
      <div class="field">
        <label for="file">CSV file</label>
        <input type="file" id="file" name="file" accept=".csv,text/csv" required>
      </div>
    </div>
    <button type="submit" class="btn btn-primary">Check and import</button>
  </form>
</section>

<section class="panel" aria-labelledby="templates-title">
  <h2 id="templates-title">Templates</h2>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th scope="col">Data</th><th scope="col">Columns</th><th scope="col"><span class="visually-hidden">Download</span></th></tr></thead>
      <tbody>
        <?php foreach ($templates as $type => $template): ?>
          <tr>
            <td><?= e($types[$type]) ?></td>
            <td><code><?= e(implode(',', $template['columns'])) ?></code></td>
            <td><a class="btn btn-small" href="<?= e(url('import/template', ['type' => $type])) ?>">Download <?= e($type) ?>.csv</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <ul class="small muted">
    <li>needs_lab and allow_two_hour_block: 1 for yes, 0 for no.</li>
    <li>class_size_override: leave blank to use the group size.</li>
    <li>availability day: Mon, Tue, Wed, Thu or Fri; start_time like 08:00; status: available, preferred or unavailable.</li>
    <li>room_type: lecture or lab.</li>
  </ul>
</section>
