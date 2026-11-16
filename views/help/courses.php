<?php /* Help panel content. Plain HTML, edit freely. */ ?>
<h3>What this page is for</h3>
<p>The courses taught in the active semester. Each course says which level takes it, who teaches it and for how many hours a week. The classes to schedule are worked out from these automatically.</p>
<h3>How to use it</h3>
<ol>
  <li>Press Add course for each course, or import them from a spreadsheet.</li>
  <li>Use the Level filter to check each level's courses and weekly hours.</li>
</ol>
<h3>What the fields mean</h3>
<dl>
  <dt>Hours</dt><dd>Contact hours per week. Each hour becomes one class to place.</dd>
  <dt>Practical</dt><dd>Taught in a lab as a block of up to 3 hours.</dd>
  <dt>2-hour block</dt><dd>Two of the hours are taught back to back.</dd>
</dl>
<h3>Common mistakes</h3>
<ul>
  <li>Giving one level more weekly hours than there are open periods: Check readiness will say so.</li>
  <li>Forgetting to tick Practical for lab courses, which then may be placed in an ordinary room.</li>
</ul>
<h3>Next</h3>
<p><a href="<?= e(url('availability')) ?>">Next: set lecturer availability</a></p>
