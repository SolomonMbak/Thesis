<?php
/**
 * Generic master data list: primary action top right, helpful empty state,
 * search and filters; the table stacks into cards on phones.
 * @var array  $def
 * @var array  $rows
 * @var string $q
 * @var array  $activeFilters
 * @var array  $options
 * @var string|null $notice
 */
$bools = $def['bool_columns'] ?? [];
$valueLabels = $def['value_labels'] ?? [];
$filtered = $q !== '' || $activeFilters;
?>
<div class="page-head">
  <h1><?= e($def['title']) ?></h1>
  <div class="button-row">
    <a class="btn" href="<?= e(url('import')) ?>"><?= icon('import') ?> Import from spreadsheet</a>
    <a class="btn btn-primary" href="<?= e(url($def['route'] . '/create')) ?>"><?= icon('plus') ?> <?= e($def['add']) ?></a>
  </div>
</div>

<?php if ($notice): ?>
  <div class="alert alert-warning" role="status"><?= e($notice) ?></div>
<?php endif; ?>

<?php if ($rows || $filtered): ?>
  <form method="get" action="<?= e(url()) ?>" class="toolbar" role="search">
    <input type="hidden" name="r" value="<?= e($def['route']) ?>">
    <div class="field">
      <label for="q">Search</label>
      <input type="search" id="q" name="q" value="<?= e($q) ?>">
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
    <?php if ($filtered): ?>
      <a class="btn" href="<?= e(url($def['route'])) ?>">Clear</a>
    <?php endif; ?>
  </form>
<?php endif; ?>

<?php if (!$rows && $filtered): ?>
  <div class="panel empty-state"><p>Nothing matches your search. Try other words or clear the filter.</p></div>
<?php elseif (!$rows): ?>
  <div class="panel empty-state">
    <p><?= e($def['empty']) ?></p>
    <div class="button-row">
      <a class="btn btn-primary" href="<?= e(url($def['route'] . '/create')) ?>"><?= icon('plus') ?> <?= e($def['add']) ?></a>
      <a class="btn" href="<?= e(url('import')) ?>">Import from spreadsheet</a>
    </div>
  </div>
<?php else: ?>
  <p class="muted small"><?= e(count($rows)) ?> <?= e(count($rows) === 1 ? $def['singular'] : $def['plural']) ?></p>
  <div class="table-wrap">
    <table class="table table-stack">
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
            <?php foreach ($def['columns'] as $column => $heading): ?>
              <td data-label="<?= e($heading) ?>">
                <?php if (in_array($column, $bools, true)): ?>
                  <?= $row[$column] ? 'Yes' : '<span class="muted">No</span>' ?>
                <?php elseif (isset($valueLabels[$column])): ?>
                  <?= e($valueLabels[$column][$row[$column]] ?? $row[$column]) ?>
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
