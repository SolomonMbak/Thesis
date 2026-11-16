<?php /* Help panel content. Plain HTML, edit freely. */ ?>
<h3>What this page is for</h3>
<p>Every rule break of one timetable, counted per rule and listed one by one with names, so you can see exactly what to improve.</p>
<h3>How to use it</h3>
<ol>
  <li>Look at the hard rules first: they must all be 0.</li>
  <li>Read the list to find, for example, the levels with idle gaps.</li>
</ol>
<h3>What the fields mean</h3>
<dl>
  <dt>Count</dt><dd>How many breaks of that rule.</dd>
  <dt>Weight</dt><dd>Penalty points per break.</dd>
  <dt>Weighted</dt><dd>Count times weight.</dd>
</dl>
<h3>Common mistakes</h3>
<ul>
  <li>Worrying about soft rule breaks: some are unavoidable; they are wishes, not errors.</li>
</ul>
<h3>Next</h3>
<p><a href="<?= e(url('timetables')) ?>">Back to runs and results</a></p>
