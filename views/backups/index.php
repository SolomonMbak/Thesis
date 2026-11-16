<?php
/**
 * Backups.
 * @var array $backups
 * @var int $keep
 */
?>
<h1>Backups</h1>
<p class="muted">Each backup is a compressed SQL file of the whole database, kept in storage/backups (never web-accessible).
  The newest <?= e($keep) ?> are kept. Schedule a daily backup with the cron line on the health page.</p>
<p class="muted">To restore into an empty database: <code>gunzip -c FILE.sql.gz | mysql -u USER -p DATABASE</code>
  (or import the unzipped .sql file in phpMyAdmin).</p>

<form method="post" action="<?= e(url('backups/create')) ?>" class="button-row">
  <?= $csrf->field() ?>
  <button type="submit" class="btn btn-primary">Create a backup now</button>
</form>

<?php if (!$backups): ?>
  <div class="panel empty-state">No backups yet. Create one now, and before every update.</div>
<?php else: ?>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th scope="col">File</th><th scope="col">Created</th><th scope="col" class="num">Size</th><th scope="col"><span class="visually-hidden">Download</span></th></tr></thead>
      <tbody>
        <?php foreach ($backups as $b): ?>
          <tr>
            <td><code><?= e($b['name']) ?></code></td>
            <td><?= e(date('Y-m-d H:i', $b['time'])) ?></td>
            <td class="num"><?= e(number_format($b['bytes'] / 1024, 1)) ?> KB</td>
            <td><a class="btn btn-small" href="<?= e(url('backups/download', ['file' => $b['name']])) ?>">Download</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
