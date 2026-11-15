<?php
/**
 * Constraints and settings.
 * @var array $weights
 * @var array $ga
 * @var array $general
 * @var array $labels
 * @var array $errors
 * @var bool  $canExec
 * @var string $guessedCli
 */
use App\Engine\GaParams;
?>
<h1>Constraints and settings</h1>

<?php if ($errors): ?>
  <div class="alert alert-error" role="alert">Nothing was saved. Please correct the fields marked below.</div>
<?php endif; ?>

<section class="panel" aria-labelledby="weights-title">
  <h2 id="weights-title">Constraint weights</h2>
  <p class="muted">Penalty = H weight x hard violations + the weighted soft violations. Keep the H weight much larger
    than any soft total so a clash is never worth trading for comfort. Each run keeps a copy of the weights it used.</p>
  <form method="post" action="<?= e(url('settings/weights')) ?>">
    <?= $csrf->field() ?>
    <table class="table">
      <thead><tr><th scope="col">Code</th><th scope="col">Constraint</th><th scope="col">Type</th><th scope="col">Weight</th></tr></thead>
      <tbody>
      <?php foreach ($weights as $w): ?>
        <?php $err = $errors['weight_' . $w['code']] ?? null; ?>
        <tr>
          <td><?= e($w['code']) ?></td>
          <td><label for="w_<?= e($w['code']) ?>"><?= e($w['description']) ?></label></td>
          <td><?= e($w['constraint_type']) ?></td>
          <td class="<?= $err ? 'has-error' : '' ?>">
            <input type="number" min="0" id="w_<?= e($w['code']) ?>" name="weight[<?= e($w['code']) ?>]"
                   value="<?= e($w['weight']) ?>" class="input-narrow">
            <?php if ($err): ?><p class="field-error"><?= e($err) ?></p><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <button type="submit" class="btn btn-primary">Save weights</button>
  </form>
</section>

<section class="panel" aria-labelledby="ga-title">
  <h2 id="ga-title">Default GA parameters</h2>
  <p class="muted">These fill in the Generate form. Each run can change them.</p>
  <form method="post" action="<?= e(url('settings/ga')) ?>">
    <?= $csrf->field() ?>
    <div class="form-grid">
      <?php foreach (GaParams::FIELDS as $key => [$label, $type]): ?>
        <?= $this->partial('partials/field', [
            'name' => $key, 'label' => $label, 'type' => 'number', 'value' => $ga[$key] ?? '',
            'errors' => $errors, 'step' => $type === 'float' ? '0.001' : '1',
        ]) ?>
      <?php endforeach; ?>
    </div>
    <button type="submit" class="btn btn-primary">Save GA defaults</button>
  </form>
</section>

<?php if ($isAdmin): ?>
<section class="panel" aria-labelledby="general-title">
  <h2 id="general-title">Institution, day and background runs</h2>
  <form method="post" action="<?= e(url('settings/general')) ?>">
    <?= $csrf->field() ?>
    <div class="form-grid">
      <?= $this->partial('partials/field', ['name' => 'institution_name', 'label' => $labels['institution_name'], 'value' => $general['institution_name'], 'errors' => $errors, 'required' => true]) ?>
      <?= $this->partial('partials/field', ['name' => 'department_name', 'label' => $labels['department_name'], 'value' => $general['department_name'], 'errors' => $errors, 'required' => true]) ?>
      <?= $this->partial('partials/field', ['name' => 'day_start', 'label' => $labels['day_start'], 'type' => 'time', 'value' => $general['day_start'], 'errors' => $errors]) ?>
      <?= $this->partial('partials/field', ['name' => 'day_end', 'label' => $labels['day_end'], 'type' => 'time', 'value' => $general['day_end'], 'errors' => $errors]) ?>
      <?= $this->partial('partials/field', ['name' => 'period_minutes', 'label' => $labels['period_minutes'], 'type' => 'number', 'value' => $general['period_minutes'], 'errors' => $errors]) ?>
      <?= $this->partial('partials/field', [
          'name' => 'launch_mode', 'label' => $labels['launch_mode'], 'type' => 'select', 'value' => $general['launch_mode'],
          'errors' => $errors,
          'options' => ['exec' => 'Start immediately in the background (exec mode, VPS)', 'queue' => 'Queue for the worker or cron job (queue mode)'],
          'help' => $canExec ? 'This server can start background processes, so exec mode is available.'
              : 'This server cannot start background processes. Use queue mode with a cron job.',
      ]) ?>
      <?= $this->partial('partials/field', [
          'name' => 'php_cli_path', 'label' => $labels['php_cli_path'], 'value' => $general['php_cli_path'], 'errors' => $errors,
          'help' => 'Leave blank to use the detected path: ' . $guessedCli,
      ]) ?>
    </div>
    <button type="submit" class="btn btn-primary">Save settings</button>
  </form>
</section>
<?php endif; ?>
