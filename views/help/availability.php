<?php /* Help panel content. Plain HTML, edit freely. */ ?>
<h3>What this page is for</h3>
<p>Records when each lecturer can teach. Unavailable periods are never used for that lecturer. Preferred periods are a wish: the timetable tries to use them.</p>
<h3>How to use it</h3>
<ol>
  <li>Choose a lecturer at the top.</li>
  <li>Click a period to change it: Available, then Preferred, then Unavailable, then back to Available. Each click saves at once.</li>
  <li>Use "Set a whole day or period for all lecturers" for things that apply to everyone, such as a staff meeting.</li>
</ol>
<h3>What the fields mean</h3>
<dl>
  <dt>Available</dt><dd>The default; the period may be used.</dd>
  <dt>Preferred</dt><dd>The lecturer would like to teach then. Only counts for lecturers who mark at least one.</dd>
  <dt>Unavailable</dt><dd>Never used for this lecturer.</dd>
  <dt>Blocked</dt><dd>Blocked for everyone on the school profile page.</dd>
</dl>
<h3>Common mistakes</h3>
<ul>
  <li>Marking so many periods unavailable that the lecturer's courses no longer fit. Check readiness will name the lecturer.</li>
</ul>
<h3>Next</h3>
<p><a href="<?= e(url('feasibility')) ?>">Next: check readiness</a></p>
