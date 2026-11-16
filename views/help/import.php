<?php /* Help panel content. Plain HTML, edit freely. */ ?>
<h3>What this page is for</h3>
<p>Imports rooms, levels, lecturers, courses or availability from a spreadsheet saved as CSV, so you do not have to type everything.</p>
<h3>How to use it</h3>
<ol>
  <li>Download the template for the data you have.</li>
  <li>Fill it in in Excel, keeping the first row exactly as it is.</li>
  <li>Choose File, Save As, CSV UTF-8.</li>
  <li>Choose what the file contains, choose the file and press Check and import.</li>
</ol>
<h3>What the fields mean</h3>
<dl>
  <dt>Order</dt><dd>Levels, rooms and lecturers first; then courses and availability, which refer to them.</dd>
  <dt>Updating</dt><dd>A row with an existing code or staff number updates that record instead of adding a copy.</dd>
</dl>
<h3>Common mistakes</h3>
<ul>
  <li>Saving as an Excel workbook (.xlsx) instead of CSV.</li>
  <li>Renaming or removing a header column.</li>
  <li>Importing courses before the levels and lecturers they refer to.</li>
</ul>
<h3>Next</h3>
<p><a href="<?= e(url('setup')) ?>">Back to the Setup Guide</a></p>
