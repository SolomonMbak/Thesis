<?php
/**
 * Members and invitations.
 * @var array $members
 * @var array $invites
 * @var array $roles role => label (admin, officer, viewer)
 * @var array $errors
 * @var array $old
 * @var int $me
 */
use App\Core\SchoolContext;

$roleHelp = 'Administrator: everything except deleting the school. Timetable officer: data, readiness, runs and publishing. Viewer: can only look at timetables and reports.';
?>
<h1>Members and invitations</h1>
<p class="page-intro">Who can open <?= e($school->name()) ?> and what each person may do. Invite colleagues by email; each one signs in with their own password.</p>

<section class="panel" aria-labelledby="invite-title">
  <h2 id="invite-title">Invite someone</h2>
  <form method="post" action="<?= e(url('members/invite')) ?>" novalidate>
    <?= $csrf->field() ?>
    <div class="form-grid">
      <?= $this->partial('partials/field', ['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'value' => $old['email'],
          'errors' => $errors, 'required' => true, 'help' => 'They get a link that works for 7 days. No account is needed beforehand.']) ?>
      <?= $this->partial('partials/field', ['name' => 'role', 'label' => 'Role', 'type' => 'select', 'value' => $old['role'],
          'options' => $roles, 'errors' => $errors, 'help' => $roleHelp]) ?>
    </div>
    <button type="submit" class="btn btn-primary">Send invitation</button>
  </form>
</section>

<h2>Members</h2>
<div class="table-wrap">
  <table class="table table-stack">
    <thead><tr><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Role</th><th scope="col">Last signed in</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
    <tbody>
      <?php foreach ($members as $m): ?>
        <tr>
          <td data-label="Name"><?= e($m['full_name']) ?><?= (int) $m['id'] === $me ? ' <span class="tag">You</span>' : '' ?></td>
          <td data-label="Email"><?= e($m['email']) ?><?= $m['email_verified_at'] ? '' : ' <span class="tag tag-warning">Email not confirmed</span>' ?></td>
          <td data-label="Role">
            <?php if ($m['role'] === 'owner'): ?>
              <strong>Owner</strong>
            <?php else: ?>
              <form method="post" action="<?= e(url('members/role')) ?>" class="inline-form">
                <?= $csrf->field() ?>
                <input type="hidden" name="user_id" value="<?= e($m['id']) ?>">
                <label for="role-<?= e($m['id']) ?>" class="visually-hidden">Role of <?= e($m['full_name']) ?></label>
                <select id="role-<?= e($m['id']) ?>" name="role" class="input-inline" data-autosubmit>
                  <?php foreach ($roles as $value => $label): ?>
                    <option value="<?= e($value) ?>"<?= $m['role'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
                <noscript><button type="submit" class="btn btn-small">Save</button></noscript>
              </form>
            <?php endif; ?>
          </td>
          <td data-label="Last signed in"><?= e($m['last_login_at'] ? substr((string) $m['last_login_at'], 0, 16) : 'Never') ?></td>
          <td class="actions">
            <?php if ($m['role'] !== 'owner'): ?>
              <form method="post" action="<?= e(url('members/remove')) ?>" class="inline-form"
                    data-confirm="Remove <?= e($m['full_name']) ?> from <?= e($school->name()) ?>? They keep their account for other schools.">
                <?= $csrf->field() ?>
                <input type="hidden" name="user_id" value="<?= e($m['id']) ?>">
                <button type="submit" class="btn btn-small btn-danger-outline"><?= (int) $m['id'] === $me ? 'Leave the school' : 'Remove' ?></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<h2>Waiting invitations</h2>
<?php if (!$invites): ?>
  <p class="muted">No invitations are waiting. When you invite someone, the invitation stays here until they accept it or it expires.</p>
<?php else: ?>
  <div class="table-wrap">
    <table class="table table-stack">
      <thead><tr><th scope="col">Email</th><th scope="col">Role</th><th scope="col">Invited by</th><th scope="col">Expires</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
      <tbody>
        <?php foreach ($invites as $i): ?>
          <tr>
            <td data-label="Email"><?= e($i['email']) ?></td>
            <td data-label="Role"><?= e(SchoolContext::ROLE_LABELS[$i['role']] ?? $i['role']) ?></td>
            <td data-label="Invited by"><?= e($i['invited_by_name']) ?></td>
            <td data-label="Expires"><?= e(substr((string) $i['expires_at'], 0, 16)) ?></td>
            <td class="actions">
              <form method="post" action="<?= e(url('members/invite')) ?>" class="inline-form">
                <?= $csrf->field() ?>
                <input type="hidden" name="email" value="<?= e($i['email']) ?>">
                <input type="hidden" name="role" value="<?= e($i['role']) ?>">
                <button type="submit" class="btn btn-small">Send again</button>
              </form>
              <form method="post" action="<?= e(url('members/revoke')) ?>" class="inline-form" data-confirm="Withdraw this invitation?">
                <?= $csrf->field() ?>
                <input type="hidden" name="id" value="<?= e($i['id']) ?>">
                <button type="submit" class="btn btn-small btn-danger-outline">Withdraw</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
