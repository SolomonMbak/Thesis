<?php
/**
 * Ask for a password reset link. The answer is the same whether or not the
 * email has an account.
 * @var string $email
 * @var bool $sent
 */
?>
<div class="card card-narrow">
  <h1>Forgot your password?</h1>
  <?php if ($sent): ?>
    <div class="alert alert-success" role="status">If an account uses <?= e($email) ?>, a link to choose a new password is on its way.
      It works for 1 hour. Check the spam folder too.</div>
    <p><a href="<?= e(url('auth/login')) ?>">Back to sign in</a></p>
  <?php else: ?>
    <p>Enter the email address you sign in with and we will send you a link to choose a new password.</p>
    <form method="post" action="<?= e(url('auth/forgot')) ?>" novalidate>
      <?= $csrf->field() ?>
      <?= $this->partial('partials/field', ['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'value' => $email,
          'errors' => [], 'required' => true, 'attrs' => 'autocomplete="username" autofocus']) ?>
      <button type="submit" class="btn btn-primary btn-block">Send the link</button>
    </form>
    <p class="small"><a href="<?= e(url('auth/login')) ?>">Back to sign in</a></p>
  <?php endif; ?>
</div>
