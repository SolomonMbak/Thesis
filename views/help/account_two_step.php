<?php /* Help panel content. Plain HTML, edit freely. */ ?>
<h3>What this page is for</h3>
<p>Two-step sign-in adds a second check when you sign in: after your password, a 6-digit code from an authenticator app on your phone. Someone who learns your password still cannot get in.</p>
<h3>How to use it</h3>
<ol>
  <li>Install a free authenticator app on your phone, such as Google Authenticator or Microsoft Authenticator.</li>
  <li>In the app, add an account and scan the QR code. If you cannot scan, choose "Enter a setup key" and type the key shown under the code.</li>
  <li>Type the 6 digits the app shows, and your password, then press Turn on.</li>
  <li>Save the 10 recovery codes you are shown: print them or copy them into your password manager.</li>
</ol>
<h3>What the fields mean</h3>
<dl>
  <dt>Code from the app</dt><dd>Changes every 30 seconds. Each code works once.</dd>
  <dt>Recovery code</dt><dd>Use one instead of the app code if you lose your phone. Each works once; you can make new ones here.</dd>
</dl>
<h3>Common mistakes</h3>
<ul>
  <li>The code is refused: check the phone's clock is set automatically, and that you read the code for this account.</li>
  <li>New phone: turn two-step sign-in off here with your old phone at hand, then set it up again on the new one.</li>
  <li>Lost both the phone and the recovery codes: the platform administrator can turn it off for you.</li>
</ul>
<h3>Next</h3>
<p><a href="<?= e(url('account/profile')) ?>">Back to your profile</a></p>