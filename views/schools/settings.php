<?php
/**
 * School settings: public link, default search settings, old runs, owner actions.
 * @var array $ga
 * @var string $slug
 * @var array $errors
 * @var bool $isOwner
 * @var array $transferTo members who could become owner
 * @var array $jobCounts
 */
$statusText = ['finished' => 'finished', 'failed' => 'failed', 'stopped' => 'stopped', 'running' => 'running', 'queued' => 'waiting'];
?>
<h1>School settings</h1>
<p class="page-intro">The public link, the default settings of the timetable search, clearing out old runs and, for the owner, handing over or deleting the school.</p>

<section class="panel" aria-labelledby="slug-title">
  <h2 id="slug-title">Public link</h2>
  <p>Students and staff open the published timetable at <strong><?= e(absolute_url(public_url($school->slug()))) ?></strong>.</p>
  <form method="post" action="<?= e(url('school/slug')) ?>" class="toolbar" novalidate>
    <?= $csrf->field() ?>
    <?= $this->partial('partials/field', ['name' => 'slug', 'label' => 'Link name', 'value' => $slug, 'errors' => $errors, 'required' => true,
        'help' => 'Small letters, numbers and dashes, for example noun-computer-science. Changing it breaks links already shared.']) ?>
    <button type="submit" class="btn" data-confirm="Change the public link? Links you have already shared will stop working.">Change link</button>
  </form>
</section>

<section class="panel" aria-labelledby="ga-title">
  <h2 id="ga-title">Default search settings</h2>
  <p class="muted">These fill in the Generate page for every new run. The usual values suit a department of about 40 courses;
    you rarely need to change them. Each run can still change them for itself.</p>
  <form method="post" action="<?= e(url('school/ga')) ?>" novalidate>
    <?= $csrf->field() ?>
    <?= $this->partial('partials/ga_fields', ['values' => $ga, 'errors' => $errors]) ?>
    <button type="submit" class="btn btn-primary">Save default settings</button>
  </form>
</section>

<section class="panel" aria-labelledby="cleanup-title">
  <h2 id="cleanup-title">Delete old runs</h2>
  <p>Runs by status: <?= e($jobCounts ? implode(', ', array_map(fn ($s, $n) => "{$n} " . ($statusText[$s] ?? $s), array_keys($jobCounts), $jobCounts)) : 'none yet') ?>.
    Published runs, and runs that are waiting or running, are never deleted.</p>
  <form method="post" action="<?= e(url('school/delete-old-jobs')) ?>" class="toolbar"
        data-confirm="Delete old runs with their progress and timetables? This cannot be undone.">
    <?= $csrf->field() ?>
    <div class="field">
      <label for="days">Older than (days)</label>
      <input type="number" id="days" name="days" min="1" max="3650" value="90" class="input-narrow">
    </div>
    <div class="field field-checkbox">
      <input type="hidden" name="include_manual" value="0">
      <label for="include_manual"><input type="checkbox" id="include_manual" name="include_manual" value="1"> Include imported manual timetables</label>
    </div>
    <button type="submit" class="btn btn-danger-outline">Delete old runs</button>
  </form>
</section>

<?php if ($isOwner): ?>
  <section class="panel" aria-labelledby="transfer-title">
    <h2 id="transfer-title">Hand the school to someone else</h2>
    <?php if (!$transferTo): ?>
      <p class="muted">Invite another member first (Members and invitations). The new owner must already belong to the school.</p>
    <?php else: ?>
      <p>The member you choose becomes the owner. You stay in the school as an administrator.</p>
      <form method="post" action="<?= e(url('school/transfer')) ?>" class="toolbar"
            data-confirm="Make this member the owner of the school? Only the new owner can undo this.">
        <?= $csrf->field() ?>
        <div class="field">
          <label for="user_id">New owner</label>
          <select id="user_id" name="user_id">
            <?php foreach ($transferTo as $m): ?>
              <option value="<?= e($m['id']) ?>"><?= e($m['full_name'] . ' (' . $m['email'] . ')') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="transfer_password">Your password</label>
          <input type="password" id="transfer_password" name="current_password" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn">Hand over</button>
      </form>
    <?php endif; ?>
  </section>

  <section class="panel panel-danger" aria-labelledby="delete-title">
    <h2 id="delete-title">Delete this school</h2>
    <p>This deletes <?= e($school->name()) ?> and everything in it: rooms, levels, lecturers, courses, every run and the public page.
      Members keep their accounts. It cannot be undone, so download what you need first.</p>
    <form method="post" action="<?= e(url('school/delete')) ?>" class="toolbar"
          data-confirm="Delete the school and all of its data for ever?">
      <?= $csrf->field() ?>
      <div class="field">
        <label for="confirm_slug">Type <strong><?= e($school->slug()) ?></strong> to confirm</label>
        <input type="text" id="confirm_slug" name="confirm_slug" autocomplete="off">
      </div>
      <div class="field">
        <label for="delete_password">Your password</label>
        <input type="password" id="delete_password" name="current_password" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn btn-danger">Delete the school</button>
    </form>
  </section>
<?php endif; ?>
