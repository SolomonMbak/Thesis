<?php
/**
 * The one description of what the app does, shown on the home page, About,
 * sign-in, Help Centre, dashboard and Setup Guide, so the wording is the
 * same everywhere.
 * Optional: $size 'short' (one paragraph) or 'full' (paragraph, the three
 * things it does, and the rules it guarantees).
 */
$size = $size ?? 'short';
?>
<?php if ($size === 'short'): ?>
  <p class="what-it-does"><strong>This app makes your school's weekly class timetable for you.</strong>
    You enter your rooms, levels, lecturers and courses. A <strong>genetic algorithm</strong> then works out when and where every class takes place, so that no
    lecturer, room or group of students is ever booked twice at the same time, and you publish the result for students and staff to see on their phones.</p>
<?php else: ?>
  <p class="lead"><strong>This app makes your school's weekly class timetable for you.</strong>
    Instead of fitting hundreds of classes into a week by hand, you enter your rooms, levels (groups of students), lecturers and courses.
    The app's engine, a <strong>genetic algorithm with targeted repair</strong>, then works out the day, the time and the room of every class;
    the app checks the result and lets you publish it on a link that students and staff open on their phones.</p>

  <div class="feature-grid">
    <section class="feature">
      <h3><span class="feature-no">1</span> You enter your school's data</h3>
      <p>Rooms and labs with their seats, levels with their number of students, lecturers and when they can teach, and each course with its
        level, lecturer and weekly hours. Type it in or import it from Excel.</p>
    </section>
    <section class="feature">
      <h3><span class="feature-no">2</span> A genetic algorithm builds it</h3>
      <p>It breeds a population of 100 candidate timetables over hundreds of generations, repairing clashes as it goes, and keeps the best
        one. It takes one to two minutes on the server, and you can watch its progress live.</p>
    </section>
    <section class="feature">
      <h3><span class="feature-no">3</span> You check it and publish it</h3>
      <p>The result starts with a plain sentence such as "No clashes". You look at it by level, lecturer or room, then publish it. Everyone
        sees it at your school's link, and it prints on A4.</p>
    </section>
  </div>

  <h3>What the timetable always gets right</h3>
  <ul class="check-list">
    <li>No lecturer is in two places at once.</li>
    <li>No room has two classes at once, and every class fits its room.</li>
    <li>No level has two classes at once.</li>
    <li>Practicals are always in a laboratory.</li>
    <li>No class is put in a blocked period (for example Friday prayers) or when its lecturer cannot teach.</li>
  </ul>
  <h3>What it tries to make better</h3>
  <ul class="check-list check-list-soft">
    <li>Lecturers teach at the times they prefer.</li>
    <li>Students have no empty gaps between classes and no more than three hours in a row.</li>
    <li>A course's classes are on different days, and late classes are avoided.</li>
  </ul>
<?php endif; ?>
