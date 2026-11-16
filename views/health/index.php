<?php
/**
 * Health page.
 * @var array $checks
 * @var array $cron
 */
$labels = ['ok' => 'OK', 'warning' => 'Check', 'problem' => 'Problem'];
$tags = ['ok' => 'tag-success', 'warning' => 'tag-warning', 'problem' => 'tag-danger'];
?>
<h1>Health</h1>
<p class="page-intro">Checks of the server that every school depends on. Fix anything marked Problem first.</p>

<div class="table-wrap">
  <table class="table">
    <thead><tr><th scope="col">Check</th><th scope="col">Status</th><th scope="col">Value</th><th scope="col">Advice</th></tr></thead>
    <tbody>
      <?php foreach ($checks as $c): ?>
        <tr>
          <td><?= e($c['label']) ?></td>
          <td><span class="tag <?= e($tags[$c['status']]) ?>"><?= e($labels[$c['status']]) ?></span></td>
          <td><?= e($c['detail']) ?></td>
          <td class="small"><?= e($c['advice']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<section class="panel" aria-labelledby="updates-title">
  <h2 id="updates-title">Apply updates</h2>
  <p>After uploading a new version, apply any database updates. Make a backup first.</p>
  <form method="post" action="<?= e(url('health/migrate')) ?>" data-confirm="Have you made a backup? Apply database updates now?">
    <?= $csrf->field() ?>
    <button type="submit" class="btn">Apply updates</button>
  </form>
</section>

<section class="panel" aria-labelledby="cron-title">
  <h2 id="cron-title">Cron jobs</h2>
  <p>Queue mode: run the worker every minute (cPanel: Cron Jobs, "Once per minute").</p>
  <pre><?= e($cron['worker']) ?></pre>
  <p>Daily backup at 02:00:</p>
  <pre><?= e($cron['backup']) ?></pre>
  <p class="small muted">On a VPS you can instead run <code>bin/worker.php</code> as a systemd service (see docs/DEPLOYMENT.md);
    set launch mode to queue and jobs start within 5 seconds.</p>
</section>


