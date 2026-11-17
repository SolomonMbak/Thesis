<?php
/**
 * The user's own profile, theme and password.
 * @var array $errors
 * @var array $themes
 * @var bool  $twoStepOn
 * @var int   $codesLeft
 * @var array $browsers  RememberTokenRepository::forUser()
 * @var int   $rememberDays
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

<section class="panel" id="two-step" aria-labelledby="two-step-title">
  <h2 id="two-step-title">Two-step sign-in</h2>
  <?php if ($twoStepOn): ?>
    <p><span class="tag tag-success">On</span> Signing in needs your password and a code from your authenticator app.
      <?= e($codesLeft) ?> recovery code(s) left.</p>
    <a class="btn" href="<?= e(url('account/two-step')) ?>">Manage two-step sign-in</a>
  <?php else: ?>
    <p><span class="tag">Off</span> Add a second step to signing in: a 6-digit code from an app on your phone. Someone who learns your
      password still cannot get in. Recommended for owners and administrators.</p>
    <a class="btn btn-primary" href="<?= e(url('account/two-step')) ?>">Set up two-step sign-in</a>
  <?php endif; ?>
</section>

<section class="panel" id="devices" aria-labelledby="devices-title">
  <h2 id="devices-title">Where you stay signed in</h2>
  <p>Browsers where you ticked "Keep me signed in". Each stays signed in for up to <?= e($rememberDays) ?> days from that sign-in,
    unless you sign out there. Without it, you are signed out after 60 minutes without activity.</p>
  <?php if (!$browsers): ?>
    <p class="muted">None at the moment.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table table-stack">
        <thead><tr><th scope="col">Browser</th><th scope="col">Signed in</th><th scope="col">Last used</th><th scope="col">Until</th></tr></thead>
        <tbody>
          <?php foreach ($browsers as $b): ?>
            <tr>
              <td data-label="Browser"><?= e(browser_label($b['user_agent'])) ?> <span class="muted small">(<?= e($b['ip']) ?>)</span></td>
              <td data-label="Signed in"><?= e(substr((string) $b['created_at'], 0, 16)) ?></td>
              <td data-label="Last used"><?= e(substr((string) $b['last_used_at'], 0, 16)) ?></td>
              <td data-label="Until"><?= e(substr((string) $b['expires_at'], 0, 10)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <form method="post" action="<?= e(url('account/sign-out-everywhere')) ?>"
          data-confirm="Sign out on every browser where you stay signed in? Each one will ask for your password next time.">
      <?= $csrf->field() ?>
      <button type="submit" class="btn btn-danger-outline">Sign out on all browsers</button>
    </form>
  <?php endif; ?>
</section>
