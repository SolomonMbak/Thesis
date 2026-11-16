<?php /* Help panel content. Plain HTML, edit freely. */ ?>
<h3>What this page is for</h3>
<p>Starts the search for the best timetable with no clashes. The search runs on the server; you can leave the page and come back.</p>
<h3>How to use it</h3>
<ol>
  <li>Optionally give the run a name.</li>
  <li>Keep "Use targeted repair" ticked.</li>
  <li>Press Start. The live page shows progress; most runs take one to two minutes.</li>
</ol>
<h3>What the fields mean</h3>
<dl>
  <dt>Use targeted repair</dt><dd>Moves each clashing class to the best free time and room. Untick only to compare with the standard genetic algorithm.</dd>
  <dt>Random seed</dt><dd>Leave blank. The same seed and data give the same timetable again.</dd>
  <dt>Advanced search settings</dt><dd>Rarely needed. The usual values suit a department of about 40 courses.</dd>
</dl>
<h3>Common mistakes</h3>
<ul>
  <li>Starting a second run while one is waiting or running: a school has one run at a time.</li>
</ul>
<h3>Next</h3>
<p><a href="<?= e(url('timetables')) ?>">See runs and results</a></p>
