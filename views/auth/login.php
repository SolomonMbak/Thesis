<?php
/**
 * Sign-in form.
 * @var string $email
 * @var array<string,string> $errors
 * @var bool $signupOpen
 * @var string $invite token to return to after signing in (may be empty)
 * @var bool $remember
 */
?>
<div class="card card-narrow">
  <h1>Sign in</h1>
  <?= $this->partial('partials/what_it_does') ?>
  <p class="small"><a href="<?= e(url('about')) ?>">Read more about what the app does</a></p>

  <?php if (!empty($errors['form'])): ?>
    <div class="alert alert-error" role="alert"><?= e($errors['form']) ?></div>
  <?php endif; ?>
  <?php if ($invite !== ''): ?>
    <div class="alert alert-info" role="status">Sign in with the email address the invitation was sent to, and you will join the school straight away.</div>
  <?php endif; ?>

  <form method="post" action="<?= e(url('auth/login')) ?>" novalidate>
    <?= $csrf->field() ?>
    <?php if ($invite !== ''): ?><input type="hidden" name="invite" value="<?= e($invite) ?>"><?php endif; ?>
    <?= $this->partial('partials/field', ['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'value' => $email,
        'errors' => $errors, 'required' => true, 'attrs' => 'autocomplete="username" autofocus']) ?>
    <?= $this->partial('partials/field', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'value' => '',
        'errors' => $errors, 'required' => true, 'attrs' => 'autocomplete="current-password"']) ?>
    <?= $this->partial('partials/field', ['name' => 'remember', 'label' => 'Keep me signed in for ' . \App\Core\Auth::rememberDays() . ' days',
        'type' => 'checkbox', 'value' => $remember, 'errors' => $errors,
        'help' => 'Untick this on a shared or public computer. Signing out always ends it.']) ?>
    <button type="submit" class="btn btn-primary btn-block">Sign in</button>
  </form>
  <p class="small"><a href="<?= e(url('auth/forgot')) ?>">Forgot your password?</a></p>
  <?php if ($signupOpen): ?>
    <hr>
    <p>New here? <a class="btn btn-block" href="<?= e(url('auth/signup')) ?>">Create a school account</a></p>
    <p class="small muted">Free to set up. You get your own private workspace and a Setup Guide that takes you to your first timetable.</p>
  <?php endif; ?>
</div>
