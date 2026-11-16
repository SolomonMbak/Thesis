<?php /* Help panel content. Plain HTML, edit freely. */ ?>
<h3>What this page is for</h3>
<p>Every run and imported timetable of this semester, newest first. From here you open, compare and publish timetables.</p>
<h3>How to use it</h3>
<ol>
  <li>Open a finished run to see its result and grids.</li>
  <li>Tick two or more finished runs and press Compare ticked runs to see them side by side.</li>
  <li>Use Import a manual timetable to score the timetable you made by hand.</li>
</ol>
<h3>What the fields mean</h3>
<dl>
  <dt>Clashes</dt><dd>Hard rule breaks; 0 means usable.</dd>
  <dt>Soft penalty</dt><dd>How well the wishes are met; lower is better.</dd>
  <dt>Type</dt><dd>Genetic algorithm with repair, standard genetic algorithm, or an imported manual timetable.</dd>
</dl>
<h3>Common mistakes</h3>
<ul>
  <li>Publishing a run that still has clashes. The result page warns you first.</li>
</ul>
<h3>Next</h3>
<p><a href="<?= e(url('published')) ?>">Published timetable and share link</a></p>
