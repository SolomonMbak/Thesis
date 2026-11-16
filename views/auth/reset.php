<?php
/**
 * Choose a new password from an emailed link.
 * @var string $token
 * @var bool $valid
 * @var array $errors
 */
?>
<div class="card card-narrow">
  <h1>Choose a new password</h1>
  <?php if (!$valid): ?>
    <div class="alert alert-error" role="alert">This link does not work any more. Reset links work once and for 1 hour.</div>
    <p><a class="btn btn-primary" href="<?= e(url('auth/forgot')) ?>">Ask for a new link</a></p>
  <?php else: ?>
    <form method="post" action="<?= e(url('auth/reset')) ?>" novalidate>
      <?= $csrf->field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <?= $this->partial('partials/field', ['name' => 'password', 'label' => 'New password', 'type' => 'password', 'value' => '',
          'errors' => $errors, 'required' => true, 'attrs' => 'autocomplete="new-password"', 'help' => 'At least 10 characters.']) ?>
      <?= $this->partial('partials/field', ['name' => 'password_confirm', 'label' => 'Type it again', 'type' => 'password', 'value' => '',
          'errors' => $errors, 'required' => true, 'attrs' => 'autocomplete="new-password"']) ?>
      <button type="submit" class="btn btn-primary btn-block">Save the new password</button>
    </form>
  <?php endif; ?>
</div>
