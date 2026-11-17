<?php
/**
 * Help Centre (BUILD_SPEC 10.3 item 6). Open to everyone; searchable on the page.
 * @var array $templates CSV templates (CsvImporter::TEMPLATES)
 * @var array $glossary  word => meaning
 */
$signedIn = isset($school) && $school;
$link = fn (string $route, string $label): string => $signedIn ? '<a href="' . e(url($route)) . '">' . e($label) . '</a>' : e($label);
?>
<h1>Help Centre</h1>
<?= $this->partial('partials/what_it_does') ?>
<p class="page-intro">Below is everything about using it, in plain words. Type to find a topic, or read <a href="<?= e(url('about')) ?>">About this app</a> for the full picture.</p>

<div class="help-search">
  <label for="help-q" class="visually-hidden">Search the help</label>
  <input type="search" id="help-q" placeholder="Search, for example: lab, clash, publish, invite" data-help-search>
</div>
<p data-help-none hidden class="panel empty-state">Nothing matches. Try a shorter word, or look through the Questions section below.</p>

<nav class="panel" aria-label="Topics">
  <ol class="help-toc">
    <li><a href="#start">Getting started: the 9 steps</a></li>
    <li><a href="#data">Preparing your data</a></li>
    <li><a href="#results">Understanding results</a></li>
    <li><a href="#publish">Publishing and sharing</a></li>
    <li><a href="#team">Inviting your team</a></li>
    <li><a href="#faq">Questions</a></li>
    <li><a href="#glossary">Words used in the system</a></li>
  </ol>
</nav>

<section class="panel help-section" id="start" aria-labelledby="start-title">
  <h2 id="start-title">Getting started: the 9 steps</h2>
  <p>After you create your school account, the <?= $link('setup', 'Setup Guide') ?> walks you through these steps. Each one turns
    green by itself when your data meets it.</p>
  <ol>
    <li data-search-item><strong>School profile and teaching week.</strong> Check your school name and the teaching day (for example 08:00 to 18:00 in
      one-hour periods, Monday to Friday) and press Save. Friday 12:00 to 14:00 starts blocked for Jumu'ah; click any period to block or open it.</li>
    <li data-search-item><strong>Add rooms.</strong> Every lecture room and laboratory the timetable may use, with its number of seats.
      A room smaller than a class is never given to it, and practicals only go into labs.</li>
    <li data-search-item><strong>Add levels.</strong> Each group of students who take classes together, such as 100L, with how many students it has.</li>
    <li data-search-item><strong>Add lecturers.</strong> The staff who teach, with a short name for timetable cells (for example A. Bello).</li>
    <li data-search-item><strong>Add courses.</strong> For each course: its level, its lecturer and its contact hours per week. Tick Practical for lab courses.</li>
    <li data-search-item><strong>Set lecturer availability</strong> (optional but recommended). Mark the periods each lecturer cannot teach, and the ones they prefer.
      You can skip this and come back later.</li>
    <li data-search-item><strong>Check readiness.</strong> One click finds anything that makes a clash-free timetable impossible, such as too few rooms,
      and tells you how to fix it.</li>
    <li data-search-item><strong>Generate your timetable.</strong> Press Start. The search runs on the server (usually one to two minutes); you can leave the page.</li>
    <li data-search-item><strong>Review and publish.</strong> Open the result, check it says "No clashes", look at it by level, lecturer and room, and press Publish.
      Then share the public link.</li>
  </ol>
</section>

<section class="panel help-section" id="data" aria-labelledby="data-title">
  <h2 id="data-title">Preparing your data</h2>
  <p data-search-item>You can type everything in, or import spreadsheets on the <?= $link('import', 'Import from spreadsheet') ?> page. In Excel, fill in
    a template and choose <strong>File, Save As, CSV UTF-8</strong>. Import levels, rooms and lecturers first, then courses and availability,
    because those refer to the others. If any row has a problem, nothing is saved and every problem is listed with its row number.</p>
  <div class="table-wrap">
    <table class="table table-stack">
      <thead><tr><th scope="col">File</th><th scope="col">Columns</th></tr></thead>
      <tbody>
        <?php foreach ($templates as $type => $template): ?>
          <tr data-search-item>
            <td data-label="File"><?= $signedIn ? '<a href="' . e(url('import/template', ['type' => $type])) . '">' . e($type) . '.csv</a>' : e($type) . '.csv' ?></td>
            <td data-label="Columns"><code><?= e(implode(',', $template['columns'])) ?></code></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <ul>
    <li data-search-item><strong>needs_lab</strong> and <strong>allow_two_hour_block</strong>: 1 for yes, 0 for no.</li>
    <li data-search-item><strong>class_size_override</strong>: leave blank to use the level's size. For a practical taught in lab groups, enter one lab group's size.</li>
    <li data-search-item><strong>room_type</strong>: lecture or lab. <strong>day</strong>: Mon to Fri. <strong>start_time</strong>: like 08:00.
      <strong>status</strong>: available, preferred or unavailable.</li>
    <li data-search-item><strong>How hours become classes:</strong> a 3-hour lecture becomes three 1-hour classes, spread over different days where possible;
      with "Allow one 2-hour block", one 2-hour class plus 1-hour classes. A practical becomes one lab block of up to 3 hours.</li>
  </ul>
