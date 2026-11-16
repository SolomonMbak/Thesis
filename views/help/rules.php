<?php /* Help panel content. Plain HTML, edit freely. */ ?>
<h3>What this page is for</h3>
<p>Explains the rules every timetable is scored with and lets you change how much each one counts.</p>
<h3>How to use it</h3>
<ol>
  <li>Read what each rule means.</li>
  <li>Raise a soft weight to make the search care more about it, lower it to care less.</li>
  <li>Press Save weights; new runs use them.</li>
</ol>
<h3>What the fields mean</h3>
<dl>
  <dt>Hard rules</dt><dd>Must never be broken. Their weight should stay far bigger than any soft weight.</dd>
  <dt>Soft rules</dt><dd>Wishes. A weight of 0 switches one off.</dd>
</dl>
<h3>Common mistakes</h3>
<ul>
  <li>Setting the hard weight low: the search might then accept a clash to avoid small preference issues.</li>
</ul>
<h3>Next</h3>
<p><a href="<?= e(url('generate')) ?>">Generate timetable</a></p>
