<?php
/**
 * Rules and weights (BUILD_SPEC section 6).
 * @var array $weights
 * @var array $errors
 * @var array $old
 */
$meaning = [
    'H' => 'Every clash or other hard rule break (H1 to H6 below). Keep this far bigger than any soft weight, so no comfort is ever bought with a clash.',
    'S1' => 'A class outside the periods a lecturer marked as preferred (only for lecturers who marked some).',
    'S2' => 'An empty period between two classes of a level on the same day.',
    'S3' => 'A second (or third) class of the same course on one day.',
    'S4' => 'Each hour beyond three classes in a row for a level.',
    'S5' => 'A class in the last period of the day.',
];
?>
<h1>Rules and weights</h1>
<p class="page-intro">The timetable is scored with these rules. Each break adds its weight to the <?= term('penalty') ?>; the search keeps the penalty as low as it can.</p>

<section class="panel" aria-labelledby="hard-title">
  <h2 id="hard-title"><?= term('hard rule', 'Hard rules') ?>: must never be broken</h2>
  <ul>
    <li><strong>H1</strong> A lecturer is in only one place at a time.</li>
    <li><strong>H2</strong> A room holds only one class at a time.</li>
    <li><strong>H3</strong> A level has only one class at a time.</li>
    <li><strong>H4</strong> The room has enough seats for the class.</li>
    <li><strong>H5</strong> Practicals are held in laboratories.</li>
    <li><strong>H6</strong> No class in a blocked period, while its lecturer is unavailable, or running past the end of the day.</li>
  </ul>
</section>

<?php if ($errors): ?>
  <div class="alert alert-error" role="alert">Nothing was saved. Please correct the weights marked below.</div>
<?php endif; ?>

<form method="post" action="<?= e(url('rules')) ?>" class="panel" novalidate>
  <?= $csrf->field() ?>
  <h2><?= term('weight', 'Weights') ?></h2>
  <div class="table-wrap">
    <table class="table table-stack">
      <thead><tr><th scope="col">Code</th><th scope="col">Rule</th><th scope="col">What counts as one break</th><th scope="col" class="num">Weight</th></tr></thead>
      <tbody>
      <?php foreach ($weights as $w): ?>
        <?php $err = $errors['weight_' . $w['code']] ?? null; $value = $old[$w['code']] ?? $w['weight']; ?>
        <tr>
          <td data-label="Code"><strong><?= e($w['code']) ?></strong> <span class="tag<?= $w['constraint_type'] === 'hard' ? ' tag-danger' : ' tag-info' ?>"><?= $w['constraint_type'] === 'hard' ? 'Hard' : 'Soft' ?></span></td>
          <td data-label="Rule"><label for="w_<?= e($w['code']) ?>"><?= e($w['description']) ?></label></td>
          <td data-label="One break" class="small"><?= e($meaning[$w['code']] ?? '') ?></td>
          <td data-label="Weight" class="num<?= $err ? ' has-error' : '' ?>">
            <input type="number" min="<?= $w['code'] === 'H' ? '1' : '0' ?>" id="w_<?= e($w['code']) ?>" name="weight[<?= e($w['code']) ?>]"
                   value="<?= e($value) ?>" class="input-narrow"<?= $err ? ' aria-invalid="true" aria-describedby="w_' . e($w['code']) . '_error"' : '' ?>>
            <?php if ($err): ?><p class="field-error" id="w_<?= e($w['code']) ?>_error"><?= e($err) ?></p><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="small muted">Default weights: hard 1000, S1 5, S2 3, S3 4, S4 3, S5 2. New runs use the weights saved here; every saved run keeps the weights it was run with.</p>
  <button type="submit" class="btn btn-primary">Save weights</button>
</form>
