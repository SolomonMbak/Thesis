<?php /* Help panel content. Plain HTML, edit freely. */ ?>
<h3>What this page is for</h3>
<p>The staff who teach. The timetable never puts one lecturer in two places at once, and keeps their classes out of periods they mark as unavailable.</p>
<h3>How to use it</h3>
<ol>
  <li>Press Add lecturer, or import them from a spreadsheet.</li>
  <li>Press Availability beside a lecturer to mark when they cannot teach.</li>
</ol>
<h3>What the fields mean</h3>
<dl>
  <dt>Short name</dt><dd>What appears in timetable cells, for example A. Bello.</dd>
  <dt>Active</dt><dd>Untick for staff on leave; give their courses to someone else.</dd>
</dl>
<h3>Common mistakes</h3>
<ul>
  <li>Deleting a lecturer who still teaches courses: change the courses first, or untick Active.</li>
</ul>
<h3>Next</h3>
<p><a href="<?= e(url('courses')) ?>">Next: add courses</a></p>
