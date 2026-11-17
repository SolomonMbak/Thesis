<?php /* Help panel content. Plain HTML, edit freely. */ ?>
<h3>What this page is for</h3>
<p>The genetic algorithm makes one week that repeats all semester. Here you give the real first and last day of teaching and the days without teaching (public holidays, breaks), so the <strong>Month</strong> view and the dated export show the timetable on real dates.</p>
<h3>How to use it</h3>
<ol>
  <li>Enter the first day of teaching. Then choose how long teaching lasts: <strong>Until a date</strong> (enter the last day) or <strong>For a number of weeks</strong> (for example 13; the last day is worked out for you). Press Save dates. The green box shows the length.</li>
  <li>Add each public holiday or break with a reason. For a break of several days, fill in Until; weekends are skipped.</li>
  <li>Open any timetable and choose Month to check the result.</li>
</ol>
<h3>What the fields mean</h3>
<dl>
  <dt>First and last day of teaching</dt><dd>Days before or after are shown as outside the semester. Both are optional; empty the fields and save to remove them.</dd>
  <dt>Number of weeks</dt><dd>Counted from the first day, holidays included. If the last day falls on a weekend, it moves back to the Friday.</dd>
  <dt>Reason</dt><dd>Shown on the calendar, for example Eid al-Fitr.</dd>
</dl>
<h3>Common mistakes</h3>
<ul>
  <li>Expecting holidays to move classes to another day: they only remove those days from the calendar; the weekly timetable stays the same.</li>
</ul>
<h3>Next</h3>
<p><a href="<?= e(url('timetables')) ?>">Runs and results</a></p>
