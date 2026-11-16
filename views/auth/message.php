<?php
/**
 * A short message page.
 * @var string $heading
 * @var string $message
 */
?>
<div class="card card-narrow">
  <h1><?= e($heading) ?></h1>
  <p><?= e($message) ?></p>
  <p><a class="btn btn-primary" href="<?= e(url('auth/login')) ?>">Go to sign in</a></p>
</div>
