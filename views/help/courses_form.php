<?php /* Help panel content. Plain HTML, edit freely. */ ?>
<h3>What this page is for</h3>
<p>Add or change one course of the active semester.</p>
<h3>How to use it</h3>
<ol>
  <li>Choose the level and the lecturer, enter the weekly contact hours and tick the boxes that apply.</li>
</ol>
<h3>What the fields mean</h3>
<dl>
  <dt>Contact hours per week</dt><dd>A 3-hour lecture becomes three 1-hour classes, spread over different days where possible.</dd>
  <dt>Practical</dt><dd>Placed only in laboratories, in blocks of up to 3 hours (4 hours become 3 + 1).</dd>
  <dt>Allow one 2-hour block</dt><dd>For lectures: two hours together, the rest as single hours.</dd>
  <dt>Class size</dt><dd>Leave blank to use the size of the level. For a practical in lab groups, enter the size of one lab group so a smaller lab is enough.</dd>
</dl>
<h3>Common mistakes</h3>
<ul>
  <li>Entering credit units in the hours box: the timetable uses contact hours, not credit units.</li>
  <li>A lab group size larger than every lab: no room fits, and Check readiness will report it.</li>
</ul>
<h3>Next</h3>
<p><a href="<?= e(url('courses')) ?>">Back to courses</a></p>
