<?php
/**
 * Platform: every school, without its data.
 * @var array $schools
 */
?>
<h1>Schools</h1>
<p class="page-intro">Every school on this installation. You see only names, owners and counts; to look inside a school you must be invited to it like anyone else.</p>

<?php if (!$schools): ?>
  <div class="panel empty-state"><p>No schools yet. Schools appear here when someone signs up or creates one.</p></div>
<?php else: ?>
  <div class="table-wrap">
    <table class="table table-stack">
      <thead><tr><th scope="col">School</th><th scope="col">Public link</th><th scope="col">Owner</th><th scope="col" class="num">Members</th>
        <th scope="col" class="num">Runs</th><th scope="col">Last run</th><th scope="col">Setup</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
      <tbody>
        <?php foreach ($schools as $s): ?>
          <tr>
            <td data-label="School"><?= e($s['name']) ?><?= $s['department_label'] ? '<br><span class="small muted">' . e($s['department_label']) . '</span>' : '' ?></td>
            <td data-label="Public link"><a href="<?= e(public_url($s['slug'])) ?>">/s/<?= e($s['slug']) ?></a></td>
            <td data-label="Owner"><?= e($s['owner_email']) ?></td>
            <td data-label="Members" class="num"><?= e($s['members']) ?></td>
            <td data-label="Runs" class="num"><?= e($s['runs']) ?></td>
            <td data-label="Last run"><?= e($s['last_run_at'] ? substr((string) $s['last_run_at'], 0, 16) : 'Never') ?></td>
            <td data-label="Setup"><?= $s['setup_completed_at'] ? '<span class="tag tag-success">Complete</span>' : '<span class="tag">In progress</span>' ?></td>
            <td data-label="Status"><?= $s['status'] === 'active' ? '<span class="tag tag-success">Active</span>' : '<span class="tag tag-danger">Suspended</span>' ?></td>
            <td class="actions">
              <form method="post" action="<?= e(url('platform/schools/status')) ?>" class="inline-form"
                    data-confirm="<?= $s['status'] === 'active' ? 'Suspend this school? Its members will not be able to open it and its public page will be hidden.' : 'Reactivate this school?' ?>">
                <?= $csrf->field() ?>
                <input type="hidden" name="id" value="<?= e($s['id']) ?>">
                <input type="hidden" name="status" value="<?= $s['status'] === 'active' ? 'suspended' : 'active' ?>">
                <button type="submit" class="btn btn-small<?= $s['status'] === 'active' ? ' btn-danger-outline' : '' ?>"><?= $s['status'] === 'active' ? 'Suspend' : 'Reactivate' ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
