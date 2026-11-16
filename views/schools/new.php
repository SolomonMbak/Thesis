<?php
/**
 * Create another school (or a first one for a user who belongs to none).
 * @var array $errors
 * @var array $old
 * @var array $types
 */
?>
<h1>Create a school</h1>
<p class="page-intro">A new, empty workspace with its own rooms, levels, lecturers, courses and timetables. You will be its owner.
  Nothing is shared with your other schools.</p>

<form method="post" action="<?= e(url('schools/create')) ?>" class="panel" novalidate>
  <?= $csrf->field() ?>
  <div class="form-grid">
    <?= $this->partial('partials/field', ['name' => 'school_name', 'label' => 'School name', 'value' => $old['school_name'], 'errors' => $errors, 'required' => true]) ?>
    <?= $this->partial('partials/field', ['name' => 'department_label', 'label' => 'Department (optional)', 'value' => $old['department_label'],
        'errors' => $errors, 'help' => 'If the workspace is for one department, for example Department of Computer Science.']) ?>
    <?= $this->partial('partials/field', ['name' => 'school_type', 'label' => 'Type of school', 'type' => 'select', 'value' => $old['school_type'],
        'options' => $types, 'errors' => $errors]) ?>
  </div>
  <div class="button-row">
    <button type="submit" class="btn btn-primary">Create the school</button>
    <?php if ($school): ?><a class="btn" href="<?= e(url('dashboard')) ?>">Cancel</a><?php endif; ?>
  </div>
</form>
<?php if (!$school): ?>
  <p class="muted">Were you expecting to join an existing school? Ask one of its administrators to invite you; the email contains a link to join.</p>
<?php endif; ?>
