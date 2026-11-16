<?php
/**
 * Setup Guide (BUILD_SPEC 10.2): numbered steps with live status.
 * @var array $steps
 * @var int $done
 * @var bool $complete
 * @var array|null $next
 * @var string $publicLink
 * @var bool $canAct
 */
$labels = ['done' => 'Done', 'todo' => 'To do', 'optional' => 'Optional', 'locked' => 'Locked'];
$tags = ['done' => 'tag-success', 'todo' => 'tag-info', 'optional' => 'tag-warning', 'locked' => ''];
?>
<div class="page-head">
  <h1>Setup Guide</h1>
  <?php if ($next && $canAct): ?>
    <a class="btn btn-primary" href="<?= e(url($next['route'])) ?>">Next: <?= e($next['title']) ?></a>
  <?php endif; ?>
</div>
<?= $this->partial('partials/what_it_does') ?>
<p class="page-intro">Nine steps take <?= e($school->name()) ?> from an empty workspace to a timetable students can open on their phones.
  Each step turns <strong>Done</strong> by itself when your data meets it, so this list is always up to date.</p>

<progress class="progress-line" max="<?= e(count($steps)) ?>" value="<?= e($done) ?>" aria-label="<?= e($done) ?> of <?= e(count($steps)) ?> steps done"></progress>
<p class="small muted"><?= e($done) ?> of <?= e(count($steps)) ?> steps done.</p>

<?php if ($complete): ?>
  <section class="panel panel-success" aria-labelledby="done-title">
    <h2 id="done-title">Your timetable is published</h2>
    <p>Setup is complete. Students and staff can open the timetable at this link, on any phone or computer, without signing in:</p>
    <div class="copy-row">
      <label for="share-link" class="visually-hidden">Public link</label>
      <input type="text" id="share-link" value="<?= e($publicLink) ?>" readonly>
      <button type="button" class="btn" data-copy="share-link"><?= icon('copy') ?> Copy link</button>
      <a class="btn" href="<?= e($publicLink) ?>">Open it</a>
    </div>
    <p class="small">The guide stays here for reference. Next semester, create the semester under Manage, Semesters and work through steps 5 to 9 again.</p>
  </section>
<?php endif; ?>

<ol class="steps-list">
  <?php foreach ($steps as $step): ?>
    <li class="step step-<?= e($step['status']) ?>">
      <span class="step-no" aria-hidden="true"><?= $step['done'] ? '&#10003;' : e($step['no']) ?></span>
      <div>
        <h2><span class="visually-hidden">Step <?= e($step['no']) ?>: </span><?= e($step['title']) ?></h2>
        <p><?= e($step['why']) ?></p>
        <?php if ($step['count']): ?><p class="small muted"><?= e($step['count']) ?></p><?php endif; ?>
        <?php if ($step['locked_reason']): ?><p class="small muted"><?= e($step['locked_reason']) ?></p><?php endif; ?>
      </div>
      <div class="step-action">
        <span class="tag <?= e($tags[$step['status']]) ?>"><?= e($labels[$step['status']]) ?></span>
        <?php if ($canAct && $step['status'] !== 'locked'): ?>
          <a class="btn btn-small<?= !$step['done'] && $next && $next['no'] === $step['no'] ? ' btn-primary' : '' ?>" href="<?= e(url($step['route'])) ?>">
            <?= e($step['done'] ? 'Review' : $step['button']) ?>
          </a>
          <?php if ($step['no'] === 6 && !$step['done']): ?>
            <form method="post" action="<?= e(url('availability/skip')) ?>" class="inline-form">
              <?= $csrf->field() ?>
              <button type="submit" class="btn-link small">Skip for now</button>
            </form>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </li>
  <?php endforeach; ?>
</ol>
<?php if (!$canAct): ?>
  <p class="muted">You have read-only access, so the steps are done by the school's officers and administrators.</p>
<?php endif; ?>
