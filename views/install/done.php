<?php
/** @var string $email */
?>
<div class="card card-narrow">
  <h1>Installation complete</h1>
  <p>The database is ready and your platform administrator account (<?= e($email) ?>) can now sign in.
    If you asked for a first school, it is ready with its teaching week, rules and an active semester.</p>
  <p>This installer is now locked and cannot be opened again.</p>
  <h2>Next steps</h2>
  <ol>
    <li>Sign in and open <strong>Platform, Health</strong> to confirm how timetable runs start on this server.</li>
    <li>On shared hosting, add the cron job shown on the Health page.</li>
    <li>Fill in the email settings under <strong>Platform settings</strong> and send a test email.</li>
    <li>Open your school's <strong>Setup Guide</strong> and follow the steps.</li>
  </ol>
  <p><a class="btn btn-primary" href="index.php?r=auth/login">Sign in</a></p>
</div>
