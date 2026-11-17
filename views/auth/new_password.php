<?php
/**
 * First sign-in of a member added with a temporary password.
 * @var string $name
 * @var array  $errors
 */
?>
<div class="card card-narrow">
  <h1>Choose your own password</h1>
  <p>Welcome, <?= e($name) ?>. You signed in with a temporary password from your school's administrator.
    Choose a password only you know before going on.</p>
  <form method="post" action="<?= e(url('auth/new-password')) ?>" novalidate>
    <?= $csrf->field() ?>
    <?= $this->partial('partials/field', ['name' => 'password', 'label' => 'New password', 'type' => 'password', 'value' => '',
        'errors' => $errors, 'required' => true, 'attrs' => 'autocomplete="new-password" autofocus', 'help' => 'At least 10 characters.']) ?>
    <?= $this->partial('partials/field', ['name' => 'password_confirm', 'label' => 'Type it again', 'type' => 'password', 'value' => '',
        'errors' => $errors, 'required' => true, 'attrs' => 'autocomplete="new-password"']) ?>
    <button type="submit" class="btn btn-primary btn-block">Save and continue</button>
  </form>
  <form method="post" action="<?= e(url('auth/logout')) ?>">
    <?= $csrf->field() ?>
    <button type="submit" class="btn-link small">Sign out</button>
  </form>
</div>
