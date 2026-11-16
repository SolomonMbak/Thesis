<?php /* Help panel content. Plain HTML, edit freely. */ ?>
<h3>What this page is for</h3>
<p>Settings for the whole installation: its name, whether anyone may sign up, how runs are started, how many run at once, and email.</p>
<h3>How to use it</h3>
<ol>
  <li>Fill in the SMTP details from your email provider and press Send test email.</li>
  <li>Choose exec mode on a VPS, or queue mode with the cron job on shared hosting.</li>
</ol>
<h3>What the fields mean</h3>
<dl>
  <dt>Maximum runs at the same time</dt><dd>One per processor core. Others wait in the queue.</dd>
  <dt>Public sign-up</dt><dd>Untick for invite-only.</dd>
</dl>
<h3>Common mistakes</h3>
<ul>
  <li>Using queue mode without the cron job: runs then wait for ever. The Health page shows the line to add.</li>
</ul>
<h3>Next</h3>
<p><a href="<?= e(url('health')) ?>">Health</a></p>
