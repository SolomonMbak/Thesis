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
  <p>This page sets up the database and the first administrator. It locks itself when it finishes.</p>

  <h2>1. Server check</h2>
  <table class="table">
    <thead><tr><th scope="col">Requirement</th><th scope="col">Result</th></tr></thead>
    <tbody>
    <?php foreach ($requirements as $check): ?>
      <tr>
        <td><?= e($check['label']) ?></td>
        <td class="<?= $check['ok'] ? 'text-success' : 'text-danger' ?>">
          <?= $check['ok'] ? 'OK' : 'Problem' ?>: <?= e($check['detail']) ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

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
        <?= $f('db_user', 'Database user', ['required' => true]) ?>
        <?= $f('db_pass', 'Database password', ['type' => 'password']) ?>
        <?= $f('app_url', 'Site address (optional)', ['type' => 'url', 'help' => 'For example https://timetable.example.edu. Leave blank to detect it.']) ?>
      </div>

      <h2>3. Institution</h2>
      <div class="form-grid">
        <?= $f('institution_name', 'Institution name', ['required' => true]) ?>
        <?= $f('department_name', 'Department name', ['required' => true]) ?>
      </div>

      <h2>4. Administrator account</h2>
      <div class="form-grid">
        <?= $f('admin_name', 'Your full name', ['required' => true]) ?>
        <?= $f('admin_email', 'Your email address', ['type' => 'email', 'required' => true]) ?>
        <?= $f('admin_password', 'Password', ['type' => 'password', 'required' => true, 'help' => 'At least 10 characters.']) ?>
        <?= $f('admin_password_confirm', 'Repeat the password', ['type' => 'password', 'required' => true]) ?>
      </div>

      <button type="submit" class="btn btn-primary">Install</button>
    </form>
  <?php endif; ?>
</div>
