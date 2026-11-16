<?php
/**
 * The 7 days to confirm the email address have passed.
 * @var string $email
 */
?>
<div class="card card-narrow">
  <h1>Please confirm your email address</h1>
  <p>To keep using your account, open the link we sent to <strong><?= e($email) ?></strong>.
    Your data is safe and waiting for you.</p>
  <p>Cannot find the email? Look in the spam or junk folder, or send it again.</p>
  <form method="post" action="<?= e(url('auth/resend-verification')) ?>">
    <?= $csrf->field() ?>
    <button type="submit" class="btn btn-primary">Send the email again</button>
  </form>
  <form method="post" action="<?= e(url('auth/logout')) ?>" class="button-row">
    <?= $csrf->field() ?>
    <button type="submit" class="btn">Sign out</button>
  </form>
</div>
