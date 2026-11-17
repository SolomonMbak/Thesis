<?php
/**
 * Installer form.
 * @var array $requirements
 * @var bool  $ready
 * @var array $input
 * @var array $errors
 */
$f = fn (string $name, string $label, array $extra = []) => $this->partial('partials/field', [
    'name' => $name, 'label' => $label, 'value' => $input[$name] ?? '', 'errors' => $errors,
] + $extra);
?>
<div class="card">
  <h1>Install the timetabling system</h1>
  <p>This page sets up the database and the platform administrator, and can create your first school. It locks itself when it finishes.</p>

  <h2>1. Server check</h2>
  <div class="table-wrap">
    <table class="table table-stack">
      <thead><tr><th scope="col">Requirement</th><th scope="col">Result</th></tr></thead>
      <tbody>
      <?php foreach ($requirements as $check): ?>
        <tr>
          <td data-label="Requirement"><?= e($check['label']) ?></td>
          <td data-label="Result" class="<?= $check['ok'] ? 'text-success' : 'text-danger' ?>"><?= $check['ok'] ? 'OK' : 'Problem' ?>: <?= e($check['detail']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if (!$ready): ?>
    <div class="alert alert-error" role="alert">Fix the problems above, then reload this page.</div>
  <?php else: ?>
    <?php if (!empty($errors['form'])): ?>
      <div class="alert alert-error" role="alert"><?= e($errors['form']) ?></div>
    <?php elseif ($errors): ?>
      <div class="alert alert-error" role="alert">Please correct the fields marked below.</div>
    <?php endif; ?>

    <form method="post" action="install.php" novalidate>
      <?= $csrf->field() ?>
      <h2>2. Database</h2>
      <p class="muted">Create an empty MySQL or MariaDB database first (in cPanel: MySQL Databases), then enter its details.</p>
      <div class="form-grid">
        <?= $f('db_host', 'Database host', ['required' => true, 'help' => 'Usually 127.0.0.1 or localhost.']) ?>
        <?= $f('db_port', 'Database port', ['type' => 'number', 'required' => true]) ?>
        <?= $f('db_name', 'Database name', ['required' => true]) ?>
        <?= $f('db_user', 'Database user', ['required' => true,
            'help' => 'A user made only for this app, with rights on this database only. Never root.']) ?>
        <?= $f('db_pass', 'Database password', ['type' => 'password',
            'help' => 'A long random password used nowhere else. It is saved in config/config.local.php, outside the web folder.']) ?>
        <?= $f('app_url', 'Site address', ['type' => 'url', 'help' => 'The address people use, for example https://timetable.example.edu. Emailed links (sign-up, password reset, invitations) use it.']) ?>
      </div>

      <h2>3. Platform</h2>
      <div class="form-grid">
        <?= $f('platform_name', 'Platform name', ['required' => true, 'help' => 'Shown on sign-in pages and in emails.']) ?>
        <?= $f('signup_open', 'Anyone may create a school account', ['type' => 'checkbox', 'value' => ($input['signup_open'] ?? '1') === '1',
            'help' => 'Untick for invite-only. You can change this later in Platform settings.']) ?>
      </div>

      <h2>4. Your administrator account</h2>
      <p class="muted">This account manages the installation (schools, platform settings, health, backups).</p>
      <div class="form-grid">
        <?= $f('admin_name', 'Your full name', ['required' => true]) ?>
        <?= $f('admin_email', 'Your email address', ['type' => 'email', 'required' => true]) ?>
        <?= $f('admin_password', 'Password', ['type' => 'password', 'required' => true, 'help' => 'At least 10 characters.']) ?>
        <?= $f('admin_password_confirm', 'Repeat the password', ['type' => 'password', 'required' => true]) ?>
      </div>

      <h2>5. Your first school (optional)</h2>
      <div class="form-grid">
        <?= $f('create_school', 'Create a school now, with me as its owner', ['type' => 'checkbox', 'value' => ($input['create_school'] ?? '1') === '1']) ?>
        <?= $f('school_name', 'School name', ['help' => 'For example National Open University of Nigeria.']) ?>
        <?= $f('department_label', 'Department (optional)', ['help' => 'For example Department of Computer Science.']) ?>
      </div>
      <p class="small muted">It gets the 50 default periods (Friday 12:00 to 14:00 blocked), the default rules and an active semester.</p>

      <button type="submit" class="btn btn-primary">Install</button>
    </form>
  <?php endif; ?>
</div>
