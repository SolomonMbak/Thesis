<?php
/**
 * Accept an invitation.
 * @var array|null $invite
 * @var string $token
 * @var array $errors
 * @var string $fullName
 * @var string $roleLabel
 * @var array|null $user signed-in user
 * @var bool $hasAccount an account exists for the invited email
 */
?>
<div class="card card-narrow">
  <?php if (!$invite || $invite['school_status'] !== 'active'): ?>
    <h1>This invitation does not work</h1>
    <p>Invitations work once and for 7 days. Ask the person who invited you to send a new one from Members and invitations.</p>
    <p><a class="btn btn-primary" href="<?= e(url('auth/login')) ?>">Go to sign in</a></p>
  <?php else: ?>
    <h1>Join <?= e($invite['school_name']) ?></h1>
    <p>You are invited as <strong><?= e($roleLabel) ?></strong> with the email address <strong><?= e($invite['email']) ?></strong>.</p>
    <?php if (!empty($errors['form'])): ?>
      <div class="alert alert-error" role="alert"><?= e($errors['form']) ?></div>
    <?php endif; ?>

    <?php if ($user && strtolower($user['email']) === strtolower($invite['email'])): ?>
      <form method="post" action="<?= e(url('invite/accept')) ?>">
        <?= $csrf->field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <button type="submit" class="btn btn-primary btn-block">Join <?= e($invite['school_name']) ?></button>
      </form>
    <?php elseif ($user): ?>
      <p>You are signed in as <?= e($user['email']) ?>. Sign out, then open the invitation link again and sign in as <?= e($invite['email']) ?>.</p>
      <form method="post" action="<?= e(url('auth/logout')) ?>">
        <?= $csrf->field() ?>
        <button type="submit" class="btn">Sign out</button>
      </form>
    <?php elseif ($hasAccount): ?>
      <p>You already have an account. Sign in and you will join the school straight away.</p>
      <a class="btn btn-primary btn-block" href="<?= e(url('auth/login', ['invite' => $token])) ?>">Sign in to accept</a>
    <?php else: ?>
      <p>Choose your name and a password to create your account.</p>
      <form method="post" action="<?= e(url('invite/accept')) ?>" novalidate>
        <?= $csrf->field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <?= $this->partial('partials/field', ['name' => 'full_name', 'label' => 'Your name', 'value' => $fullName, 'errors' => $errors,
            'required' => true, 'attrs' => 'autocomplete="name"']) ?>
        <?= $this->partial('partials/field', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'value' => '', 'errors' => $errors,
            'required' => true, 'attrs' => 'autocomplete="new-password"', 'help' => 'At least 10 characters.']) ?>
        <?= $this->partial('partials/field', ['name' => 'password_confirm', 'label' => 'Type it again', 'type' => 'password', 'value' => '',
            'errors' => $errors, 'required' => true, 'attrs' => 'autocomplete="new-password"']) ?>
        <button type="submit" class="btn btn-primary btn-block">Create my account and join</button>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</div>
