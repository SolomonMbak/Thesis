<?php /* Help panel content. Plain HTML, edit freely. */ ?>
<h3>What this page is for</h3>
<p>Check readiness looks for problems in your data that make a timetable with no clashes impossible, before you wait for a run. It checks rooms, labs, each lecturer's free time, each level's hours and that every class has somewhere and some time to go.</p>
<h3>How to use it</h3>
<ol>
  <li>Press Check readiness now.</li>
  <li>If problems are listed, fix each one with the button beside it.</li>
  <li>Check again until it says Ready, then generate.</li>
</ol>
<h3>What the fields mean</h3>
<dl>
  <dt>Ready</dt><dd>A clash-free timetable is possible with this data (not guaranteed; the run will tell).</dd>
  <dt>Your data changed since the last check</dt><dd>Something was edited after the last check; check again.</dd>
</dl>
<h3>Common mistakes</h3>
<ul>
  <li>Generating without fixing the problems: the run will not start while problems remain.</li>
</ul>
<h3>Next</h3>
<p><a href="<?= e(url('generate')) ?>">Next: generate your timetable</a></p>
