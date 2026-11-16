<?php
/**
 * Main layout (BUILD_SPEC 10.1): top bar with school switcher, semester,
 * Help, theme and user menu; collapsible sidebar groups; breadcrumbs;
 * first-visit tip; page content; next-step banner; help panel.
 * @var string $content page HTML, already escaped by the page template
 */
use App\Core\SchoolContext;
use App\Core\Theme;
use App\Data\SemesterRepository;

$schoolLogo = function (?SchoolContext $s, ?array $row = null): string {
    $r = $row ?? $s?->school;
    if (!$r) {
        return '';
    }
    if (!empty($r['logo_path'])) {
        return '<img class="school-logo" src="' . e(url('school/logo', ['s' => $r['slug'], 'v' => substr(md5((string) $r['logo_path']), 0, 8)])) . '" alt="">';
    }
    return '<span class="school-initial" aria-hidden="true">' . e(mb_strtoupper(mb_substr((string) $r['name'], 0, 1))) . '</span>';
};
$tourRoute = $currentRoute === 'public/school' ? 'dashboard' : $currentRoute;
?>
<!doctype html>
<html lang="en" data-theme-pref="<?= e($themePref) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e($csrf->token()) ?>">
  <meta name="theme-save-url" content="<?= e(\App\Core\Request::basePath() . '/api/theme.php') ?>">
  <meta name="help-save-url" content="<?= e(\App\Core\Request::basePath() . '/api/help.php') ?>">
  <title><?= e($title !== '' ? $title . ' | ' : '') ?><?= e($school ? $school->name() : $platformName) ?></title>
  <?= Theme::headScript() ?>
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
  <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="topbar">
  <button type="button" class="menu-toggle" aria-controls="sidebar" aria-expanded="false">
    <?= icon('menu') ?><span class="visually-hidden">Menu</span>
  </button>

  <details class="dropdown" data-tour="switcher">
    <summary aria-label="Current school: <?= e($school ? $school->name() : 'none') ?>. Switch school">
      <?= $schoolLogo($school) ?>
      <span class="school-name"><?= e($school ? $school->name() : $platformName) ?></span>
      <?= icon('chevron-down') ?>
    </summary>
    <div class="dropdown-menu">
      <p class="dropdown-label">Your schools</p>
      <?php foreach ($memberships as $m): ?>
        <form method="post" action="<?= e(url('schools/switch')) ?>">
          <?= $csrf->field() ?>
          <input type="hidden" name="school_id" value="<?= e($m['id']) ?>">
          <button type="submit"<?= $school && (int) $m['id'] === $school->id ? ' class="dropdown-current" aria-current="true"' : '' ?>>
            <?= $schoolLogo(null, $m) ?>
            <span><?= e($m['name']) ?><br><span class="small muted"><?= e(SchoolContext::ROLE_LABELS[$m['role']] ?? $m['role']) ?><?= $m['status'] !== 'active' ? ', suspended' : '' ?></span></span>
          </button>
        </form>
      <?php endforeach; ?>
      <hr>
      <a href="<?= e(url('schools/new')) ?>"><?= icon('plus') ?> Create another school</a>
    </div>
  </details>

  <div class="hide-narrow">
    <?php if ($school && $school->can('admin') && count($allSemesters) > 1): ?>
      <form method="post" action="<?= e(url('semesters/activate')) ?>" class="inline-form semester-switcher">
        <?= $csrf->field() ?>
        <input type="hidden" name="return" value="<?= e($currentRoute) ?>">
        <label for="semester-switch" class="visually-hidden">Active semester</label>
        <select id="semester-switch" name="id" data-autosubmit>
          <?php foreach ($allSemesters as $s): ?>
            <option value="<?= e($s['id']) ?>"<?= $s['is_active'] ? ' selected' : '' ?>><?= e(SemesterRepository::label($s)) ?></option>
          <?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="btn btn-small">Switch</button></noscript>
      </form>
    <?php elseif ($activeSemester): ?>
      <span class="badge"><?= e(SemesterRepository::label($activeSemester)) ?></span>
    <?php elseif ($school): ?>
      <span class="badge badge-warning">No active semester</span>
    <?php endif; ?>
  </div>

  <span class="topbar-spacer"></span>

  <button type="button" class="topbar-button" data-help-toggle aria-controls="help-panel" aria-expanded="false" data-tour="help">
    <?= icon('help') ?><span class="hide-narrow">Help</span>
  </button>
  <button type="button" class="topbar-button" data-theme-toggle data-tour="theme" aria-label="Theme: <?= e(Theme::CHOICES[$themePref] ?? 'System') ?>. Change theme">
    <span data-theme-icon="system"<?= $themePref === 'system' ? '' : ' hidden' ?>><?= icon('system') ?></span>
    <span data-theme-icon="light"<?= $themePref === 'light' ? '' : ' hidden' ?>><?= icon('sun') ?></span>
    <span data-theme-icon="dark"<?= $themePref === 'dark' ? '' : ' hidden' ?>><?= icon('moon') ?></span>
    <span class="hide-narrow" data-theme-label><?= e(Theme::CHOICES[$themePref] ?? 'System') ?></span>
  </button>

  <details class="dropdown">
    <summary aria-label="Your account"><?= icon('user') ?><span class="hide-narrow"><?= e($user['full_name'] ?? '') ?></span></summary>
    <div class="dropdown-menu align-right">
      <p class="dropdown-label"><?= e($user['email'] ?? '') ?><?= $school ? ' (' . e($school->roleLabel()) . ')' : '' ?></p>
      <a href="<?= e(url('account/profile')) ?>">Profile and theme</a>
      <a href="<?= e(url('account/profile')) ?>#password">Change password</a>
      <?php if ($school): ?>
        <a href="<?= e(url($tourRoute, ['tour' => '1'])) ?>">Restart the tour</a>
      <?php endif; ?>
      <a href="<?= e(url('help')) ?>">Help Centre</a>
      <hr>
      <form method="post" action="<?= e(url('auth/logout')) ?>">
        <?= $csrf->field() ?>
        <button type="submit">Sign out</button>
      </form>
    </div>
  </details>
