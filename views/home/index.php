<?php
/**
 * Home page for visitors: what the app does, at first glance.
 * @var bool $signupOpen
 * @var bool $signedIn
 */
?>
<section class="hero">
  <h1>Your school's class timetable, made for you</h1>
  <?php if (!$signedIn): ?>
    <div class="button-row hero-actions">
      <?php if ($signupOpen): ?><a class="btn btn-primary" href="<?= e(url('auth/signup')) ?>">Create a school account (free)</a><?php endif; ?>
      <a class="btn" href="<?= e(url('auth/login')) ?>">Sign in</a>
    </div>
  <?php endif; ?>
  <?= $this->partial('partials/what_it_does', ['size' => 'full']) ?>
  <div class="button-row">
    <?php if ($signupOpen && !$signedIn): ?>
      <a class="btn btn-primary" href="<?= e(url('auth/signup')) ?>">Create a school account (free)</a>
    <?php endif; ?>
    <a class="btn<?= $signupOpen && !$signedIn ? '' : ' btn-primary' ?>" href="<?= e(url($signedIn ? 'schools/new' : 'auth/login')) ?>"><?= $signedIn ? 'Create your school' : 'Sign in' ?></a>
    <a class="btn" href="<?= e(url('about')) ?>">More about the app</a>
  </div>
</section>

<section class="panel" aria-labelledby="ga-title">
  <h2 id="ga-title">The engine: a genetic algorithm with targeted repair</h2>
  <?= $this->partial('partials/ga_loop') ?>
  <p><a href="<?= e(url('about')) ?>#ga-title">Read more about how it works</a></p>
</section>

<section class="panel" aria-labelledby="who-title">
  <h2 id="who-title">Who it is for</h2>
  <ul>
    <li><strong>Timetable officers and heads of department</strong> at universities, polytechnics, colleges and secondary schools,
      who today make the timetable by hand in Excel or on paper.</li>
    <li><strong>Students and lecturers</strong>, who simply open the school's link to see their own week. They need no account.</li>
  </ul>
  <p>Each school gets its own private workspace: nobody else can see its data. A Setup Guide walks you through the nine steps from an empty
    school to a published timetable, and every page has a Help button.</p>
</section>

<section class="panel" aria-labelledby="students-title">
  <h2 id="students-title">Looking for your timetable?</h2>
  <p>Ask your school for its timetable link. It looks like <code><?= e(absolute_url(public_url('your-school'))) ?></code>.</p>
</section>
