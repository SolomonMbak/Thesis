<?php
/** @var string $email */
?>
<div class="card card-narrow">
  <h1>Installation complete</h1>
  <p>The database is ready, the 50 teaching periods have been created and your administrator account
    (<?= e($email) ?>) can now sign in.</p>
  <p>This installer is now locked and cannot be opened again.</p>
  <h2>Next steps</h2>
  <ol>
    <li>Sign in and open <strong>Health</strong> to confirm how timetable runs will be started on this server.</li>
    <li>On shared hosting, add the cron job shown on the health page.</li>
    <li>Create the semester, then add lecturers, groups, rooms and courses.</li>
  </ol>
  <p><a class="btn btn-primary" href="index.php?r=auth/login">Sign in</a></p>
</div>
