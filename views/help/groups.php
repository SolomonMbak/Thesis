<?php /* Help panel content. Plain HTML, edit freely. */ ?>
<h3>What this page is for</h3>
<p>Levels are groups of students who take their classes together, such as 100 Level Computer Science. A level never has two classes at the same time, and the number of students decides which rooms are big enough.</p>
<h3>How to use it</h3>
<ol>
  <li>Press Add level for each level, or import them from a spreadsheet.</li>
  <li>If your school timetables several departments, fill in Department so levels are easy to tell apart.</li>
</ol>
<h3>What the fields mean</h3>
<dl>
  <dt>Code</dt><dd>A short name for the timetable, for example 100L.</dd>
  <dt>Number of students</dt><dd>Used to choose a big enough room.</dd>
</dl>
<h3>Common mistakes</h3>
<ul>
  <li>Making one level for a whole faculty that never sits together: make one level per group of students who share a timetable.</li>
</ul>
<h3>Next</h3>
<p><a href="<?= e(url('lecturers')) ?>">Next: add lecturers</a></p>
