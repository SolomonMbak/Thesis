<?php
/** Shown when the platform administrator has turned public sign-up off (invite-only). */
?>
<div class="card card-narrow">
  <h1>Sign-up is by invitation</h1>
  <p>New school accounts can only be created by invitation at the moment. If your school already uses the system,
    ask one of its administrators to invite you from Members and invitations.</p>
  <p><a class="btn btn-primary" href="<?= e(url('auth/login')) ?>">Sign in</a></p>
</div>
