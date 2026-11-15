<?php
/**
 * Friendly error page.
 * @var int $status
 * @var string $message
 */
?>
<div class="card card-narrow">
  <h1><?= e($title) ?></h1>
  <p><?= e($message) ?></p>
  <p>
    <?php if ($status === 401): ?>
      <a class="btn btn-primary" href="<?= e(url('auth/login')) ?>">Sign in</a>
    <?php else: ?>
      <a class="btn btn-primary" href="<?= e(url('dashboard')) ?>">Go to the dashboard</a>
    <?php endif; ?>
  </p>
  <p class="muted small">Error code <?= e($status) ?></p>
</div>
