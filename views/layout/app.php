<?php
/**
 * Main layout: top bar, left menu, flash messages and page content.
 * @var string $content  page HTML, already escaped by the page template
 */
use App\Data\SemesterRepository;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e($csrf->token()) ?>">
  <title><?= e($title !== '' ? $title . ' | ' : '') ?>Timetable | <?= e($department) ?></title>
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
  <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="topbar">
  <button type="button" class="menu-toggle" aria-controls="sidebar" aria-expanded="false">
    <span aria-hidden="true">&#9776;</span><span class="visually-hidden">Menu</span>
  </button>
  <div class="brand">
    <span class="brand-institution"><?= e($institution) ?></span>
    <span class="brand-department"><?= e($department) ?></span>
  </div>
  <div class="topbar-semester">
    <?php if ($isAdmin && count($allSemesters) > 1): ?>
      <form method="post" action="<?= e(url('semesters/activate')) ?>" class="inline-form semester-switcher">
        <?= $csrf->field() ?>
        <input type="hidden" name="return" value="<?= e($currentRoute) ?>">
        <label for="semester-switch" class="visually-hidden">Active semester</label>
        <select id="semester-switch" name="id" data-autosubmit>
          <?php foreach ($allSemesters as $s): ?>
            <option value="<?= e($s['id']) ?>"<?= $s['is_active'] ? ' selected' : '' ?>><?= e(SemesterRepository::label($s)) ?></option>
          <?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="btn btn-small btn-ghost">Switch</button></noscript>
      </form>
    <?php elseif ($activeSemester): ?>
      <span class="badge"><?= e(SemesterRepository::label($activeSemester)) ?></span>
    <?php else: ?>
      <span class="badge badge-warning">No active semester</span>
    <?php endif; ?>
  </div>
  <div class="topbar-user">
    <span class="user-name"><?= e($user['full_name'] ?? '') ?>
      <small>(<?= e($user['role'] ?? '') ?>)</small></span>
    <form method="post" action="<?= e(url('auth/logout')) ?>" class="inline-form">
      <?= $csrf->field() ?>
      <button type="submit" class="btn btn-small btn-ghost">Sign out</button>
    </form>
  </div>
</header>

<div class="shell">
  <nav id="sidebar" class="sidebar" aria-label="Main menu">
    <?php foreach ($menu as $section): ?>
      <?php
      $items = array_filter($section['items'], fn ($item) => !$item[2] || $isAdmin);
      if (!$items) { continue; }
      ?>
      <?php if ($section['heading']): ?>
        <p class="menu-heading"><?= e($section['heading']) ?></p>
      <?php endif; ?>
      <ul class="menu">
        <?php foreach ($items as [$label, $route]): ?>
          <?php $active = $currentRoute === $route || str_starts_with($currentRoute, $route . '/'); ?>
          <li><a href="<?= e(url($route)) ?>"<?= $active ? ' class="active" aria-current="page"' : '' ?>><?= e($label) ?></a></li>
        <?php endforeach; ?>
      </ul>
    <?php endforeach; ?>
  </nav>

  <main id="main" class="content" tabindex="-1">
    <?php foreach ($flashes as $flash): ?>
      <div class="alert alert-<?= e($flash['type']) ?>" role="<?= $flash['type'] === 'error' ? 'alert' : 'status' ?>">
        <?= e($flash['message']) ?>
      </div>
    <?php endforeach; ?>
    <?= $content ?>
  </main>
</div>
</body>
</html>
