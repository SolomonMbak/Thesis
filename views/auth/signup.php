<?php
/**
 * Create a school account (BUILD_SPEC section 2).
 * @var array $errors
 * @var array $old
 * @var array $types
 */
?>
<div class="card card-narrow">
  <h1>Create a school account</h1>
  <p class="muted">You will be the owner of a private workspace for your school or department. Nobody else can see its data
    until you invite them.</p>

  <?php if (!empty($errors['form'])): ?>
    <div class="alert alert-error" role="alert"><?= e($errors['form']) ?></div>
  <?php elseif ($errors): ?>
    <div class="alert alert-error" role="alert">Your account was not created yet. Please correct the fields marked below.</div>
  <?php endif; ?>

  <form method="post" action="<?= e(url('auth/signup')) ?>" novalidate>
    <?= $csrf->field() ?>
    <h2>About you</h2>
    <?= $this->partial('partials/field', ['name' => 'full_name', 'label' => 'Your name', 'value' => $old['full_name'], 'errors' => $errors,
        'required' => true, 'attrs' => 'autocomplete="name"']) ?>
    <?= $this->partial('partials/field', ['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'value' => $old['email'],
        'errors' => $errors, 'required' => true, 'attrs' => 'autocomplete="email"',
        'help' => 'We send a link to confirm it. You can start working straight away.']) ?>
    <?= $this->partial('partials/field', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'value' => '',
        'errors' => $errors, 'required' => true, 'attrs' => 'autocomplete="new-password" minlength="10"',
        'help' => 'At least 10 characters. A short sentence is easy to remember and hard to guess.']) ?>
    <?= $this->partial('partials/field', ['name' => 'password_confirm', 'label' => 'Type the password again', 'type' => 'password',
        'value' => '', 'errors' => $errors, 'required' => true, 'attrs' => 'autocomplete="new-password"']) ?>

    <h2>About your school</h2>
    <?= $this->partial('partials/field', ['name' => 'school_name', 'label' => 'School name', 'value' => $old['school_name'], 'errors' => $errors,
        'required' => true, 'help' => 'For example National Open University of Nigeria. You can change it later.']) ?>
    <?= $this->partial('partials/field', ['name' => 'department_label', 'label' => 'Department (optional)', 'value' => $old['department_label'],
        'errors' => $errors, 'help' => 'Fill this in if the workspace is for one department, for example Department of Computer Science.']) ?>
    <?= $this->partial('partials/field', ['name' => 'school_type', 'label' => 'Type of school', 'type' => 'select', 'value' => $old['school_type'],
        'options' => $types, 'errors' => $errors]) ?>

    <button type="submit" class="btn btn-primary btn-block">Create my school account</button>
  </form>
  <p class="small">Already have an account? <a href="<?= e(url('auth/login')) ?>">Sign in</a>, then use "Create another school" in the top bar.</p>
</div>
