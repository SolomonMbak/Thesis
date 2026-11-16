<?php
/**
 * About this app: what it does, how it works, who uses it, and its background.
 */
?>
<h1>About this app</h1>
<?= $this->partial('partials/what_it_does', ['size' => 'full']) ?>

<section class="panel" aria-labelledby="steps-title">
  <h2 id="steps-title">How you use it, step by step</h2>
  <ol>
    <li><strong>Set up your school:</strong> the teaching week (for example Monday to Friday, 08:00 to 18:00 in one-hour periods) and any blocked periods.</li>
    <li><strong>Add rooms, levels and lecturers</strong>, by typing them in or importing a spreadsheet.</li>
    <li><strong>Add courses:</strong> each with its level, its lecturer and its contact hours per week.</li>
    <li><strong>Mark lecturer availability</strong> (optional): times they cannot teach and times they prefer.</li>
    <li><strong>Check readiness:</strong> one click finds problems that make a clash-free timetable impossible, such as too few rooms, and says how to fix them.</li>
    <li><strong>Generate:</strong> press Start; the app builds the timetable in a minute or two.</li>
    <li><strong>Review and publish:</strong> read the result, look at each level, lecturer and room, and publish it. Share the link.</li>
  </ol>
  <p>The <strong>Setup Guide</strong> inside the app shows these steps with a tick beside each one you have finished.</p>
</section>

<section class="panel" aria-labelledby="ga-title">
  <h2 id="ga-title">The engine: a genetic algorithm with targeted repair</h2>
  <?= $this->partial('partials/ga_loop') ?>
</section>

<section class="panel" aria-labelledby="how-title">
  <h2 id="how-title">Why a genetic algorithm, and why repair</h2>
  <p>A department with 40 courses and about 80 weekly classes, 50 periods and 8 rooms has more possible timetables than there are atoms in the
    universe, so they cannot be tried one by one. A genetic algorithm does not need to try them all: by breeding from the best timetables it
    quickly concentrates on good ones. Its weakness is clashes: chance alone removes the last few slowly. The <strong>targeted repair</strong>
    step fixes exactly that, by moving each clashing class to the best free time and room after every change. The dissertation compares the
    genetic algorithm with and without repair on the same data, the same random seeds and the same rules.</p>
  <p>Every run has a <strong>seed</strong>, the number that fixes its random choices: the same seed, data and settings always give the same
    timetable, so every result can be repeated and checked.</p>
</section>

<section class="panel" aria-labelledby="who-title">
  <h2 id="who-title">Who does what</h2>
  <ul>
    <li><strong>Owner and administrators</strong> set up the school, invite colleagues and manage semesters and rules.</li>
    <li><strong>Timetable officers</strong> enter the data, generate, review and publish timetables.</li>
    <li><strong>Viewers</strong> (for example a dean) can look at timetables and reports but change nothing.</li>
    <li><strong>Students and staff</strong> open the public link; no account is needed.</li>
  </ul>
</section>

<section class="panel" aria-labelledby="bg-title">
  <h2 id="bg-title">Background</h2>
  <p>The app was built as the software part of an M.Sc. dissertation in Information Systems and Technology (Faculty of Computing,
    National Open University of Nigeria). The study models timetabling rules as a constraint satisfaction problem and tests whether the
    repair step makes the genetic algorithm find clash-free timetables more reliably than the standard method, using real department data and
    the department's existing hand-made timetable for comparison.</p>
</section>

<p><a class="btn btn-primary" href="<?= e(url('help')) ?>">Open the Help Centre</a></p>
