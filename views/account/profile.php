<?php
/**
 * The user's own profile, theme and password.
 * @var array $errors
 * @var array $themes
 */
?>
<h1>Your profile</h1>
<p class="page-intro">Your account works in every school you belong to.</p>

<form method="post" action="<?= e(url('account/profile')) ?>" class="panel" novalidate>
  <?= $csrf->field() ?>
  <h2>Name and theme</h2>
  <div class="form-grid">
    <?= $this->partial('partials/field', ['name' => 'full_name', 'label' => 'Your name', 'value' => $user['full_name'], 'errors' => $errors, 'required' => true]) ?>
    <div class="field">
      <label for="f_email">Email address</label>
      <input type="email" id="f_email" value="<?= e($user['email']) ?>" readonly aria-describedby="f_email_help">
      <p class="field-help" id="f_email_help">Your sign-in name. It cannot be changed here.</p>
    </div>
    <?= $this->partial('partials/field', ['name' => 'theme', 'label' => 'Theme', 'type' => 'select', 'value' => $user['theme'],
        'options' => $themes, 'errors' => $errors, 'help' => 'System follows the light or dark setting of your phone or computer.']) ?>
  </div>
  <button type="submit" class="btn btn-primary">Save</button>
</form>

<form method="post" action="<?= e(url('account/password')) ?>" class="panel" id="password" novalidate>
  <?= $csrf->field() ?>
  <h2>Change password</h2>
  <div class="form-grid">
    <?= $this->partial('partials/field', ['name' => 'current_password', 'label' => 'Current password', 'type' => 'password', 'value' => '',
        'errors' => $errors, 'required' => true, 'attrs' => 'autocomplete="current-password"']) ?>
    <?= $this->partial('partials/field', ['name' => 'password', 'label' => 'New password', 'type' => 'password', 'value' => '',
        'errors' => $errors, 'required' => true, 'attrs' => 'autocomplete="new-password"', 'help' => 'At least 10 characters.']) ?>
    <?= $this->partial('partials/field', ['name' => 'password_confirm', 'label' => 'New password again', 'type' => 'password', 'value' => '',
        'errors' => $errors, 'required' => true, 'attrs' => 'autocomplete="new-password"']) ?>
  </div>
  <button type="submit" class="btn btn-primary">Change password</button>
</form>