</header>

<?php if ($verifyDaysLeft !== null): ?>
  <div class="verify-banner" role="status">
    <span>Please confirm your email address (<?= e($user['email']) ?>). We sent you a link.
      <?= $verifyDaysLeft > 0 ? 'You can keep working for ' . e($verifyDaysLeft) . ' more day(s) before it is needed.' : 'It is needed to keep working.' ?></span>
    <form method="post" action="<?= e(url('auth/resend-verification')) ?>">
      <?= $csrf->field() ?>
      <button type="submit" class="btn btn-small">Send the email again</button>
    </form>
  </div>
<?php endif; ?>

<div class="shell">
  <nav id="sidebar" class="sidebar" aria-label="Main menu" data-tour="sidebar">
    <div class="nav-groups">
      <?php foreach ($menu as $group): ?>
        <?php
        $groupHasCurrent = false;
        foreach ($group['items'] as $item) {
            $groupHasCurrent = $groupHasCurrent || $currentRoute === $item[1] || str_starts_with($currentRoute, $item[1] . '/');
        }
        ?>
        <details class="nav-group" data-group="<?= e($group['id']) ?>"<?= $groupHasCurrent ? ' open data-current' : '' ?>>
          <summary title="<?= e($group['label']) ?>">
            <?= icon($group['icon']) ?>
            <span class="nav-group-label"><?= e($group['label']) ?></span>
            <?= icon('chevron', 'icon chevron') ?>
          </summary>
          <ul class="nav-items">
            <?php foreach ($group['items'] as [$label, $route, , $itemIcon]): ?>
              <?php $active = $currentRoute === $route || str_starts_with($currentRoute, $route . '/'); ?>
              <li>
                <a href="<?= e(url($route)) ?>" data-tooltip="<?= e($label) ?>"<?= $active ? ' class="active" aria-current="page"' : '' ?><?= $route === 'setup' ? ' data-tour="guide"' : '' ?>>
                  <?= icon($itemIcon) ?>
                  <span class="nav-label"><?= e($label) ?></span>
                  <?php if ($route === 'setup' && $setup): ?>
                    <?php $circ = 2 * M_PI * 8; $filled = $setup['total'] ? $circ * $setup['done'] / $setup['total'] : 0; ?>
                    <svg class="ring" width="22" height="22" viewBox="0 0 22 22" aria-hidden="true">
                      <circle class="ring-track" cx="11" cy="11" r="8" fill="none" stroke-width="3"></circle>
                      <circle class="ring-value" cx="11" cy="11" r="8" fill="none" stroke-width="3" stroke-linecap="round"
                              stroke-dasharray="<?= e(round($filled, 2)) ?> <?= e(round($circ, 2)) ?>" transform="rotate(-90 11 11)"></circle>
                    </svg>
                    <span class="ring-text"><?= e($setup['done']) ?> of <?= e($setup['total']) ?></span>
                  <?php endif; ?>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </details>
      <?php endforeach; ?>
    </div>
    <div class="sidebar-foot">
      <button type="button" class="rail-toggle" data-rail-toggle aria-pressed="false">
        <?= icon('collapse') ?><span class="rail-toggle-label">Collapse menu</span>
      </button>
    </div>
  </nav>

  <main id="main" class="content" tabindex="-1">
    <nav class="crumbs" aria-label="Breadcrumb">
      <ol>
        <?php foreach ($crumbs as $i => [$label, $route]): ?>
          <?php $last = $i === count($crumbs) - 1; ?>
          <li><?php if ($route && !$last): ?><a href="<?= e(url($route)) ?>"><?= e($label) ?></a><?php else: ?><span<?= $last ? ' aria-current="page"' : '' ?>><?= e($label) ?></span><?php endif; ?></li>
        <?php endforeach; ?>
      </ol>
    </nav>

    <?php foreach ($flashes as $flash): ?>
      <div class="alert alert-<?= e($flash['type']) ?>" role="<?= $flash['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>

    <?php if ($tip): ?>
      <section class="tip-card" data-tip="<?= e($tip['key']) ?>" aria-label="Tip for this page">
        <?= icon('info') ?>
        <div class="tip-body">
          <p><?= e($tip['text']) ?></p>
          <div class="button-row">
            <?php if (!empty($tip['route'])): ?>
              <a class="btn btn-small btn-primary" href="<?= e(url($tip['route'])) ?>"><?= e($tip['action']) ?></a>
            <?php endif; ?>
            <button type="button" class="btn btn-small" data-tip-dismiss>Got it, hide this tip</button>
          </div>
        </div>
      </section>
    <?php endif; ?>

    <?= $content ?>

    <?php if ($setup && $setup['banner'] && $setup['next']): ?>
      <aside class="next-banner" aria-label="Setup Guide">
        <p><strong><?= e($setup['done']) ?> of <?= e($setup['total']) ?> setup steps done.</strong>
          Next: <?= e($setup['next']['title']) ?>.</p>
        <div class="button-row">
          <a class="btn btn-primary btn-small" href="<?= e(url($setup['next']['route'])) ?>"><?= e($setup['next']['button']) ?></a>
          <a class="btn btn-small" href="<?= e(url('setup')) ?>">Open the Setup Guide</a>
        </div>
      </aside>
    <?php endif; ?>

    <footer class="content-foot">
      <a href="<?= e(url('about')) ?>">About this app</a> · <a href="<?= e(url('help')) ?>">Help Centre</a>
      <?php if ($school): ?> · Public timetable: <a href="<?= e(public_url($school->slug())) ?>"><?= e(absolute_url(public_url($school->slug()))) ?></a><?php endif; ?>
      · <?= e($platformName) ?>
    </footer>
  </main>

  <aside id="help-panel" class="help-panel" hidden aria-labelledby="help-panel-title" tabindex="-1">
    <div class="help-panel-head">
      <h2 id="help-panel-title">Help: <?= e($title !== '' ? $title : 'this page') ?></h2>
      <button type="button" class="icon-button" data-help-close aria-label="Close help"><?= icon('close') ?></button>
    </div>
    <?php if ($helpKey !== null): ?>
      <?= $this->partial('help/' . $helpKey, ['school' => $school]) ?>
    <?php else: ?>
      <p>This page has no extra help. The <a href="<?= e(url('help')) ?>">Help Centre</a> explains every part of the system.</p>
    <?php endif; ?>
    <p class="small muted">More answers in the <a href="<?= e(url('help')) ?>">Help Centre</a>.</p>
  </aside>
</div>
<?php if ($tourAutostart): ?><div hidden data-tour-autostart></div><?php endif; ?>
</body>
</html>
