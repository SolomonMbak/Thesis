<?php /* Help panel content. Plain HTML, edit freely. */ ?>
<h3>What this page is for</h3>
<p>The list of rooms and laboratories the timetable may use. The timetable never places a class in a room that is too small, and practicals only go into labs.</p>
<h3>How to use it</h3>
<ol>
  <li>Press Add room for each lecture room and lab, or import them all from a spreadsheet.</li>
  <li>Use Edit to change a room; untick "Available for timetabling" to stop using it for a while.</li>
</ol>
<h3>What the fields mean</h3>
<dl>
  <dt>Seats</dt><dd>How many students the room holds.</dd>
  <dt>Type</dt><dd>Lecture room or laboratory. Lectures may use labs if they are big enough.</dd>
</dl>
<h3>Common mistakes</h3>
<ul>
  <li>Forgetting the labs: practicals can only be placed in rooms marked as Laboratory.</li>
  <li>Deleting a room that saved timetables use: untick Available instead.</li>
</ul>
<h3>Next</h3>
<p><a href="<?= e(url('groups')) ?>">Next: add levels</a></p>
