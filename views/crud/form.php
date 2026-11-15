<?php
/**
 * Generic add / edit form.
 * @var array    $def
 * @var int|null $id
 * @var array    $values
 * @var array    $errors
 * @var array    $options
 */
?>
<h1><?= e($title) ?></h1>
<p><a href="<?= e(url($def['route'])) ?>">Back to <?= e(strtolower($def['title'])) ?></a></p>

<?php if ($errors): ?>
  <div class="alert alert-error" role="alert">Nothing was saved. Please correct the fields marked below.</div>
<?php endif; ?>

<form method="post" action="<?= e(url($def['route'] . ($id ? '/update' : '/store'))) ?>" class="panel" novalidate>
  <?= $csrf->field() ?>
  <?php if ($id): ?>
    <input type="hidden" name="id" value="<?= e($id) ?>">
  <?php endif; ?>
  <div class="form-grid">
    <?php foreach ($def['fields'] as $name => $field): ?>
      <?php
      $type = $field['type'] ?? 'text';
      $fieldOptions = $field['options'] ?? $options[$name] ?? [];
      if ($type === 'select' && !isset($field['options'])) {
          $fieldOptions = ['' => 'Choose...'] + $fieldOptions;
      }
      ?>
      <?= $this->partial('partials/field', [
          'name' => $name,
          'label' => $field['label'],
          'type' => $type,
          'value' => $values[$name] ?? '',
          'errors' => $errors,
          'options' => $fieldOptions,
          'help' => $field['help'] ?? null,
          'required' => str_contains($field['rules'], 'required'),
      ]) ?>
    <?php endforeach; ?>
  </div>
  <div class="button-row">
    <button type="submit" class="btn btn-primary"><?= $id ? 'Save changes' : 'Add ' . e($def['singular']) ?></button>
    <a class="btn" href="<?= e(url($def['route'])) ?>">Cancel</a>
  </div>
</form>
