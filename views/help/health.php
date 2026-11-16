<?php /* Help panel content. Plain HTML, edit freely. */ ?>
<h3>What this page is for</h3>
<p>Checks of the server that every school depends on: PHP, the database, background runs, disk space and more.</p>
<h3>How to use it</h3>
<ol>
  <li>Fix anything marked Problem first, following its advice.</li>
  <li>Copy the cron lines into your host's cron settings.</li>
  <li>After installing a new version, press Apply updates.</li>
</ol>
<h3>Common mistakes</h3>
<ul>
  <li>Ignoring the worker check in queue mode: without it no run starts.</li>
</ul>
<h3>Next</h3>
<p><a href="<?= e(url('backups')) ?>">Backups</a></p>
