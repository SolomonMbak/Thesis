<?php
/**
 * Table of runs. Stacks into cards on narrow screens.
 * @var array $jobs
 * @var bool  $selectable  show compare checkboxes (optional)
 */
$selectable = $selectable ?? false;
$statusTag = ['finished' => 'tag-success', 'failed' => 'tag-danger', 'stopped' => 'tag-warning', 'running' => 'tag-info', 'queued' => 'tag-info'];
$statusText = ['finished' => 'Finished', 'failed' => 'Failed', 'stopped' => 'Stopped', 'running' => 'Running', 'queued' => 'Waiting to start'];
?>
<div class="table-wrap">
  <table class="table table-stack">
    <thead>
      <tr>
        <?php if ($selectable): ?><th scope="col"><span class="visually-hidden">Compare</span></th><?php endif; ?>
        <th scope="col">Run</th><th scope="col">Label</th><th scope="col">Type</th><th scope="col">Status</th>
        <th scope="col" class="num">Clashes</th><th scope="col" class="num">Soft penalty</th><th scope="col" class="num">Penalty</th>
        <th scope="col" class="num">Generations</th><th scope="col">Created</th><th scope="col"><span class="visually-hidden">Open</span></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($jobs as $job): ?>
        <tr>
          <?php if ($selectable): ?>
            <td data-label="Compare"><input type="checkbox" name="ids[]" value="<?= e($job['id']) ?>" aria-label="Compare run <?= e($job['id']) ?>"
                       form="compare-form"<?= in_array($job['status'], ['finished', 'stopped'], true) ? '' : ' disabled' ?>></td>
          <?php endif; ?>
          <td data-label="Run">#<?= e($job['id']) ?><?= $job['is_published'] ? ' <span class="tag tag-success">Published</span>' : '' ?></td>
          <td data-label="Label"><?= e($job['label']) ?></td>
          <td data-label="Type"><?= $job['job_type'] === 'manual' ? 'Manual (imported)' : ($job['repair_enabled'] ? 'Genetic algorithm with repair' : 'Standard genetic algorithm') ?></td>
          <td data-label="Status"><span class="tag <?= e($statusTag[$job['status']] ?? '') ?>"><?= e($statusText[$job['status']] ?? $job['status']) ?></span></td>
          <td data-label="Clashes" class="num"><?= $job['hard_violations'] === null ? '' : ((int) $job['hard_violations'] === 0 ? '<span class="text-success">0</span>' : '<span class="text-danger">' . e($job['hard_violations']) . '</span>') ?></td>
          <td data-label="Soft penalty" class="num"><?= e($job['soft_penalty']) ?></td>
          <td data-label="Penalty" class="num"><?= e($job['best_penalty']) ?></td>
          <td data-label="Generations" class="num"><?= e($job['generations_run']) ?></td>
          <td data-label="Created"><?= e(substr((string) $job['created_at'], 0, 16)) ?></td>
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
