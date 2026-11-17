<?php
/**
 * Two-step sign-in: set-up (QR code and key) when off; status, new recovery codes and turning off when on.
 * @var bool   $on
 * @var array  $errors
 * Off: @var string $qr (trusted SVG from QrCode), @var string $key, @var string $issuer
 * On:  @var int $codesLeft
 */
?>
<h1>Two-step sign-in</h1>
<p class="page-intro">With two-step sign-in, signing in needs your password <strong>and</strong> a 6-digit code from an app on your phone.
  Someone who learns your password still cannot get in. Recommended for owners and administrators.</p>

<?php if (!$on): ?>
  <section class="panel two-step-setup" aria-labelledby="step1">
    <h2 id="step1">1. Install an authenticator app</h2>
    <p>Any of these free apps works: Google Authenticator, Microsoft Authenticator, Authy, or the password manager you already use.</p>

    <h2>2. Add this account to the app</h2>
    <div class="qr-row">
      <div class="qr-box"><?= $qr ?></div>
      <div>
        <p>In the app, choose <strong>Add account</strong> (or the + button), then <strong>Scan a QR code</strong> and point the camera at the code.</p>
        <p>Cannot scan? Choose <strong>Enter a setup key</strong> and type:</p>
        <p class="setup-key" data-setup-key><?= e($key) ?></p>
        <p class="small muted">Account: your email. Type: time based. The app then shows <?= e($issuer) ?> with a code that changes every 30 seconds.</p>
      </div>
    </div>

    <h2>3. Confirm</h2>
    <form method="post" action="<?= e(url('account/two-step/enable')) ?>" novalidate>
      <?= $csrf->field() ?>
      <div class="form-grid">
        <?= $this->partial('partials/field', ['name' => 'code', 'label' => 'Code from the app', 'value' => '', 'errors' => $errors, 'required' => true,
            'attrs' => 'autocomplete="one-time-code" inputmode="numeric" maxlength="7" class="input-code"', 'help' => 'The 6 digits the app shows now.']) ?>
        <?= $this->partial('partials/field', ['name' => 'password', 'label' => 'Your password', 'type' => 'password', 'value' => '', 'errors' => $errors,
            'required' => true, 'attrs' => 'autocomplete="current-password"', 'help' => 'To prove it is really you making this change.']) ?>
      </div>
      <button type="submit" class="btn btn-primary">Turn on two-step sign-in</button>
    </form>
    <p class="small muted">Next you get 10 recovery codes. Keep them somewhere safe: each one lets you sign in once if you lose your phone.</p>
  </section>
<?php else: ?>
  <div class="alert alert-success" role="status">Two-step sign-in is <strong>on</strong> for your account. You have <?= e($codesLeft) ?> unused recovery code(s).</div>

  <section class="panel" aria-labelledby="codes-title">
    <h2 id="codes-title">Recovery codes</h2>
    <p>Lost the codes, or used most of them? Make 10 new ones. The old ones stop working.</p>
    <form method="post" action="<?= e(url('account/two-step/new-codes')) ?>" novalidate>
      <?= $csrf->field() ?>
      <div class="form-grid">
        <?= $this->partial('partials/field', ['name' => 'codes_password', 'label' => 'Your password', 'type' => 'password', 'value' => '',
            'errors' => $errors, 'required' => true, 'attrs' => 'autocomplete="current-password"']) ?>
      </div>
      <button type="submit" class="btn">Make new recovery codes</button>
    </form>
  </section>

  <section class="panel" aria-labelledby="off-title">
    <h2 id="off-title">Turn off two-step sign-in</h2>
    <p>You will sign in with your password only. To move to a new phone, turn it off here and set it up again.</p>
    <form method="post" action="<?= e(url('account/two-step/disable')) ?>" novalidate data-confirm="Turn off two-step sign-in? Your account will be protected by your password only.">
      <?= $csrf->field() ?>
      <div class="form-grid">
        <?= $this->partial('partials/field', ['name' => 'password', 'label' => 'Your password', 'type' => 'password', 'value' => '',
            'errors' => $errors, 'required' => true, 'attrs' => 'autocomplete="current-password"']) ?>
      </div>
      <button type="submit" class="btn btn-danger-outline">Turn off</button>
    </form>
  </section>
<?php endif; ?>
<p><a href="<?= e(url('account/profile')) ?>">Back to your profile</a></p>
