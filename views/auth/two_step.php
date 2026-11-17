<?php
/**
 * Second step of sign-in for users with two-step sign-in on.
 * @var string $email
 * @var array  $errors
 * @var string $invite
 */
?>
<div class="card card-narrow">
  <h1>Two-step sign-in</h1>
  <p>Your password is correct. Now open the authenticator app on your phone and type the 6-digit code it shows for
    <strong><?= e($email) ?></strong>.</p>

  <form method="post" action="<?= e(url('auth/two-step')) ?>" novalidate>
    <?= $csrf->field() ?>
    <?php if ($invite !== ''): ?><input type="hidden" name="invite" value="<?= e($invite) ?>"><?php endif; ?>
    <?= $this->partial('partials/field', ['name' => 'code', 'label' => 'Code', 'value' => '', 'errors' => $errors, 'required' => true,
        'attrs' => 'autocomplete="one-time-code" inputmode="numeric" autofocus maxlength="20" class="input-code"',
        'help' => 'The code changes every 30 seconds. Lost your phone? Type one of your recovery codes instead (like abcde-12345).']) ?>
    <button type="submit" class="btn btn-primary btn-block">Sign in</button>
  </form>
  <p class="small"><a href="<?= e(url('auth/login')) ?>">Start again with another account</a></p>
  <p class="small muted">No phone and no recovery codes? Ask the platform administrator to turn two-step sign-in off for your account.</p>
</div>
