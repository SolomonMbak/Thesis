<?php
/**
 * New recovery codes, shown once.
 * @var string[] $codes
 * @var string   $email
 */
?>
<h1>Your recovery codes</h1>
<div class="alert alert-warning" role="alert">Save these codes now. This is the only time they are shown.</div>
<p class="page-intro">If you lose your phone, sign in with your password and type one of these codes instead of the 6-digit code.
  Each code works once. Print them or copy them into a safe place, such as your password manager. Do not keep them on the same phone.</p>

<section class="panel recovery-sheet">
  <h2>Recovery codes for <?= e($email) ?></h2>
  <ol class="recovery-codes">
    <?php foreach ($codes as $code): ?><li><code><?= e($code) ?></code></li><?php endforeach; ?>
  </ol>
  <textarea id="recovery-text" class="visually-hidden" readonly aria-hidden="true" tabindex="-1"><?= e("Recovery codes for {$email}\n" . implode("\n", $codes)) ?></textarea>
  <div class="button-row no-print">
    <button type="button" class="btn" data-copy="recovery-text"><?= icon('copy') ?> Copy the codes</button>
    <button type="button" class="btn" data-print>Print</button>
  </div>
</section>
<p class="no-print"><a class="btn btn-primary" href="<?= e(url('account/profile')) ?>">I have saved them</a></p>
