<?php /* Help panel content. Plain HTML, edit freely. */ ?>
<h3>What this page is for</h3>
<p>Who can open your school, and what each person may do. Each person signs in with their own email and password.</p>
<h3>How to use it</h3>
<ol>
  <li>Enter an email address, choose a role and press Send invitation.</li>
  <li>No email set up, or the person is with you? Open "Add someone without email", fill in their name, email and role, note the temporary password and press Add member. Give them the password in person; they choose their own at the first sign-in.</li>
  <li>Change a role in the list; it takes effect at once.</li>
  <li>Remove someone who no longer needs access; their account stays for other schools.</li>
</ol>
<h3>What the fields mean</h3>
<dl>
  <dt>Administrator</dt><dd>Everything except deleting the school and handing it over.</dd>
  <dt>Timetable officer</dt><dd>Data, readiness, runs and publishing.</dd>
  <dt>Viewer</dt><dd>Can look at timetables and reports only.</dd>
  <dt>Two-step on</dt><dd>That person signs in with a code from their phone as well as their password.</dd>
</dl>
<h3>Common mistakes</h3>
<ul>
  <li>Sharing one account between several people: add or invite each person so the activity log shows who did what.</li>
  <li>Adding someone who already has an account (for example in another school): invite them instead, so they agree to join.</li>
</ul>
<h3>Next</h3>
<p><a href="<?= e(url('audit')) ?>">Activity log</a></p>
