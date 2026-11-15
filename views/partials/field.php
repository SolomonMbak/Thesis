<?php
/**
 * One labelled form field with its error message.
 * @var string $name
 * @var string $label
 * @var string $type       text|email|password|number|time|select|checkbox|textarea|url
 * @var mixed  $value
 * @var array  $errors
 * Optional: $options (value => label, for select), $help, $required, $attrs (raw, trusted), $step, $min, $max
 */
$type = $type ?? 'text';
$errors = $errors ?? [];
$error = $errors[$name] ?? null;
$id = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
$describedBy = trim(($error ? $id . '_error ' : '') . (!empty($help) ? $id . '_help' : ''));
$common = 'id="' . e($id) . '" name="' . e($name) . '"'
    . ($describedBy !== '' ? ' aria-describedby="' . e($describedBy) . '"' : '')
    . ($error ? ' aria-invalid="true"' : '')
    . (!empty($required) ? ' required' : '')
    . (isset($min) ? ' min="' . e($min) . '"' : '')
    . (isset($max) ? ' max="' . e($max) . '"' : '')
    . (isset($step) ? ' step="' . e($step) . '"' : '')
    . (!empty($attrs) ? ' ' . $attrs : '');
?>
<div class="field<?= $error ? ' has-error' : '' ?><?= $type === 'checkbox' ? ' field-checkbox' : '' ?>">
  <?php if ($type === 'checkbox'): ?>
    <input type="hidden" name="<?= e($name) ?>" value="0">
    <label for="<?= e($id) ?>">
      <input type="checkbox" value="1" <?= $common ?><?= !empty($value) ? ' checked' : '' ?>>
      <?= e($label) ?>
    </label>
  <?php else: ?>
    <label for="<?= e($id) ?>"><?= e($label) ?></label>
    <?php if ($type === 'select'): ?>
      <select <?= $common ?>>
        <?php foreach ($options as $optValue => $optLabel): ?>
          <option value="<?= e($optValue) ?>"<?= (string) $optValue === (string) $value ? ' selected' : '' ?>><?= e($optLabel) ?></option>
        <?php endforeach; ?>
      </select>
    <?php elseif ($type === 'textarea'): ?>
      <textarea rows="4" <?= $common ?>><?= e($value) ?></textarea>
    <?php else: ?>
      <input type="<?= e($type) ?>" value="<?= $type === 'password' ? '' : e($value) ?>" <?= $common ?>>
    <?php endif; ?>
  <?php endif; ?>
  <?php if (!empty($help)): ?>
    <p class="field-help" id="<?= e($id) ?>_help"><?= e($help) ?></p>
  <?php endif; ?>
  <?php if ($error): ?>
    <p class="field-error" id="<?= e($id) ?>_error"><?= e($error) ?></p>
  <?php endif; ?>
</div>
