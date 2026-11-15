<?php
/**
 * Table of jobs.
 * @var array $jobs
 * @var bool  $selectable  show compare checkboxes (optional)
 */
$selectable = $selectable ?? false;
$statusTag = ['finished' => 'tag-success', 'failed' => 'tag-danger', 'stopped' => 'tag-warning', 'running' => 'tag-info', 'queued' => 'tag-info'];
?>
<div class="table-wrap">
  <table class="table">
    <thead>
      <tr>
        <?php if ($selectable): ?><th scope="col"><span class="visually-hidden">Compare</span></th><?php endif; ?>
        <th scope="col">Run</th><th scope="col">Label</th><th scope="col">Type</th><th scope="col">Status</th>
        <th scope="col" class="num">V_H</th><th scope="col" class="num">Soft</th><th scope="col" class="num">Penalty</th>
        <th scope="col" class="num">Generations</th><th scope="col">Created</th><th scope="col"><span class="visually-hidden">Open</span></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($jobs as $job): ?>
        <tr>
          <?php if ($selectable): ?>
            <td><input type="checkbox" name="ids[]" value="<?= e($job['id']) ?>" aria-label="Compare run <?= e($job['id']) ?>"
                       form="compare-form"<?= in_array($job['status'], ['finished', 'stopped'], true) ? '' : ' disabled' ?>></td>
          <?php endif; ?>
          <td>#<?= e($job['id']) ?><?= $job['is_published'] ? ' <span class="tag tag-success">Published</span>' : '' ?></td>
          <td><?= e($job['label']) ?></td>
          <td><?= $job['job_type'] === 'manual' ? 'Manual' : ($job['repair_enabled'] ? 'GA + repair' : 'Standard GA') ?></td>
          <td><span class="tag <?= e($statusTag[$job['status']] ?? '') ?>"><?= e(ucfirst($job['status'])) ?></span></td>
          <td class="num"><?= e($job['hard_violations']) ?></td>
          <td class="num"><?= e($job['soft_penalty']) ?></td>
          <td class="num"><?= e($job['best_penalty']) ?></td>
          <td class="num"><?= e($job['generations_run']) ?></td>
          <td><?= e(substr((string) $job['created_at'], 0, 16)) ?></td>
          <td class="actions">
            <?php if (!in_array($job['status'], ['finished', 'stopped'], true)): ?>
              <a class="btn btn-small" href="<?= e(url('generate/job', ['id' => $job['id']])) ?>">Progress</a>
            <?php else: ?>
              <a class="btn btn-small" href="<?= e(url('timetables/view', ['id' => $job['id']])) ?>">Open</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
