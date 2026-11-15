<?php
/**
 * Sign-in form.
 * @var string $email
 * @var array<string,string> $errors
 */
?>
<div class="card card-narrow">
  <h1>Sign in</h1>
  <p class="muted">Class timetabling system</p>

  <?php if (!empty($errors['form'])): ?>
    <div class="alert alert-error" role="alert"><?= e($errors['form']) ?></div>
  <?php endif; ?>

  <form method="post" action="<?= e(url('auth/login')) ?>" novalidate>
    <?= $csrf->field() ?>
    <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>">
      <label for="email">Email address</label>
      <input type="email" id="email" name="email" value="<?= e($email) ?>"
             autocomplete="username" required autofocus
             <?= isset($errors['email']) ? 'aria-describedby="email-error" aria-invalid="true"' : '' ?>>
      <?php if (isset($errors['email'])): ?>
        <p class="field-error" id="email-error"><?= e($errors['email']) ?></p>
      <?php endif; ?>
    </div>
    <div class="field<?= isset($errors['password']) ? ' has-error' : '' ?>">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" autocomplete="current-password" required
             <?= isset($errors['password']) ? 'aria-describedby="password-error" aria-invalid="true"' : '' ?>>
      <?php if (isset($errors['password'])): ?>
        <p class="field-error" id="password-error"><?= e($errors['password']) ?></p>
      <?php endif; ?>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Sign in</button>
  </form>
  <p class="muted small">Forgotten your password? Ask an administrator to reset it.</p>
</div>
