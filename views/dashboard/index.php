<?php
/**
 * Dashboard.
 * @var array<string,array{0:int,1:string}> $counts label => [count, route]
 * @var string[] $warnings
 * @var array|null $feasibility
 * @var array|null $lastJob
 * @var array|null $published
 * @var string $publicLink
 * @var array $guide done, total, complete, next
 */
$statusText = ['finished' => 'Finished', 'failed' => 'Failed', 'stopped' => 'Stopped', 'running' => 'Running', 'queued' => 'Waiting to start'];
?>
<h1>Dashboard</h1>
<?= $this->partial('partials/what_it_does') ?>
<p class="page-intro">Below: how far <?= e($school->name()) ?> has got, and what needs attention.</p>

<?php if (!$guide['complete']): ?>
  <section class="panel panel-success" aria-labelledby="guide-title">
    <h2 id="guide-title">Setup Guide: <?= e($guide['done']) ?> of <?= e($guide['total']) ?> steps done</h2>
    <progress class="progress-line" max="<?= e($guide['total']) ?>" value="<?= e($guide['done']) ?>" aria-label="<?= e($guide['done']) ?> of <?= e($guide['total']) ?> steps done"></progress>
    <?php if ($guide['next']): ?>
      <p>Next: <strong><?= e($guide['next']['title']) ?></strong>. <?= e($guide['next']['why']) ?></p>
    <?php endif; ?>
    <div class="button-row">
      <?php if ($guide['next'] && $school->can('officer')): ?>
        <a class="btn btn-primary" href="<?= e(url($guide['next']['route'])) ?>"><?= e($guide['next']['button']) ?></a>
      <?php endif; ?>
      <a class="btn" href="<?= e(url('setup')) ?>">Open the Setup Guide</a>
    </div>
  </section>
<?php endif; ?>

<?php if ($warnings): ?>
  <section aria-labelledby="warnings-title" class="panel panel-warning">
    <h2 id="warnings-title">Needs your attention</h2>
    <ul>
      <?php foreach ($warnings as $warning): ?>
        <li><?= e($warning) ?></li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<section aria-labelledby="counts-title">
  <h2 id="counts-title" class="visually-hidden">Data summary</h2>
  <div class="stat-grid">
    <?php foreach ($counts as $label => [$value, $route]): ?>
      <?php if ($school->can('officer')): ?>
        <a class="stat" href="<?= e(url($route)) ?>"><span class="stat-value"><?= e($value) ?></span><span class="stat-label"><?= e($label) ?></span></a>
      <?php else: ?>
        <div class="stat"><span class="stat-value"><?= e($value) ?></span><span class="stat-label"><?= e($label) ?></span></div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</section>

<div class="two-col">
  <section class="panel" aria-labelledby="feas-title">
    <h2 id="feas-title">Readiness</h2>
    <?php if (!$feasibility): ?>
      <p>Not checked yet for this semester.</p>
    <?php elseif ($feasibility['ok'] && $feasibility['current']): ?>
      <p><span class="tag tag-success">Ready</span> Checked <?= e(substr((string) $feasibility['checked_at'], 0, 16)) ?>.</p>
    <?php elseif ($feasibility['ok']): ?>
      <p><span class="tag tag-warning">Check again</span> It passed on <?= e(substr((string) $feasibility['checked_at'], 0, 16)) ?>, but your data has changed since.</p>
    <?php else: ?>
      <p><span class="tag tag-danger">Not ready</span> <?= e(count($feasibility['failures'])) ?> problem(s) found on <?= e(substr((string) $feasibility['checked_at'], 0, 16)) ?>.</p>
    <?php endif; ?>
    <?php if ($school->can('officer')): ?><a href="<?= e(url('feasibility')) ?>">Open Check readiness</a><?php endif; ?>
  </section>

  <section class="panel" aria-labelledby="job-title">
    <h2 id="job-title">Latest run</h2>
    <?php if (!$lastJob): ?>
      <p>No timetable has been generated for this semester yet.</p>
      <?php if ($school->can('officer')): ?><a href="<?= e(url('generate')) ?>">Generate a timetable</a><?php endif; ?>
    <?php else: ?>
      <p>Run #<?= e($lastJob['id']) ?><?= $lastJob['label'] ? ' (' . e($lastJob['label']) . ')' : '' ?>:
        <strong><?= e($statusText[$lastJob['status']] ?? $lastJob['status']) ?></strong>.
        <?php if ($lastJob['hard_violations'] !== null): ?>
          <?= (int) $lastJob['hard_violations'] === 0 ? 'No clashes.' : e($lastJob['hard_violations']) . ' clash(es).' ?>
        <?php endif; ?>
      </p>
      <?php if (in_array($lastJob['status'], ['finished', 'stopped'], true)): ?>
        <a href="<?= e(url('timetables/view', ['id' => $lastJob['id']])) ?>">Open the timetable</a>
      <?php else: ?>
        <a href="<?= e(url('generate/job', ['id' => $lastJob['id']])) ?>">View progress</a>
      <?php endif; ?>
    <?php endif; ?>
  </section>

  <section class="panel" aria-labelledby="pub-title">
    <h2 id="pub-title">Published timetable</h2>
    <?php if ($published): ?>
      <p><span class="tag tag-success">Published</span> Run #<?= e($published['id']) ?> is what students and staff see.</p>
    <?php else: ?>
      <p>Nothing is published yet, so the public page says the timetable is not available.</p>
    <?php endif; ?>
    <p class="small">Public link: <a href="<?= e($publicLink) ?>"><?= e($publicLink) ?></a></p>
  </section>
</div>
