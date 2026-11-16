<?php /* Help panel content. Plain HTML, edit freely. */ ?>
<h3>What this page is for</h3>
<p>Scores a timetable made by hand with exactly the same rules and weights as generated ones, so you can compare them fairly.</p>
<h3>How to use it</h3>
<ol>
  <li>Download the template.</li>
  <li>Write one row per class: course code, class number, day, start time and room.</li>
  <li>Save as CSV UTF-8 and upload it.</li>
</ol>
<h3>What the fields mean</h3>
<dl>
  <dt>session_no</dt><dd>The class number of the course, as on the How courses become classes page.</dd>
  <dt>start_time</dt><dd>The first period, like 08:00. A 2-hour class needs only its first period.</dd>
</dl>
<h3>Common mistakes</h3>
<ul>
  <li>Leaving a class out: every class of every course must appear exactly once. Missing ones are listed.</li>
</ul>
<h3>Next</h3>
<p><a href="<?= e(url('timetables')) ?>">Back to runs and results</a></p>
