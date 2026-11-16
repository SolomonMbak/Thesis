<?php
/**
 * Platform settings: name, sign-up, runs and email.
 * @var array $values
 * @var array $labels
 * @var array $errors
 * @var bool $canExec
 * @var string $guessedCli
 * @var bool $passwordSet
 */
?>
<h1>Platform settings</h1>
<p class="page-intro">Settings for the whole installation, shared by every school.</p>
<?php if ($errors): ?>
  <div class="alert alert-error" role="alert">Nothing was saved. Please correct the fields marked below.</div>
<?php endif; ?>

<form method="post" action="<?= e(url('platform/settings')) ?>" novalidate>
  <?= $csrf->field() ?>
  <section class="panel" aria-labelledby="general-title">
    <h2 id="general-title">General</h2>
    <div class="form-grid">
      <?= $this->partial('partials/field', ['name' => 'platform_name', 'label' => $labels['platform_name'], 'value' => $values['platform_name'],
          'errors' => $errors, 'required' => true, 'help' => 'Shown on sign-in pages and in emails.']) ?>
      <?= $this->partial('partials/field', ['name' => 'signup_open', 'label' => 'Anyone may create a school account', 'type' => 'checkbox',
          'value' => $values['signup_open'] === '1', 'errors' => $errors,
          'help' => 'Untick to make the platform invite-only: existing schools can still invite people.']) ?>
    </div>
  </section>

  <section class="panel" aria-labelledby="runs-title">
    <h2 id="runs-title">Timetable runs</h2>
    <div class="form-grid">
      <?= $this->partial('partials/field', ['name' => 'max_concurrent_jobs', 'label' => $labels['max_concurrent_jobs'], 'type' => 'number',
          'value' => $values['max_concurrent_jobs'], 'errors' => $errors, 'min' => 1, 'max' => 16,
          'help' => 'Runs use one processor core each. Use 1 on shared hosting and 2 on a 2-core server. Other runs wait their turn in the queue.']) ?>
      <?= $this->partial('partials/field', [
          'name' => 'launch_mode', 'label' => $labels['launch_mode'], 'type' => 'select', 'value' => $values['launch_mode'], 'errors' => $errors,
          'options' => ['exec' => 'Start at once in the background (exec mode, VPS)', 'queue' => 'Queue for the worker or cron job (queue mode)'],
          'help' => $canExec ? 'This server can start background processes, so exec mode works here.'
              : 'This server cannot start background processes. Use queue mode with the cron job shown on the Health page.',
      ]) ?>
      <?= $this->partial('partials/field', ['name' => 'php_cli_path', 'label' => $labels['php_cli_path'], 'value' => $values['php_cli_path'],
          'errors' => $errors, 'help' => 'Leave blank to use the detected path: ' . $guessedCli]) ?>
    </div>
  </section>

  <section class="panel" aria-labelledby="mail-title">
    <h2 id="mail-title">Email (verification, password reset, invitations)</h2>
    <p class="muted small">Leave the SMTP host blank to use the server's own mail function. Your host or email provider gives you these details.</p>
    <div class="form-grid">
      <?= $this->partial('partials/field', ['name' => 'smtp_host', 'label' => $labels['smtp_host'], 'value' => $values['smtp_host'], 'errors' => $errors,
          'help' => 'For example mail.example.edu or smtp.gmail.com.']) ?>
      <?= $this->partial('partials/field', ['name' => 'smtp_port', 'label' => $labels['smtp_port'], 'type' => 'number', 'value' => $values['smtp_port'], 'errors' => $errors,
          'help' => 'Usually 587 with TLS or 465 with SSL.']) ?>
      <?= $this->partial('partials/field', ['name' => 'smtp_encryption', 'label' => $labels['smtp_encryption'], 'type' => 'select', 'value' => $values['smtp_encryption'] ?: 'tls',
          'options' => ['tls' => 'TLS (STARTTLS, port 587)', 'ssl' => 'SSL (port 465)', 'none' => 'None (not recommended)'], 'errors' => $errors]) ?>
      <?= $this->partial('partials/field', ['name' => 'smtp_username', 'label' => $labels['smtp_username'], 'value' => $values['smtp_username'], 'errors' => $errors,
          'attrs' => 'autocomplete="off"']) ?>
      <?= $this->partial('partials/field', ['name' => 'smtp_password', 'label' => $labels['smtp_password'], 'type' => 'password', 'value' => '', 'errors' => $errors,
          'attrs' => 'autocomplete="new-password"', 'help' => $passwordSet ? 'A password is saved. Leave blank to keep it.' : 'Not set yet.']) ?>
      <?= $this->partial('partials/field', ['name' => 'smtp_from_email', 'label' => $labels['smtp_from_email'], 'type' => 'email', 'value' => $values['smtp_from_email'], 'errors' => $errors,
          'help' => 'For example timetable@example.edu.']) ?>
      <?= $this->partial('partials/field', ['name' => 'smtp_from_name', 'label' => $labels['smtp_from_name'], 'value' => $values['smtp_from_name'], 'errors' => $errors]) ?>
    </div>
  </section>
  <button type="submit" class="btn btn-primary">Save platform settings</button>
</form>

<form method="post" action="<?= e(url('platform/test-email')) ?>" class="panel button-row">
  <?= $csrf->field() ?>
  <span>Check the email settings by sending a test message to <?= e($user['email']) ?>.</span>
  <button type="submit" class="btn">Send test email</button>
</form>
