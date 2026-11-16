<?php /* Help panel content. Plain HTML, edit freely. */ ?>
<h3>What this page is for</h3>
<p>The live view of one run: its status, how many generations it has done, the clashes left and the score, with a chart that updates every second or two.</p>
<h3>How to use it</h3>
<ol>
  <li>Watch the clashes fall to 0; the run then keeps improving the soft rules and stops when it stops improving.</li>
  <li>Press Stop to end early; the best timetable found so far is saved.</li>
  <li>When it finishes, press See the result.</li>
</ol>
<h3>What the fields mean</h3>
<dl>
  <dt>Generation</dt><dd>One round of improving all the candidate timetables.</dd>
  <dt>Clashes left</dt><dd>Hard rule breaks in the best timetable so far.</dd>
  <dt>Penalty</dt><dd>The score of the best timetable; lower is better.</dd>
  <dt>Number N in the queue</dt><dd>Runs from every school take turns; yours starts when its turn comes.</dd>
</dl>
<h3>Common mistakes</h3>
<ul>
  <li>Closing the page and thinking the run stopped: it keeps going on the server.</li>
</ul>
<h3>Next</h3>
<p><a href="<?= e(url('timetables')) ?>">All runs and results</a></p>
