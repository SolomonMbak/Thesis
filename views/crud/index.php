<?php
/**
 * Generic master data list.
 * @var array  $def
 * @var array  $rows
 * @var string $q
 * @var array  $activeFilters
 * @var array  $options
 * @var string|null $notice
 */
$bools = $def['bool_columns'] ?? [];
?>
<div class="page-head">
  <h1><?= e($def['title']) ?></h1>
  <div class="button-row">
    <a class="btn btn-primary" href="<?= e(url($def['route'] . '/create')) ?>">Add <?= e($def['singular']) ?></a>
    <a class="btn" href="<?= e(url('import')) ?>">Import CSV</a>
  </div>
</div>

<?php if ($notice): ?>
  <div class="alert alert-warning" role="status"><?= e($notice) ?></div>
<?php endif; ?>

<form method="get" action="<?= e(url()) ?>" class="toolbar" role="search">
  <input type="hidden" name="r" value="<?= e($def['route']) ?>">
  <div class="field">
    <label for="q">Search</label>
    <input type="text" id="q" name="q" value="<?= e($q) ?>">
  </div>
  <?php foreach ($def['filters'] ?? [] as $field => $label): ?>
    <div class="field">
      <label for="filter_<?= e($field) ?>"><?= e($label) ?></label>
      <select id="filter_<?= e($field) ?>" name="<?= e($field) ?>">
        <option value="">All</option>
        <?php foreach ($options[$field] ?? [] as $value => $text): ?>
          <option value="<?= e($value) ?>"<?= (string) ($activeFilters[$field] ?? '') === (string) $value ? ' selected' : '' ?>><?= e($text) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endforeach; ?>
  <button type="submit" class="btn">Filter</button>
  <?php if ($q !== '' || $activeFilters): ?>
    <a class="btn" href="<?= e(url($def['route'])) ?>">Clear</a>
  <?php endif; ?>
</form>

<?php if (!$rows): ?>
  <div class="panel empty-state">
    <?= e($q !== '' || $activeFilters ? 'Nothing matches your search. Try different words or clear the filter.' : $def['empty']) ?>
  </div>
<?php else: ?>
  <p class="muted small"><?= e(count($rows)) ?> <?= e(count($rows) === 1 ? $def['singular'] : strtolower($def['title'])) ?></p>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <?php foreach ($def['columns'] as $heading): ?>
            <th scope="col"><?= e($heading) ?></th>
          <?php endforeach; ?>
          <th scope="col"><span class="visually-hidden">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <?php foreach (array_keys($def['columns']) as $column): ?>
              <td>
                <?php if (in_array($column, $bools, true)): ?>
                  <?= $row[$column] ? 'Yes' : '<span class="muted">No</span>' ?>
                <?php else: ?>
                  <?= e($row[$column]) ?>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
            <td class="actions">
              <a class="btn btn-small" href="<?= e(url($def['route'] . '/edit', ['id' => $row['id']])) ?>">Edit</a>
              <?php foreach ($def['row_links'] ?? [] as [$route, $param, $label]): ?>
                <a class="btn btn-small" href="<?= e(url($route, [$param => $row['id']])) ?>"><?= e($label) ?></a>
              <?php endforeach; ?>
              <form method="post" action="<?= e(url($def['route'] . '/delete')) ?>" class="inline-form"
                    data-confirm="Delete this <?= e($def['singular']) ?>? This cannot be undone.">
                <?= $csrf->field() ?>
                <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                <button type="submit" class="btn btn-small btn-danger-outline">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
