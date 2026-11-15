<?php
/**
 * Dashboard.
 * @var array<string,int> $counts
 * @var string[] $warnings
 */
?>
<h1>Dashboard</h1>

<?php if ($warnings): ?>
  <section aria-labelledby="warnings-title" class="panel panel-warning">
    <h2 id="warnings-title">Things to do</h2>
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
    <?php foreach ($counts as $label => $value): ?>
      <div class="stat">
        <span class="stat-value"><?= e($value) ?></span>
        <span class="stat-label"><?= e($label) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</section>
