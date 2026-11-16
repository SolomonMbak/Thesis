<?php /* Help panel content. Plain HTML, edit freely. */ ?>
<h3>What this page is for</h3>
<p>Add or change one room. All fields except the name are used by the timetable.</p>
<h3>How to use it</h3>
<ol>
  <li>Fill in the fields and press the button at the bottom.</li>
  <li>Use a short code; it is what appears in timetable cells.</li>
</ol>
<h3>What the fields mean</h3>
<dl>
  <dt>Code</dt><dd>A short unique name, for example LT1 or LAB2.</dd>
  <dt>Seats</dt><dd>Maximum number of students.</dd>
  <dt>Type</dt><dd>Laboratory for rooms where practicals can be taught.</dd>
  <dt>Available for timetabling</dt><dd>Untick to keep the room on file without using it.</dd>
</dl>
<h3>Common mistakes</h3>
<ul>
  <li>Entering the floor area or a guess instead of the real number of seats.</li>
</ul>
<h3>Next</h3>
<p><a href="<?= e(url('rooms')) ?>">Back to rooms</a></p>
