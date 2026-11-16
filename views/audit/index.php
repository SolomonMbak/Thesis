<?php
/**
 * Activity log of the school.
 * @var array $rows
 * @var array $users
 * @var array $actions
 * @var string $action
 * @var int $userId
 * @var int $page
 * @var int $pages
 * @var int $total
 */
$query = fn (int $p) => url('audit', array_filter(['action' => $action, 'user' => $userId ?: null, 'page' => $p]));
?>
<h1>Activity log</h1>
<p class="page-intro">Every sign-in, change to the data, run, publication and settings change in this school, newest first.</p>
<form method="get" action="<?= e(url()) ?>" class="toolbar">
  <input type="hidden" name="r" value="audit">
  <div class="field">
    <label for="action">Area</label>
    <select id="action" name="action">
      <option value="">All</option>
      <?php foreach ($actions as $a): ?><option value="<?= e($a) ?>"<?= $action === $a ? ' selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="user">Person</label>
    <select id="user" name="user">
      <option value="">All</option>
      <?php foreach ($users as $id => $name): ?><option value="<?= e($id) ?>"<?= $userId === (int) $id ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
    </select>
  </div>
  <button type="submit" class="btn">Filter</button>
</form>

<?php if (!$rows): ?>
  <div class="panel empty-state">No entries match.</div>
<?php else: ?>
  <p class="muted small"><?= e($total) ?> entries. Page <?= e($page) ?> of <?= e($pages) ?>.</p>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th scope="col">When</th><th scope="col">Who</th><th scope="col">Action</th><th scope="col">Record</th><th scope="col">Details</th><th scope="col">IP</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="nowrap"><?= e($r['created_at']) ?></td>
            <td><?= e($r['full_name'] ?? 'System') ?></td>
            <td><code><?= e($r['action']) ?></code></td>
            <td><?= e($r['entity'] ? $r['entity'] . ' #' . $r['entity_id'] : '') ?></td>
            <td class="small details-cell"><?= e($r['details']) ?></td>
            <td><?= e($r['ip']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <nav class="pagination" aria-label="Pages">
    <?php if ($page > 1): ?><a class="btn btn-small" href="<?= e($query($page - 1)) ?>">Newer</a><?php endif; ?>
    <?php if ($page < $pages): ?><a class="btn btn-small" href="<?= e($query($page + 1)) ?>">Older</a><?php endif; ?>
  </nav>
<?php endif; ?>
