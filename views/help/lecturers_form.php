<?php /* Help panel content. Plain HTML, edit freely. */ ?>
<h3>What this page is for</h3>
<p>Add or change one lecturer.</p>
<h3>How to use it</h3>
<ol>
  <li>Fill in the fields and press the button at the bottom.</li>
</ol>
<h3>What the fields mean</h3>
<dl>
  <dt>Staff number</dt><dd>Unique within your school. Used to match lecturers in spreadsheet imports.</dd>
  <dt>Short name</dt><dd>Keep it short: it must fit in a timetable cell.</dd>
  <dt>Email</dt><dd>Optional, for your records.</dd>
</dl>
<h3>Common mistakes</h3>
<ul>
  <li>Leaving the short name as the full name, which makes timetable cells crowded.</li>
</ul>
<h3>Next</h3>
<p><a href="<?= e(url('lecturers')) ?>">Back to lecturers</a></p>
