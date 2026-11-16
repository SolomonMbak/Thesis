<?php
/**
 * School profile and teaching week (Setup Guide step 1).
 * @var array $values
 * @var array $errors
 * @var array $labels
 * @var array $types
 * @var bool $canEdit
 * @var array $grid
 * @var bool $profileSaved
 * @var string|null $logoUrl
 */
use App\Services\PeriodGenerator;

$disabled = $canEdit ? '' : 'disabled';
?>
<h1>School profile and periods</h1>
<p class="page-intro">Your school's name and the shape of the teaching week. Every timetable uses these periods; blocked periods are never used.</p>

<?php if (!$profileSaved && $canEdit): ?>
  <div class="alert alert-info" role="status">Check the details below and press <strong>Save profile</strong> once, even if nothing needs changing.
    That completes step 1 of the Setup Guide.</div>
<?php endif; ?>
<?php if (!$canEdit): ?>
  <div class="alert alert-info" role="status">Only owners and administrators can change the profile. You can still block and unblock periods below.</div>
<?php endif; ?>
<?php if ($errors): ?>
  <div class="alert alert-error" role="alert">Nothing was saved. Please correct the fields marked below.</div>
<?php endif; ?>

<form method="post" action="<?= e(url('school/profile')) ?>" class="panel" novalidate>
  <?= $csrf->field() ?>
  <h2>School</h2>
  <div class="form-grid">
    <?= $this->partial('partials/field', ['name' => 'name', 'label' => $labels['name'], 'value' => $values['name'], 'errors' => $errors, 'required' => true, 'attrs' => $disabled,
        'help' => 'Shown in the top bar and on the public timetable.']) ?>
    <?= $this->partial('partials/field', ['name' => 'department_label', 'label' => $labels['department_label'], 'value' => $values['department_label'], 'errors' => $errors, 'attrs' => $disabled,
        'help' => 'For a workspace that is one department, for example Department of Computer Science.']) ?>
    <?= $this->partial('partials/field', ['name' => 'school_type', 'label' => $labels['school_type'], 'type' => 'select', 'value' => $values['school_type'],
        'options' => $types, 'errors' => $errors, 'attrs' => $disabled]) ?>
    <?= $this->partial('partials/field', ['name' => 'country', 'label' => $labels['country'], 'value' => $values['country'], 'errors' => $errors, 'required' => true, 'attrs' => $disabled]) ?>
    <?= $this->partial('partials/field', ['name' => 'timezone', 'label' => $labels['timezone'], 'value' => $values['timezone'], 'errors' => $errors, 'required' => true, 'attrs' => $disabled,
        'help' => 'For example Africa/Lagos.']) ?>
  </div>
  <h2>Teaching day</h2>
  <div class="form-grid">
    <?= $this->partial('partials/field', ['name' => 'day_start', 'label' => $labels['day_start'], 'type' => 'time', 'value' => $values['day_start'], 'errors' => $errors, 'required' => true, 'attrs' => $disabled,
        'help' => 'When the first class may start, for example 08:00.']) ?>
    <?= $this->partial('partials/field', ['name' => 'day_end', 'label' => $labels['day_end'], 'type' => 'time', 'value' => $values['day_end'], 'errors' => $errors, 'required' => true, 'attrs' => $disabled,
        'help' => 'When the last class must end, for example 18:00.']) ?>
    <?= $this->partial('partials/field', ['name' => 'period_minutes', 'label' => $labels['period_minutes'], 'type' => 'number', 'value' => $values['period_minutes'], 'errors' => $errors, 'required' => true, 'attrs' => $disabled,
        'min' => 15, 'max' => 240, 'help' => 'Usually 60. Changing the day rebuilds the week; blocked periods stay blocked where they still exist.']) ?>
  </div>
  <?php if ($canEdit): ?><button type="submit" class="btn btn-primary">Save profile</button><?php endif; ?>
</form>

<?php if ($canEdit): ?>
  <section class="panel" aria-labelledby="logo-title">
    <h2 id="logo-title">Logo (optional)</h2>
    <?php if ($logoUrl): ?>
      <p><img src="<?= e($logoUrl) ?>" alt="Current logo" class="school-logo"> Current logo</p>
    <?php endif; ?>
    <form method="post" action="<?= e(url('school/logo-upload')) ?>" enctype="multipart/form-data" class="toolbar">
      <?= $csrf->field() ?>
      <div class="field">
        <label for="logo">Image file</label>
        <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/gif,image/webp" aria-describedby="logo-help">
        <p class="field-help" id="logo-help">PNG, JPEG, GIF or WebP, smaller than 512 KB. A square image works best.</p>
      </div>
      <button type="submit" class="btn">Upload logo</button>
    </form>
    <?php if ($logoUrl): ?>
      <form method="post" action="<?= e(url('school/logo-remove')) ?>" data-confirm="Remove the logo?">
        <?= $csrf->field() ?>
        <button type="submit" class="btn btn-small btn-danger-outline">Remove logo</button>
      </form>
    <?php endif; ?>
  </section>
<?php endif; ?>

<section aria-labelledby="periods-title" id="periods">
  <h2 id="periods-title">Teaching week</h2>
  <p class="muted">Click a period to block or unblock it. Friday 12:00 to 14:00 is blocked for Jumu'ah when the week is first made.
    Blocked periods are shaded and labelled.</p>
  <?php if (!$grid['rows']): ?>
    <div class="panel empty-state"><p>The week has no periods. Save the profile with a day start, day end and period length to create them.</p></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table grid-table">
        <thead>
          <tr><th scope="col">Time</th>
            <?php foreach (PeriodGenerator::DAYS as $name): ?><th scope="col"><?= e($name) ?></th><?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($grid['rows'] as $no => $times): ?>
            <tr>
              <th scope="row"><?= e($times['start']) ?> to <?= e($times['end']) ?></th>
              <?php foreach (array_keys(PeriodGenerator::DAYS) as $day): ?>
                <?php $slot = $grid['cells'][$no][$day] ?? null; ?>
                <td class="<?= $slot && $slot['is_blocked'] ? 'cell-blocked' : '' ?>">
                  <?php if ($slot): ?>
                    <form method="post" action="<?= e(url('periods/toggle')) ?>" class="inline-form">
                      <?= $csrf->field() ?>
                      <input type="hidden" name="id" value="<?= e($slot['id']) ?>">
                      <button type="submit" class="cell-button"
                              aria-label="<?= e(PeriodGenerator::DAYS[$day] . ' ' . $times['start'] . ': ' . ($slot['is_blocked'] ? 'blocked, click to open' : 'open, click to block')) ?>">
                        <?= $slot['is_blocked'] ? 'Blocked' : 'Open' ?>
                      </button>
                    </form>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
