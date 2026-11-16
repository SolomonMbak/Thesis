<?php /* Help panel content. Plain HTML, edit freely. */ ?>
<h3>What this page is for</h3>
<p>The Setup Guide is your checklist from an empty school to a published timetable. It has nine steps. Each step shows whether it is done, still to do, optional or locked, and turns Done by itself when your data meets it.</p>
<h3>How to use it</h3>
<ol>
  <li>Start at the first step that is not Done and press its button.</li>
  <li>Add what the step asks for, then come back here (or follow the green banner at the bottom of each page).</li>
  <li>Locked steps say which steps must be finished first.</li>
  <li>Step 6, lecturer availability, is optional: you can press Skip for now.</li>
</ol>
<h3>What the fields mean</h3>
<dl>
  <dt>Done</dt><dd>Your data meets the step. If you later delete the data, the step becomes To do again.</dd>
  <dt>Optional</dt><dd>Recommended but not needed to publish.</dd>
  <dt>Locked</dt><dd>Another step must be done first.</dd>
</dl>
<h3>Common mistakes</h3>
<ul>
  <li>Expecting a step to turn green after a click: steps follow your data, so add the rooms, levels and so on first.</li>
  <li>Skipping Check readiness: Generate runs it anyway and will not start if it finds a problem.</li>
</ul>
<h3>Next</h3>
<p><a href="<?= e(url('school/profile')) ?>">Start with step 1: School profile and periods</a></p>