</section>

<section class="panel help-section" id="results" aria-labelledby="results-title">
  <h2 id="results-title">Understanding results</h2>
  <p data-search-item>Every result starts with a sentence: <strong>"No clashes. 12 small preference issues."</strong> or
    <strong>"3 clashes remain. Here is what is causing them."</strong> A clash-free timetable is usable; the preference issues are wishes, not errors.</p>
  <h3>Hard rules (must)</h3>
  <ul>
    <li data-search-item>A lecturer is in only one place at a time.</li>
    <li data-search-item>A room holds one class at a time, and has enough seats for it.</li>
    <li data-search-item>A level has one class at a time.</li>
    <li data-search-item>Practicals are in labs.</li>
    <li data-search-item>No class in a blocked period, when its lecturer is unavailable, or running past the end of the day.</li>
  </ul>
  <h3>Soft rules (should)</h3>
  <ul>
    <li data-search-item>Lecturers teach in the periods they prefer; levels have no idle gaps between classes; a course's classes are on different days;
      no more than three hours of classes in a row; and the last period of the day is avoided.</li>
  </ul>
  <h3>The score</h3>
  <p data-search-item>Each broken rule adds its weight to the <strong>penalty</strong> (hard breaks add 1000 each by default, soft ones 2 to 5).
    Lower is better. <strong>Fitness</strong> is 1 divided by (1 + penalty), so a perfect timetable scores 1. Weights can be changed under Rules and weights.</p>
  <h3>How the genetic algorithm works</h3>
  <?= $this->partial('partials/ga_loop') ?>
  <p data-search-item>The system uses a <strong>genetic algorithm</strong>: it keeps about 100 candidate timetables, picks the better ones, mixes and
    changes them, and repeats this for many generations, the way breeding improves a crop. After every change a <strong>repair</strong> step moves
    each clashing class to the best free time and room, which removes clashes much faster than chance alone. The best timetables always survive,
    so the result never gets worse during a run.</p>
  <h3>Why a run may not reach zero clashes</h3>
  <ul>
    <li data-search-item>The data is very tight: for example, a lecturer is available for exactly as many periods as they teach, or the labs are almost full.
      Mark more availability, add a room, or unblock periods.</li>
    <li data-search-item>Two courses of the same level must both use the only lab that fits them. Set a smaller class size (lab groups) or add a lab.</li>
    <li data-search-item>Run again: each run starts from a different seed and may find a better timetable. Use the result with fewer clashes.</li>
  </ul>
</section>

<section class="panel help-section" id="publish" aria-labelledby="publish-title">
  <h2 id="publish-title">Publishing and sharing</h2>
  <ul>
    <li data-search-item>Open a finished run and press <strong>Publish</strong>. One timetable per semester is published; publishing another replaces it at once.</li>
    <li data-search-item>Your school has one public link, such as <code>/s/your-school</code>. Students and staff need no account: they choose their level,
      lecturer or room and see the week. The link stays the same when you publish a newer timetable.</li>
    <li data-search-item>Share the link on WhatsApp, notice boards or the school website. Anyone can print a timetable from the public page.</li>
    <li data-search-item>For paper copies, use <strong>Print view</strong> (one level per A4 page) or <strong>Export CSV</strong> for a spreadsheet.</li>
  </ul>
</section>

