<?php /* Help panel content. Plain HTML, edit freely. */ ?>
<h3>What this page is for</h3>
<p>This page sets your school name and the shape of the teaching week: when the day starts and ends, how long a period is, and which periods are blocked. Every timetable uses these periods.</p>
<h3>How to use it</h3>
<ol>
  <li>Check the name and the teaching day and press Save profile. This completes step 1 even if you changed nothing.</li>
  <li>In the teaching week grid, click a period to block it (for example a weekly assembly) or to open it again.</li>
  <li>Optionally upload a logo; it appears in the top bar and on the public timetable.</li>
</ol>
<h3>What the fields mean</h3>
<dl>
  <dt>Day starts at / Day ends at</dt><dd>The first class may start at the start time; the last must end by the end time.</dd>
  <dt>Period length</dt><dd>Usually 60 minutes. A 2-hour class uses two periods in a row.</dd>
  <dt>Blocked</dt><dd>No class is ever placed in a blocked period. Friday 12:00 to 14:00 starts blocked for Jumu'ah.</dd>
</dl>
<h3>Common mistakes</h3>
<ul>
  <li>Making the day shorter after timetables were saved: periods used by saved timetables cannot be removed, so the change is refused with a message.</li>
  <li>Blocking so many periods that a level's weekly hours no longer fit. Check readiness will tell you.</li>
</ul>
<h3>Next</h3>
<p><a href="<?= e(url('rooms')) ?>">Next: add rooms</a></p>
