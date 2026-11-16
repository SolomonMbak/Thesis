<?php
/**
 * The genetic algorithm (GA), the engine of the app, as a picture of its
 * loop with one plain sentence per step. Used on the home page, About,
 * Generate and the Help Centre.
 * Optional: $population, $stall, $maxGenerations, $tournament, $elite (the school's real settings; the defaults
 * otherwise), $compact (no intro)
 */
$population = $population ?? 100;
$stall = $stall ?? 100;
$maxGenerations = $maxGenerations ?? 2000;
$tournament = $tournament ?? 3;
$elite = $elite ?? 2;
$steps = [
    ['Start', "Make {$population} different timetables. Most are random; some are built by placing each class in the first free slot."],
    ['Score', 'Give every timetable a penalty: 1000 points per clash plus a few points per broken wish. Lower is better.'],
    ['Choose parents', "Pick timetables to breed from, favouring the better ones (a tournament: the best of {$tournament} picked at random wins)."],
    ['Mix', 'Combine two parents into two children: each class takes its time and room from one parent or the other.'],
    ['Mutate', 'Move a few classes at random to a new time or room, so the search keeps finding new ideas.'],
    ['Repair', 'Find every class in a clash and move it to the best free time and room. This step is what makes this GA fast.'],
    ['Keep the best', "The {$elite} best timetables pass unchanged into the next round, so the best result never gets worse."],
];
?>
<?php if (empty($compact)): ?>
  <p>The timetable is built by a <strong>genetic algorithm (GA)</strong>, a search method that copies how living things improve over
    generations. It keeps a whole population of candidate timetables and, round after round, breeds better ones from the best of them.
    One round is called a <strong>generation</strong>.</p>
<?php endif; ?>
<ol class="ga-loop" aria-label="One generation of the genetic algorithm">
  <?php foreach ($steps as $i => [$name, $text]): ?>
    <li class="ga-step<?= $name === 'Repair' ? ' ga-step-key' : '' ?>">
      <span class="ga-step-no" aria-hidden="true"><?= $i + 1 ?></span>
      <strong><?= e($name) ?></strong>
      <span><?= e($text) ?></span>
    </li>
  <?php endforeach; ?>
</ol>
<p class="ga-repeat"><span aria-hidden="true">&#8635;</span> Steps 2 to 7 repeat, one generation after another. The search stops when the best
  timetable has no clashes and has not improved for <?= e($stall) ?> generations, after at most <?= e($maxGenerations) ?> generations, or when
  you press Stop. The best timetable found is saved.</p>