<section class="panel help-section" id="team" aria-labelledby="team-title">
  <h2 id="team-title">Inviting your team</h2>
  <p data-search-item>Owners and administrators invite people under <?= $link('members', 'Members and invitations') ?>. The invitation email has a link
    that works for 7 days; someone without an account chooses a password when they accept. When email is not set up, or the person is with
    you, use "Add someone without email": you choose their role and a temporary password, and they must choose their own at the first sign-in.</p>
  <div class="table-wrap">
    <table class="table table-stack">
      <thead><tr><th scope="col">Role</th><th scope="col">Can do</th></tr></thead>
      <tbody>
        <tr data-search-item><td data-label="Role">Owner</td><td data-label="Can do">Everything, including handing the school to someone else and deleting it.</td></tr>
        <tr data-search-item><td data-label="Role">Administrator</td><td data-label="Can do">Everything except deleting the school and handing it over: members, semesters, rules, settings.</td></tr>
        <tr data-search-item><td data-label="Role">Timetable officer</td><td data-label="Can do">Rooms, levels, lecturers, courses, availability, imports, readiness, runs and publishing.</td></tr>
        <tr data-search-item><td data-label="Role">Viewer</td><td data-label="Can do">Looks at timetables and reports only. Good for heads of department and deans.</td></tr>
      </tbody>
    </table>
  </div>
  <p data-search-item>One person can belong to several schools and switch between them from the school name in the top bar.</p>
</section>

<section class="panel help-section" id="faq" aria-labelledby="faq-title">
  <h2 id="faq-title">Questions</h2>
  <dl class="faq">
    <div data-search-item><dt>Do I need to build class sessions?</dt>
      <dd>No. The classes are worked out from your courses automatically whenever you open Check readiness or Generate.</dd></div>
    <div data-search-item><dt>Check readiness passed but the run still has clashes. Why?</dt>
      <dd>Passing means a clash-free timetable is possible on paper, not that it exists. See "Why a run may not reach zero clashes" above.</dd></div>
    <div data-search-item><dt>A run stays "Waiting to start".</dt>
      <dd>Runs from all schools take turns. If yours does not start within a few minutes, the background worker may not be running; ask the platform administrator to check the Health page.</dd></div>
    <div data-search-item><dt>Can I compare our old hand-made timetable?</dt>
      <dd>Yes. Under Runs and results, use Import a manual timetable. It is scored with exactly the same rules, so you can compare it with generated ones.</dd></div>
    <div data-search-item><dt>Will changing a course change the published timetable?</dt>
      <dd>No. Saved and published timetables keep their own copy. Generate again and publish the new run when you are ready.</dd></div>
    <div data-search-item><dt>How do I start a new semester?</dt>
      <dd>An administrator creates it under Manage, Semesters, sets it active and can copy last semester's courses into it.</dd></div>
    <div data-search-item><dt>I forgot my password.</dt>
      <dd>On the sign-in page, choose "Forgot your password?" and we email you a link that works for 1 hour.</dd></div>
    <div data-search-item><dt>How long do I stay signed in?</dt>
      <dd>60 minutes without activity, unless you tick "Keep me signed in" when you sign in: then up to 30 days on that browser, or until you
        press Sign out. Do not tick it on a shared computer. Under Profile you can sign out on all browsers at once.</dd></div>
    <div data-search-item><dt>What is two-step sign-in?</dt>
      <dd>An extra check after your password: a 6-digit code from an authenticator app on your phone. It is optional but encouraged, especially for owners
        and administrators. Turn it on under Profile, Two-step sign-in. Keep the recovery codes you are given; each lets you sign in once without your phone.</dd></div>
    <div data-search-item><dt>I lost my phone and my recovery codes.</dt>
      <dd>Ask the platform administrator to turn two-step sign-in off for your account. Then sign in with your password and set it up again.</dd></div>
    <div data-search-item><dt>Can other schools see our data?</dt>
      <dd>No. Each school's data is separate. Only members you invite can open your workspace; everyone else sees only what you publish.</dd></div>
    <div data-search-item><dt>How do I switch between light and dark?</dt>
      <dd>Use the theme button in the top bar: System follows your device, or choose Light or Dark. Public pages have the same button.</dd></div>
  </dl>
</section>

<section class="panel help-section" id="glossary" aria-labelledby="glossary-title">
  <h2 id="glossary-title">Words used in the system</h2>
  <dl class="settings-list">
    <?php foreach ($glossary as $word => $meaning): ?>
      <div data-search-item><dt><?= e(ucfirst($word)) ?></dt><dd><?= e($meaning) ?></dd></div>
    <?php endforeach; ?>
  </dl>
</section>

<section class="panel help-section" id="about" aria-labelledby="about-title">
  <h2 id="about-title">About this system</h2>
  <p data-search-item>The timetabling problem is modelled as a constraint satisfaction problem and solved by a genetic algorithm with a targeted
    repair operator. The system is the software artefact of an M.Sc. dissertation in Information Systems and Technology (Faculty of Computing,
    National Open University of Nigeria), which compares the search with and without repair on real department data.</p>
</section>
