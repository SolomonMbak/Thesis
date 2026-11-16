<?php
/**
 * Published timetable and share link (Setup Guide step 9).
 * @var array|null $semester
 * @var array|null $job
 * @var string $publicLink
 * @var bool $canPublish
 */
use App\Data\SemesterRepository;
?>
<h1>Published timetable and share link</h1>
<p class="page-intro">The published timetable is the one students and staff see. They need no account: they open the link,
  choose their level, lecturer or room, and can print it.</p>

<section class="panel" aria-labelledby="link-title">
  <h2 id="link-title">Your public link</h2>
  <p>This link always shows the latest published timetable of the active semester, so you can share it once
    (WhatsApp, notice boards, the school website) and keep it.</p>
  <div class="copy-row">
    <label for="share-link" class="visually-hidden">Public link</label>
    <input type="text" id="share-link" value="<?= e($publicLink) ?>" readonly>
    <button type="button" class="btn" data-copy="share-link"><?= icon('copy') ?> Copy link</button>
    <a class="btn" href="<?= e($publicLink) ?>">Open the public page</a>
  </div>
  <?php if ($school->can('admin')): ?>
    <p class="small muted">You can change the link name under <a href="<?= e(url('school/settings')) ?>">School settings</a>.</p>
  <?php endif; ?>
</section>

<?php if (!$semester): ?>
  <div class="panel empty-state"><p>There is no active semester, so nothing can be published.</p></div>
<?php elseif (!$job): ?>
  <div class="panel empty-state">
    <p>Nothing is published for <?= e(SemesterRepository::label($semester)) ?> yet, so the public page says the timetable is not available.
      Open a finished run with no clashes and press <strong>Publish</strong>.</p>
    <div class="button-row"><a class="btn btn-primary" href="<?= e(url('timetables')) ?>">Choose a run to publish</a></div>
  </div>
<?php else: ?>
  <section class="panel panel-success" aria-labelledby="pub-title">
    <h2 id="pub-title">Run #<?= e($job['id']) ?><?= $job['label'] ? ': ' . e($job['label']) : '' ?> is published</h2>
    <p><?= (int) $job['hard_violations'] === 0 ? 'It has no clashes.' : 'Careful: it has ' . e($job['hard_violations']) . ' clash(es).' ?>
      Publishing another run replaces it straight away; the link stays the same.</p>
    <div class="button-row">
      <a class="btn" href="<?= e(url('timetables/view', ['id' => $job['id']])) ?>">Open it</a>
      <a class="btn" href="<?= e(url('timetables/print', ['id' => $job['id']])) ?>"><?= icon('print') ?> Print all levels</a>
      <?php if ($canPublish): ?>
        <form method="post" action="<?= e(url('timetables/unpublish')) ?>" class="inline-form" data-confirm="Take this timetable off the public page?">
          <?= $csrf->field() ?><input type="hidden" name="id" value="<?= e($job['id']) ?>">
          <button type="submit" class="btn btn-danger-outline">Unpublish</button>
        </form>
      <?php endif; ?>
    </div>
  </section>
<?php endif; ?>
